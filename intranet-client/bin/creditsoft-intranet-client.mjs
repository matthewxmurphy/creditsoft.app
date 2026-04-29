#!/usr/bin/env node

import { spawn } from 'node:child_process';
import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { createServer } from 'node:http';
import os from 'node:os';
import path from 'node:path';

const DEFAULT_LOCAL_API_BASE = 'http://127.0.0.1:8001/api/v1';
const DEFAULT_DASHBOARD_PATH = '/dashboard?source=intranet-client';
const DEFAULT_ROUTER_HOST = '127.0.0.1';
const DEFAULT_ROUTER_PORT = '8877';
const CRM_PROXY_PREFIX = '/__creditsoft/crm';
const CRM_SPA_ROUTE_PREFIXES = ['/objects'];
const CONFIG_DIR = path.join(os.homedir(), '.creditsoft');
const CONFIG_PATH = path.join(CONFIG_DIR, 'intranet-client.json');

const help = `CreditSoft Intranet Client

Usage:
  creditsoft-intranet-client [options]

Options:
  --base <url>       Candidate API base URL. Can be repeated.
  --host <host>      Build an http://host:port/api/v1 candidate.
  --port <port>      Port used with --host. Defaults to 8001.
  --token <token>    Personal CreditSoft API key. Prefer CREDITSOFT_API_TOKEN.
  --pair <value>     Pairing URL or JSON file with candidateBaseUrls.
  --serve            Run a local loopback router/proxy for Chrome/PWA.
  --listen <host>    Router listen host. Defaults to 127.0.0.1.
  --listen-port <n>  Router listen port. Defaults to 8877.
  --crm-base <url>   CRM sidecar origin to proxy under ${CRM_PROXY_PREFIX}.
  --strategy <mode>  Node selection strategy: fastest or ordered. Defaults to fastest.
  --open             Open the detected CreditSoft dashboard. Default.
  --no-open          Do not open a browser/PWA window.
  --save             Save reachable base URL candidates, never the API key.
  --json             Print machine-readable JSON.
  --timeout <ms>     Probe timeout. Defaults to 2500.
  --help             Show this help.
`;

const parseArgs = (argv) => {
    const args = {
        bases: [],
        host: null,
        port: '8001',
        token: process.env.CREDITSOFT_API_TOKEN || '',
        pair: null,
        serve: false,
        listen: DEFAULT_ROUTER_HOST,
        listenPort: DEFAULT_ROUTER_PORT,
        crmBase: process.env.CREDITSOFT_CRM_BASE_URL || '',
        strategy: 'fastest',
        open: true,
        save: false,
        json: false,
        timeout: 2500,
    };

    for (let index = 0; index < argv.length; index += 1) {
        const arg = argv[index];

        if (arg === '--help' || arg === '-h') {
            args.help = true;
        } else if (arg === '--base') {
            args.bases.push(argv[++index] ?? '');
        } else if (arg === '--host') {
            args.host = argv[++index] ?? '';
        } else if (arg === '--port') {
            args.port = argv[++index] ?? '8001';
        } else if (arg === '--token') {
            args.token = argv[++index] ?? '';
        } else if (arg === '--pair') {
            args.pair = argv[++index] ?? '';
        } else if (arg === '--serve') {
            args.serve = true;
        } else if (arg === '--listen') {
            args.listen = argv[++index] ?? DEFAULT_ROUTER_HOST;
        } else if (arg === '--listen-port') {
            args.listenPort = argv[++index] ?? DEFAULT_ROUTER_PORT;
        } else if (arg === '--crm-base') {
            args.crmBase = argv[++index] ?? '';
        } else if (arg === '--strategy') {
            args.strategy = argv[++index] ?? 'fastest';
        } else if (arg === '--open') {
            args.open = true;
        } else if (arg === '--no-open') {
            args.open = false;
        } else if (arg === '--save') {
            args.save = true;
        } else if (arg === '--json') {
            args.json = true;
        } else if (arg === '--timeout') {
            args.timeout = Number.parseInt(argv[++index] ?? '2500', 10);
        } else {
            args.bases.push(arg);
        }
    }

    if (!Number.isFinite(args.timeout) || args.timeout < 500) {
        args.timeout = 2500;
    }

    if (!['fastest', 'ordered'].includes(args.strategy)) {
        args.strategy = 'fastest';
    }

    return args;
};

const normalizeApiBase = (value) => {
    if (typeof value !== 'string' || value.trim() === '') {
        return null;
    }

    let candidate = value.trim();

    if (!candidate.startsWith('http://') && !candidate.startsWith('https://')) {
        candidate = `https://${candidate}`;
    }

    let parsed;
    try {
        parsed = new URL(candidate);
    } catch {
        return null;
    }

    let pathname = parsed.pathname.replace(/\/+$/, '');

    if (pathname === '' || pathname === '/') {
        pathname = '/api/v1';
    } else if (pathname === '/api') {
        pathname = '/api/v1';
    } else if (!pathname.endsWith('/api/v1')) {
        pathname = `${pathname}/api/v1`;
    }

    parsed.pathname = pathname;
    parsed.search = '';
    parsed.hash = '';

    return parsed.toString().replace(/\/$/, '');
};

const originFromApiBase = (apiBase) => {
    const parsed = new URL(apiBase);
    return `${parsed.protocol}//${parsed.host}`;
};

const normalizeOrigin = (value) => {
    if (typeof value !== 'string' || value.trim() === '') {
        return null;
    }

    let candidate = value.trim();

    if (!candidate.startsWith('http://') && !candidate.startsWith('https://')) {
        candidate = `http://${candidate}`;
    }

    try {
        const parsed = new URL(candidate);

        return `${parsed.protocol}//${parsed.host}`;
    } catch {
        return null;
    }
};

const readConfig = () => {
    if (!existsSync(CONFIG_PATH)) {
        return {};
    }

    try {
        const parsed = JSON.parse(readFileSync(CONFIG_PATH, 'utf8'));

        return parsed && typeof parsed === 'object' ? parsed : {};
    } catch {
        return {};
    }
};

const parsePairing = (value) => {
    if (!value) {
        return {};
    }

    if (existsSync(value)) {
        try {
            const parsed = JSON.parse(readFileSync(value, 'utf8'));

            return parsed && typeof parsed === 'object' ? parsed : {};
        } catch {
            return {};
        }
    }

    if (value.startsWith('creditsoft://pair')) {
        try {
            const parsed = new URL(value);
            const base = parsed.searchParams.get('base') || parsed.searchParams.get('api_base_url');
            const token = parsed.searchParams.get('token') || '';
            const officeName = parsed.searchParams.get('name') || '';

            return {
                officeName,
                token,
                candidateBaseUrls: base ? [base] : [],
            };
        } catch {
            return {};
        }
    }

    return {};
};

const unique = (values) => [...new Set(values.filter(Boolean))];

const buildCandidates = (args, config, pairing) => {
    const hostCandidate = args.host
        ? [`http://${args.host.replace(/^https?:\/\//, '')}:${args.port}/api/v1`]
        : [];

    return unique([
        ...args.bases,
        ...hostCandidate,
        process.env.CREDITSOFT_API_BASE_URL,
        ...(Array.isArray(pairing.candidateBaseUrls) ? pairing.candidateBaseUrls : []),
        pairing.api_base_url,
        pairing.base_url,
        config.lastConnectedBaseUrl,
        ...(Array.isArray(config.candidateBaseUrls) ? config.candidateBaseUrls : []),
        DEFAULT_LOCAL_API_BASE,
    ].map(normalizeApiBase));
};

const fetchJson = async (url, options = {}, timeout = 2500) => {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), timeout);
    const startedAt = Date.now();

    try {
        const response = await fetch(url, {
            ...options,
            signal: controller.signal,
            headers: {
                Accept: 'application/json',
                ...(options.headers || {}),
            },
        });
        const bodyText = await response.text();
        let body = null;

        try {
            body = bodyText ? JSON.parse(bodyText) : null;
        } catch {
            body = bodyText;
        }

        return {
            ok: response.ok,
            status: response.status,
            body,
            durationMs: Date.now() - startedAt,
        };
    } finally {
        clearTimeout(timer);
    }
};

const probeBase = async (apiBase, token, timeout) => {
    const startedAt = Date.now();
    const overview = await fetchJson(apiBase, {}, timeout);
    const result = {
        apiBase,
        reachable: overview.ok,
        latencyMs: Date.now() - startedAt,
        overviewLatencyMs: overview.durationMs,
        overviewStatus: overview.status,
        apiName: overview.body?.data?.name || null,
        authenticated: false,
        authStatus: null,
        authLatencyMs: null,
        authMessage: null,
        handshake: null,
    };

    if (!overview.ok || !token) {
        return result;
    }

    const handshake = await fetchJson(`${apiBase}/client/handshake`, {
        headers: {
            Authorization: `Bearer ${token}`,
        },
    }, timeout);

    if (handshake.ok) {
        result.authenticated = true;
        result.authStatus = handshake.status;
        result.authLatencyMs = handshake.durationMs;
        result.latencyMs = Date.now() - startedAt;
        result.handshake = handshake.body?.data || null;

        return result;
    }

    const fallback = await fetchJson(`${apiBase}/office-stats`, {
        headers: {
            Authorization: `Bearer ${token}`,
        },
    }, timeout);

    result.authenticated = fallback.ok;
    result.authStatus = fallback.status;
    result.authLatencyMs = fallback.durationMs;
    result.latencyMs = Date.now() - startedAt;
    result.authMessage = handshake.body?.message || fallback.body?.message || null;

    return result;
};

const selectProbe = (probes, token, strategy = 'fastest') => {
    const connected = probes.filter((probe) => probe.reachable);

    if (connected.length === 0) {
        return null;
    }

    if (strategy === 'ordered') {
        return probes.find((probe) => probe.reachable && (!token || probe.authenticated))
            || probes.find((probe) => probe.reachable)
            || null;
    }

    const preferred = connected.filter((probe) => !token || probe.authenticated);
    const pool = preferred.length > 0 ? preferred : connected;

    return [...pool].sort((left, right) => {
        const leftLatency = Number.isFinite(left.latencyMs) ? left.latencyMs : Number.MAX_SAFE_INTEGER;
        const rightLatency = Number.isFinite(right.latencyMs) ? right.latencyMs : Number.MAX_SAFE_INTEGER;

        return leftLatency - rightLatency;
    })[0] || null;
};

const runCommand = (command, args, timeout = 1500) => new Promise((resolve) => {
    const child = spawn(command, args, {
        stdio: ['ignore', 'pipe', 'pipe'],
        windowsHide: true,
    });
    let stdout = '';
    let stderr = '';
    const timer = setTimeout(() => {
        child.kill();
    }, timeout);

    child.stdout.on('data', (chunk) => {
        stdout += chunk.toString();
    });

    child.stderr.on('data', (chunk) => {
        stderr += chunk.toString();
    });

    child.on('error', (error) => {
        clearTimeout(timer);
        resolve({ ok: false, stdout, stderr: error.message });
    });

    child.on('close', (code) => {
        clearTimeout(timer);
        resolve({ ok: code === 0, stdout, stderr, code });
    });
});

const tailscaleStatus = async () => {
    const status = await runCommand('tailscale', ['status', '--json']);

    if (!status.ok) {
        return {
            installed: false,
            running: false,
            message: 'Tailscale CLI was not available or is not running.',
        };
    }

    let parsed = {};
    try {
        parsed = JSON.parse(status.stdout);
    } catch {
        parsed = {};
    }

    const ip = await runCommand('tailscale', ['ip', '-4']);

    return {
        installed: true,
        running: true,
        self: parsed.Self?.HostName || null,
        dnsName: parsed.Self?.DNSName || null,
        ipv4: ip.ok ? ip.stdout.trim().split(/\s+/)[0] : null,
    };
};

const openUrl = async (url) => {
    if (process.platform === 'darwin') {
        return runCommand('open', [url], 3000);
    }

    if (process.platform === 'win32') {
        return runCommand('cmd', ['/c', 'start', '', url], 3000);
    }

    return runCommand('xdg-open', [url], 3000);
};

const collectRequestBody = (request) => new Promise((resolve, reject) => {
    const chunks = [];

    request.on('data', (chunk) => {
        chunks.push(chunk);
    });

    request.on('end', () => {
        resolve(chunks.length ? Buffer.concat(chunks) : null);
    });

    request.on('error', reject);
});

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

const requestHeaders = (request, token) => {
    const headers = new Headers();

    for (const [name, value] of Object.entries(request.headers)) {
        const normalized = name.toLowerCase();

        if (hopByHopHeaders.has(normalized) || typeof value === 'undefined') {
            continue;
        }

        headers.set(name, Array.isArray(value) ? value.join(', ') : value);
    }

    headers.set('x-creditsoft-client-router', 'node');
    headers.set('x-forwarded-proto', 'http');

    if (request.headers.host) {
        headers.set('x-forwarded-host', request.headers.host);
    }

    if (token && request.url?.startsWith('/api/v1') && !headers.has('authorization')) {
        headers.set('authorization', `Bearer ${token}`);
    }

    return headers;
};

const setCookieHeaders = (response) => {
    if (typeof response.headers.getSetCookie === 'function') {
        return response.headers.getSetCookie();
    }

    const single = response.headers.get('set-cookie');

    return single ? [single] : [];
};

const rewriteCookie = (cookie) => cookie
    .replace(/;\s*Domain=[^;]+/gi, '')
    .replace(/;\s*Secure/gi, '');

const responseHeaders = (response, targetOrigin, routerOrigin) => {
    const headers = {};

    response.headers.forEach((value, name) => {
        const normalized = name.toLowerCase();

        if ([
            'connection',
            'content-encoding',
            'content-length',
            'host',
            'set-cookie',
            'transfer-encoding',
        ].includes(normalized)) {
            return;
        }

        if (normalized === 'location' && value.startsWith(targetOrigin)) {
            headers[name] = value.replace(targetOrigin, routerOrigin);
            return;
        }

        headers[name] = value;
    });

    const cookies = setCookieHeaders(response).map(rewriteCookie);

    if (cookies.length > 0) {
        headers['set-cookie'] = cookies;
    }

    return headers;
};

const rewriteCrmHtml = (html) => {
    const envScript = `<script id="creditsoft-crm-router-env">window._env_=Object.assign({},window._env_||{},{REACT_APP_SERVER_BASE_URL:"${CRM_PROXY_PREFIX}"});if(window.location.pathname.indexOf("${CRM_PROXY_PREFIX}/")===0){window.history.replaceState(null,document.title,"/"+window.location.search+window.location.hash);}</script>`;
    let rewritten = html.includes('creditsoft-crm-router-env')
        ? html
        : html.replace('</head>', `${envScript}</head>`);

    rewritten = rewritten
        .replace(/(href|src)="\/(assets\/[^"]+)"/g, `$1="${CRM_PROXY_PREFIX}/$2"`)
        .replace(/(href|src)="\/(favicon[^"]*)"/g, `$1="${CRM_PROXY_PREFIX}/$2"`)
        .replace(/(href|src)="\/(manifest[^"]*)"/g, `$1="${CRM_PROXY_PREFIX}/$2"`)
        .replace(/(href|src)="\/(apple-touch-icon[^"]*)"/g, `$1="${CRM_PROXY_PREFIX}/$2"`);

    return rewritten;
};

const isCrmSpaRoute = (pathname) => CRM_SPA_ROUTE_PREFIXES.some((prefix) => (
    pathname === prefix || pathname.startsWith(`${prefix}/`)
));

const proxyRequest = async ({ request, response, targetOrigin, routerOrigin, token }) => {
    const targetUrl = new URL(request.url || '/', targetOrigin);
    const method = request.method || 'GET';
    const body = ['GET', 'HEAD'].includes(method) ? undefined : await collectRequestBody(request);
    const upstream = await fetch(targetUrl, {
        method,
        headers: requestHeaders(request, token),
        body,
        redirect: 'manual',
    });
    const payload = Buffer.from(await upstream.arrayBuffer());

    response.writeHead(upstream.status, upstream.statusText, responseHeaders(upstream, targetOrigin, routerOrigin));
    response.end(payload);
};

const proxyCrmRequest = async ({ request, response, crmOrigin, routerOrigin }) => {
    const requestUrl = new URL(request.url || '/', `http://${request.headers.host || '127.0.0.1'}`);
    const proxiedPath = requestUrl.pathname.startsWith(`${CRM_PROXY_PREFIX}/`) || requestUrl.pathname === CRM_PROXY_PREFIX
        ? (requestUrl.pathname === CRM_PROXY_PREFIX
        ? '/'
        : requestUrl.pathname.slice(CRM_PROXY_PREFIX.length) || '/')
        : requestUrl.pathname;
    const targetUrl = new URL(`${proxiedPath}${requestUrl.search}`, crmOrigin);
    const method = request.method || 'GET';
    const body = ['GET', 'HEAD'].includes(method) ? undefined : await collectRequestBody(request);
    const upstream = await fetch(targetUrl, {
        method,
        headers: requestHeaders(request, ''),
        body,
        redirect: 'manual',
    });
    const headers = responseHeaders(upstream, crmOrigin, `${routerOrigin}${CRM_PROXY_PREFIX}`);
    const contentType = upstream.headers.get('content-type') || '';
    let payload = Buffer.from(await upstream.arrayBuffer());

    if (contentType.includes('text/html')) {
        payload = Buffer.from(rewriteCrmHtml(payload.toString('utf8')));
    }

    response.writeHead(upstream.status, upstream.statusText, headers);
    response.end(payload);
};

const startLocalRouter = ({ selected, token, listen, listenPort, dashboardPath, crmOrigin }) => new Promise((resolve, reject) => {
    const targetOrigin = originFromApiBase(selected.apiBase);
    const server = createServer((request, response) => {
        const requestUrl = new URL(request.url || '/', `http://${request.headers.host || `${listen}:${listenPort}`}`);
        const isCrmProxyRequest = crmOrigin && (
            requestUrl.pathname === CRM_PROXY_PREFIX
            || requestUrl.pathname.startsWith(`${CRM_PROXY_PREFIX}/`)
            || requestUrl.pathname.startsWith('/assets/')
            || isCrmSpaRoute(requestUrl.pathname)
        );

        if (isCrmProxyRequest) {
            proxyCrmRequest({
                request,
                response,
                crmOrigin,
                routerOrigin: `http://${listen}:${listenPort}`,
            }).catch((error) => {
                response.writeHead(502, { 'content-type': 'application/json; charset=utf-8' });
                response.end(JSON.stringify({
                    message: 'CreditSoft local router could not reach the CRM sidecar.',
                    crmOrigin,
                    error: error instanceof Error ? error.message : String(error),
                }, null, 2));
            });
            return;
        }

        if (requestUrl.pathname === '/__creditsoft/client/status') {
            response.writeHead(200, { 'content-type': 'application/json; charset=utf-8' });
            response.end(JSON.stringify({
                status: 'ok',
                targetOrigin,
                apiBase: selected.apiBase,
                latencyMs: selected.latencyMs ?? null,
                authenticated: selected.authenticated,
                tokenProvided: Boolean(token),
                crmOrigin,
                crmProxyPath: crmOrigin ? CRM_PROXY_PREFIX : null,
            }, null, 2));
            return;
        }

        proxyRequest({
            request,
            response,
            targetOrigin,
            routerOrigin: `http://${listen}:${listenPort}`,
            token,
        }).catch((error) => {
            response.writeHead(502, { 'content-type': 'application/json; charset=utf-8' });
            response.end(JSON.stringify({
                message: 'CreditSoft local router could not reach the office server.',
                targetOrigin,
                error: error instanceof Error ? error.message : String(error),
            }, null, 2));
        });
    });

    server.on('error', reject);
    server.listen(Number.parseInt(listenPort, 10), listen, () => {
        resolve({
            server,
            origin: `http://${listen}:${listenPort}`,
            dashboardUrl: `http://${listen}:${listenPort}${dashboardPath}`,
            targetOrigin,
            crmOrigin,
        });
    });
});

const saveConfig = (config) => {
    mkdirSync(CONFIG_DIR, { recursive: true });
    writeFileSync(CONFIG_PATH, `${JSON.stringify(config, null, 2)}\n`, { mode: 0o600 });
};

const main = async () => {
    const args = parseArgs(process.argv.slice(2));

    if (args.help) {
        console.log(help);
        return 0;
    }

    const config = readConfig();
    const pairing = parsePairing(args.pair);
    const token = args.token || pairing.token || '';
    const strategy = ['fastest', 'ordered'].includes(pairing.selectionStrategy)
        ? pairing.selectionStrategy
        : args.strategy;
    const candidates = buildCandidates(args, config, pairing);
    const tailnet = await tailscaleStatus();
    const probes = await Promise.all(candidates.map(async (apiBase) => {
        try {
            const probe = await probeBase(apiBase, token, args.timeout);

            return probe;
        } catch (error) {
            return {
                apiBase,
                reachable: false,
                latencyMs: null,
                error: error instanceof Error ? error.message : String(error),
            };
        }
    }));

    const selected = selectProbe(probes, token, strategy);
    const crmOrigin = normalizeOrigin(args.crmBase || pairing.crmBase || pairing.crm_base_url || config.crmBase || config.crmOrigin);

    const dashboardPath = pairing.dashboardPath || config.dashboardPath || DEFAULT_DASHBOARD_PATH;
    const dashboardUrl = selected ? `${originFromApiBase(selected.apiBase)}${dashboardPath}` : null;
    const router = selected && args.serve
        ? await startLocalRouter({
            selected,
            token,
            listen: args.listen,
            listenPort: args.listenPort,
            dashboardPath,
            crmOrigin,
        })
        : null;
    const launchUrl = router?.dashboardUrl || dashboardUrl;
    let opened = false;

    if (selected && launchUrl && args.open) {
        const openResult = await openUrl(launchUrl);
        opened = openResult.ok;
    }

    if (args.save && selected) {
        saveConfig({
            officeName: pairing.officeName || config.officeName || 'CreditSoft Office',
            lastConnectedBaseUrl: selected.apiBase,
            candidateBaseUrls: unique([selected.apiBase, ...candidates]),
            crmOrigin,
            dashboardPath,
            updatedAt: new Date().toISOString(),
        });
    }

    const result = {
        ok: Boolean(selected?.reachable),
        authenticated: Boolean(selected?.authenticated),
        selectedApiBase: selected?.apiBase || null,
        dashboardUrl,
        launchUrl,
        router: router ? {
            origin: router.origin,
            dashboardUrl: router.dashboardUrl,
            statusUrl: `${router.origin}/__creditsoft/client/status`,
            targetOrigin: router.targetOrigin,
            crmOrigin: router.crmOrigin,
            crmProxyPath: router.crmOrigin ? CRM_PROXY_PREFIX : null,
        } : null,
        strategy,
        opened,
        tokenProvided: Boolean(token),
        tailscale: tailnet,
        probes,
        configPath: CONFIG_PATH,
    };

    if (args.json) {
        console.log(JSON.stringify(result, null, 2));
    } else {
        console.log('CreditSoft Intranet Client');
        console.log(`Tailscale: ${tailnet.running ? `running (${tailnet.dnsName || tailnet.ipv4 || 'connected'})` : tailnet.message}`);
        console.log(`Selection: ${strategy}`);
        console.log(`API: ${result.selectedApiBase || 'no reachable API found'}${selected?.latencyMs ? ` (${selected.latencyMs}ms)` : ''}`);
        console.log(`Authenticated: ${result.authenticated ? 'yes' : (result.tokenProvided ? 'no' : 'no token supplied')}`);

        if (router) {
            console.log(`Local router: ${router.origin} -> ${router.targetOrigin}`);
            if (router.crmOrigin) {
                console.log(`CRM proxy: ${router.origin}${CRM_PROXY_PREFIX} -> ${router.crmOrigin}`);
            }
        }

        if (launchUrl) {
            console.log(`Dashboard: ${launchUrl}${opened ? ' (opened)' : ''}`);
        }

        if (!result.ok) {
            console.log('No candidate API lane answered. Pass --base with the server machine URL or connect Tailscale first.');
        } else if (result.tokenProvided && !result.authenticated) {
            console.log('The API is reachable, but the personal API key was not accepted by /office-stats.');
        }
    }

    if (!result.ok) {
        return 2;
    }

    if (result.tokenProvided && !result.authenticated) {
        return 3;
    }

    return 0;
};

main()
    .then((code) => {
        process.exitCode = code;
    })
    .catch((error) => {
        console.error(error instanceof Error ? error.message : String(error));
        process.exitCode = 1;
    });
