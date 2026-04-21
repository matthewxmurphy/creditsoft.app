#!/usr/bin/env node

import { existsSync, readFileSync } from 'node:fs';
import { createServer } from 'node:http';

const listenHost = process.env.CREDITSOFT_ROUTER_LISTEN || '127.0.0.1';
const listenPort = Number.parseInt(process.env.CREDITSOFT_ROUTER_PORT || '8877', 10);
const tokenFile = process.env.CREDITSOFT_API_TOKEN_FILE || '';
const apiToken = (process.env.CREDITSOFT_API_TOKEN || readSecret(tokenFile)).trim();
const selectionStrategy = normalizeStrategy(process.env.CREDITSOFT_ROUTER_SELECTION_STRATEGY || 'resource-aware');
const preferredApiBase = normalizeApiBase(process.env.CREDITSOFT_ROUTER_PREFERRED_BASE_URL || '');
const apiBases = (process.env.CREDITSOFT_API_BASES || 'http://127.0.0.1:8001/api/v1')
    .split(',')
    .map((entry) => normalizeApiBase(entry))
    .filter(Boolean);

let selectedApiBase = apiBases[0] || 'http://127.0.0.1:8001/api/v1';
let selectedAt = null;
let selectedLatencyMs = null;
let selectedAuthenticated = false;
let selectedRouterHint = null;
let lastProbeResults = [];

function readSecret(path) {
    if (!path || !existsSync(path)) {
        return '';
    }

    return readFileSync(path, 'utf8');
}

function normalizeApiBase(value) {
    const trimmed = String(value || '').trim();
    if (!trimmed) {
        return null;
    }

    const withScheme = /^https?:\/\//i.test(trimmed) ? trimmed : `https://${trimmed}`;
    const url = new URL(withScheme);
    const pathname = url.pathname.replace(/\/+$/, '');
    url.pathname = pathname.endsWith('/api/v1') ? pathname : `${pathname || ''}/api/v1`;
    url.search = '';
    url.hash = '';

    return url.toString().replace(/\/$/, '');
}

function originFromApiBase(apiBase) {
    const url = new URL(apiBase);

    return `${url.protocol}//${url.host}`;
}

function normalizeStrategy(value) {
    return ['resource-aware', 'fastest', 'ordered'].includes(value)
        ? value
        : 'resource-aware';
}

async function fetchJson(url, options = {}) {
    const startedAt = Date.now();
    const response = await fetch(url, {
        ...options,
        headers: {
            Accept: 'application/json',
            ...(options.headers || {}),
        },
    });
    const text = await response.text();
    let body = null;

    try {
        body = text ? JSON.parse(text) : null;
    } catch {
        body = text;
    }

    return {
        ok: response.ok,
        status: response.status,
        body,
        durationMs: Date.now() - startedAt,
    };
}

async function selectApiBase() {
    const probes = [];

    for (const apiBase of apiBases) {
        try {
            const overview = await fetchJson(apiBase);
            if (!overview.ok) {
                probes.push({
                    apiBase,
                    reachable: false,
                    authenticated: false,
                    latencyMs: overview.durationMs,
                    routerHint: overview.body?.data?.router || null,
                    status: overview.status,
                });
                continue;
            }

            let authenticated = false;
            let latencyMs = overview.durationMs;
            let routerHint = overview.body?.data?.router || null;

            if (apiToken) {
                const handshake = await fetchJson(`${apiBase}/client/handshake`, {
                    headers: {
                        Authorization: `Bearer ${apiToken}`,
                    },
                });
                authenticated = handshake.ok;
                latencyMs += handshake.durationMs;
                routerHint = handshake.body?.data?.router || routerHint;

                if (!authenticated) {
                    const stats = await fetchJson(`${apiBase}/office-stats`, {
                        headers: {
                            Authorization: `Bearer ${apiToken}`,
                        },
                    });
                    authenticated = stats.ok;
                    latencyMs += stats.durationMs;
                }
            }

            probes.push({
                apiBase,
                reachable: true,
                authenticated,
                latencyMs,
                routerHint,
                status: overview.status,
            });
        } catch {
            probes.push({
                apiBase,
                reachable: false,
                authenticated: false,
                latencyMs: null,
                routerHint: null,
                status: null,
            });
            continue;
        }
    }

    lastProbeResults = probes;
    const selected = selectProbe(probes);

    if (selected) {
        selectedApiBase = selected.apiBase;
        selectedAt = new Date().toISOString();
        selectedLatencyMs = selected.latencyMs;
        selectedAuthenticated = selected.authenticated;
        selectedRouterHint = selected.routerHint;
        return;
    }

    selectedAt = new Date().toISOString();
    selectedLatencyMs = null;
    selectedAuthenticated = false;
    selectedRouterHint = null;
}

function selectProbe(probes) {
    const connected = probes.filter((probe) => probe.reachable);

    if (connected.length === 0) {
        return null;
    }

    if (selectionStrategy === 'ordered') {
        return connected.find((probe) => !apiToken || probe.authenticated) || connected[0] || null;
    }

    const authenticated = connected.filter((probe) => !apiToken || probe.authenticated);
    const pool = authenticated.length > 0 ? authenticated : connected;

    if (selectionStrategy === 'fastest') {
        return [...pool].sort((left, right) => latencyFor(left) - latencyFor(right))[0] || null;
    }

    return [...pool].sort((left, right) => probeScore(left) - probeScore(right))[0] || null;
}

function latencyFor(probe) {
    return Number.isFinite(probe.latencyMs) ? probe.latencyMs : 100000;
}

function probeMatchesPreferredBase(probe) {
    const hintPreferred = normalizeApiBase(probe.routerHint?.preferred_api_base_url || '');
    const preferred = preferredApiBase || hintPreferred;

    return Boolean(preferred && preferred === probe.apiBase);
}

function probeScore(probe) {
    const health = probe.routerHint?.node_health || {};
    const memoryPenalty = memoryPressurePenalty(health);
    const swapPenalty = Number.isFinite(health.swap_used_percent)
        ? Number(health.swap_used_percent) * 45
        : 0;
    const cpuCores = Math.max(Number(health.cpu_cores || 1), 1);
    const loadPenalty = Number.isFinite(health.load_one)
        ? (Number(health.load_one) / cpuCores) * 200
        : 0;
    const authPenalty = apiToken && !probe.authenticated ? 50000 : 0;
    const preferredBonus = probeMatchesPreferredBase(probe) ? -25000 : 0;

    return latencyFor(probe) + memoryPenalty + swapPenalty + loadPenalty + authPenalty + preferredBonus;
}

function memoryPressurePenalty(health) {
    const level = String(health.memory_pressure_level || '').toLowerCase();

    if (level === 'healthy') {
        return 0;
    }

    if (Number.isFinite(health.memory_pressure_free_percent)) {
        return Math.max(0, 100 - Number(health.memory_pressure_free_percent)) * 6;
    }

    if (Number.isFinite(health.memory_available_percent)) {
        return Math.max(0, 100 - Number(health.memory_available_percent)) * 6;
    }

    return Number.isFinite(health.memory_used_percent)
        ? Number(health.memory_used_percent) * 12
        : 0;
}

function collectBody(request) {
    return new Promise((resolve, reject) => {
        const chunks = [];
        request.on('data', (chunk) => chunks.push(chunk));
        request.on('end', () => resolve(chunks.length ? Buffer.concat(chunks) : undefined));
        request.on('error', reject);
    });
}

const hopByHopHeaders = new Set([
    'accept-encoding',
    'connection',
    'content-length',
    'host',
    'keep-alive',
    'proxy-authenticate',
    'proxy-authorization',
    'te',
    'trailer',
    'transfer-encoding',
    'upgrade',
]);

function requestHeaders(request) {
    const headers = new Headers();

    for (const [name, value] of Object.entries(request.headers)) {
        if (hopByHopHeaders.has(name.toLowerCase()) || typeof value === 'undefined') {
            continue;
        }

        headers.set(name, Array.isArray(value) ? value.join(', ') : value);
    }

    headers.set('x-creditsoft-client-router', 'native-node');
    headers.set('x-forwarded-proto', 'http');

    if (request.headers.host) {
        headers.set('x-forwarded-host', request.headers.host);
    }

    if (apiToken && request.url?.startsWith('/api/v1') && !headers.has('authorization')) {
        headers.set('authorization', `Bearer ${apiToken}`);
    }

    return headers;
}

function responseHeaders(upstream, targetOrigin, routerOrigin) {
    const headers = {};

    upstream.headers.forEach((value, name) => {
        const lower = name.toLowerCase();
        if (['connection', 'content-encoding', 'content-length', 'host', 'transfer-encoding'].includes(lower)) {
            return;
        }

        headers[name] = lower === 'location' && value.startsWith(targetOrigin)
            ? value.replace(targetOrigin, routerOrigin)
            : value;
    });

    return headers;
}

function requestAcceptsHtml(request) {
    const accept = String(request.headers.accept || '');

    return accept.includes('text/html') || accept.includes('application/xhtml+xml');
}

function requestWantsJson(request) {
    const accept = String(request.headers.accept || '');

    return accept.includes('application/json')
        || accept.includes('text/json')
        || String(request.url || '').startsWith('/api/');
}

function routerRequestUrl(request) {
    return new URL(request.url || '/', `http://${request.headers.host || `${listenHost}:${listenPort}`}`);
}

function offlineHtml({ targetOrigin, errorMessage }) {
    const escapedTarget = targetOrigin.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    const escapedError = errorMessage.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

    return `<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>CreditSoft office unavailable</title>
  <style>
    :root { color-scheme: light; font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
    body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #f7f3ea; color: #1c1917; }
    main { width: min(92vw, 560px); border: 1px solid #e7d8bd; background: #fffaf0; padding: 28px; box-shadow: 0 24px 60px rgba(120, 113, 108, 0.18); }
    h1 { margin: 0 0 12px; font-size: 24px; line-height: 1.2; }
    p { margin: 0 0 14px; color: #57534e; line-height: 1.6; }
    code { display: block; white-space: pre-wrap; word-break: break-word; border: 1px solid #eadfcb; background: #fff; padding: 12px; color: #44403c; }
    a, button { appearance: none; border: 0; background: #eab308; color: #1c1917; font-weight: 700; padding: 11px 14px; text-decoration: none; cursor: pointer; }
    .actions { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 18px; }
  </style>
</head>
<body>
  <main>
    <h1>Your local office app is temporarily unavailable.</h1>
    <p>The local router is running, but it could not reach the selected CreditSoft office node.</p>
    <code>Target: ${escapedTarget}\nError: ${escapedError}</code>
    <div class="actions">
      <button type="button" onclick="window.location.reload()">Reconnect</button>
      <a href="/__creditsoft/client/status">Router status</a>
    </div>
  </main>
</body>
</html>`;
}

function sendProxyFailure(request, response, error) {
    const requestUrl = routerRequestUrl(request);
    const targetOrigin = originFromApiBase(selectedApiBase);
    const errorMessage = error instanceof Error ? error.message : String(error);

    if (request.headers['x-inertia']) {
        response.writeHead(409, {
            'content-type': 'text/plain; charset=utf-8',
            'x-inertia-location': requestUrl.href,
            vary: 'X-Inertia',
        });
        response.end('CreditSoft office node unavailable. Reloading the local router status page.');
        return;
    }

    if (requestAcceptsHtml(request) && ! requestWantsJson(request)) {
        response.writeHead(503, {
            'content-type': 'text/html; charset=utf-8',
            'cache-control': 'no-store',
        });
        response.end(offlineHtml({ targetOrigin, errorMessage }));
        return;
    }

    response.writeHead(502, { 'content-type': 'application/json; charset=utf-8' });
    response.end(JSON.stringify({
        message: 'CreditSoft local router could not reach the office server.',
        targetOrigin,
        error: errorMessage,
    }, null, 2));
}

async function proxy(request, response) {
    const targetOrigin = originFromApiBase(selectedApiBase);
    const routerOrigin = `http://${listenHost}:${listenPort}`;
    const targetUrl = new URL(request.url || '/', targetOrigin);
    const method = request.method || 'GET';
    const body = ['GET', 'HEAD'].includes(method) ? undefined : await collectBody(request);
    const upstream = await fetch(targetUrl, {
        method,
        headers: requestHeaders(request),
        body,
        redirect: 'manual',
    });
    const payload = Buffer.from(await upstream.arrayBuffer());

    response.writeHead(upstream.status, upstream.statusText, responseHeaders(upstream, targetOrigin, routerOrigin));
    response.end(payload);
}

const server = createServer((request, response) => {
    const requestUrl = routerRequestUrl(request);

    if (requestUrl.pathname === '/__creditsoft/client/status') {
        response.writeHead(200, { 'content-type': 'application/json; charset=utf-8' });
        response.end(JSON.stringify({
            status: 'ok',
            targetOrigin: originFromApiBase(selectedApiBase),
            apiBase: selectedApiBase,
            selectedAt,
            latencyMs: selectedLatencyMs,
            authenticated: selectedAuthenticated,
            tokenProvided: Boolean(apiToken),
            selectionStrategy,
            routerHint: selectedRouterHint,
            candidates: apiBases,
            probes: lastProbeResults,
        }, null, 2));
        return;
    }

    proxy(request, response).catch((error) => sendProxyFailure(request, response, error));
});

await selectApiBase();

server.listen(listenPort, listenHost, () => {
    console.log(`CreditSoft loopback router listening on http://${listenHost}:${listenPort} -> ${originFromApiBase(selectedApiBase)}`);
});
