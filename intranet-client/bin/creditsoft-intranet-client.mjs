#!/usr/bin/env node

import { spawn } from 'node:child_process';
import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { createServer } from 'node:http';
import os from 'node:os';
import path from 'node:path';
import readline from 'node:readline';

const DEFAULT_LOCAL_API_BASE = 'http://127.0.0.1:8001/api/v1';
const DEFAULT_DASHBOARD_PATH = '/dashboard?source=intranet-client';
const DEFAULT_ROUTER_HOST = '127.0.0.1';
const DEFAULT_ROUTER_PORT = '8877';
const DEFAULT_TAILSCALE_PORTS = ['80', '8001', '8000', '8877', '443'];
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
  --prompt-token     Ask for a personal API key when one was not provided. Default.
  --no-prompt-token  Do not prompt for a personal API key.
  --pair <value>     Pairing URL or JSON file with candidateBaseUrls.
  --serve            Run a local loopback router/proxy for Chrome/PWA.
  --listen <host>    Router listen host. Defaults to 127.0.0.1.
  --listen-port <n>  Router listen port. Defaults to 8877.
  --strategy <mode>  Node selection strategy: resource-aware, fastest, or ordered. Defaults to resource-aware.
  --tailscale-discover
                     Probe Tailscale/MagicDNS peers. Default.
  --no-tailscale-discover
                     Do not add Tailscale/MagicDNS peer candidates.
  --tailscale-port <port>
                     Extra port to probe on Tailscale peers. Can be repeated.
  --tailscale-ports <csv>
                     Tailscale probe ports. Defaults to 8001,8000,8877,443,80.
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
        promptToken: process.env.CREDITSOFT_PROMPT_TOKEN !== 'false',
        pair: null,
        serve: false,
        listen: DEFAULT_ROUTER_HOST,
        listenPort: DEFAULT_ROUTER_PORT,
        strategy: normalizeStrategy(process.env.CREDITSOFT_ROUTER_SELECTION_STRATEGY || 'resource-aware'),
        tailscaleDiscover: process.env.CREDITSOFT_TAILSCALE_DISCOVERY !== 'false',
        tailscalePorts: normalizePortList(process.env.CREDITSOFT_TAILSCALE_PORTS || DEFAULT_TAILSCALE_PORTS.join(',')),
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
        } else if (arg === '--prompt-token') {
            args.promptToken = true;
        } else if (arg === '--no-prompt-token') {
            args.promptToken = false;
        } else if (arg === '--pair') {
            args.pair = argv[++index] ?? '';
        } else if (arg === '--serve') {
            args.serve = true;
        } else if (arg === '--listen') {
            args.listen = argv[++index] ?? DEFAULT_ROUTER_HOST;
        } else if (arg === '--listen-port') {
            args.listenPort = argv[++index] ?? DEFAULT_ROUTER_PORT;
        } else if (arg === '--strategy') {
            args.strategy = argv[++index] ?? 'fastest';
        } else if (arg === '--tailscale-discover') {
            args.tailscaleDiscover = true;
        } else if (arg === '--no-tailscale-discover') {
            args.tailscaleDiscover = false;
        } else if (arg === '--tailscale-port') {
            args.tailscalePorts.push(argv[++index] ?? '');
        } else if (arg === '--tailscale-ports') {
            args.tailscalePorts = normalizePortList(argv[++index] ?? '');
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

    args.strategy = normalizeStrategy(args.strategy);

    args.tailscalePorts = normalizePortList(args.tailscalePorts);

    return args;
};

function normalizePortList(value) {
    const entries = Array.isArray(value)
        ? value
        : String(value || '').split(',');

    return unique(entries
        .map((entry) => String(entry).trim())
        .filter((entry) => /^\d+$/.test(entry))
        .filter((entry) => Number.parseInt(entry, 10) > 0 && Number.parseInt(entry, 10) <= 65535));
}

function normalizeStrategy(value) {
    return ['resource-aware', 'fastest', 'ordered'].includes(value)
        ? value
        : 'resource-aware';
}

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

const stripTrailingDot = (value) => String(value || '').trim().replace(/\.$/, '');

const urlHost = (host) => {
    const normalized = stripTrailingDot(host);

    return normalized.includes(':') && !normalized.startsWith('[')
        ? `[${normalized}]`
        : normalized;
};

const tailscaleApiBaseFor = (host, port) => {
    const normalizedPort = String(port || '').trim();
    const scheme = normalizedPort === '443' ? 'https' : 'http';
    const hostForUrl = urlHost(host);
    const portSuffix = (scheme === 'https' && normalizedPort === '443')
        || (scheme === 'http' && normalizedPort === '80')
        ? ''
        : `:${normalizedPort}`;

    return `${scheme}://${hostForUrl}${portSuffix}/api/v1`;
};

const tailscalePeerHosts = (peer) => unique([
    stripTrailingDot(peer?.dnsName),
    stripTrailingDot(peer?.hostName),
    ...(Array.isArray(peer?.tailscaleIPs) ? peer.tailscaleIPs : []),
]);

const tailscaleCandidateBases = (tailnet, ports) => {
    if (!tailnet?.running || !Array.isArray(tailnet.peers)) {
        return [];
    }

    return tailnet.peers
        .filter((peer) => peer.online || peer.active)
        .flatMap((peer) => tailscalePeerHosts(peer)
            .flatMap((host) => ports.map((port) => tailscaleApiBaseFor(host, port))));
};

const buildCandidates = (args, config, pairing, tailnet) => {
    const hostCandidate = args.host
        ? [`http://${args.host.replace(/^https?:\/\//, '')}:${args.port}/api/v1`]
        : [];
    const tailnetCandidates = args.tailscaleDiscover
        ? tailscaleCandidateBases(tailnet, args.tailscalePorts)
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
        ...tailnetCandidates,
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
        overviewRouter: overview.body?.data?.router || null,
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

    if (strategy === 'resource-aware') {
        return [...pool].sort((left, right) => probeScore(left, token) - probeScore(right, token))[0] || null;
    }

    return [...pool].sort((left, right) => {
        const leftLatency = Number.isFinite(left.latencyMs) ? left.latencyMs : Number.MAX_SAFE_INTEGER;
        const rightLatency = Number.isFinite(right.latencyMs) ? right.latencyMs : Number.MAX_SAFE_INTEGER;

        return leftLatency - rightLatency;
    })[0] || null;
};

const probeRouterHint = (probe) => probe.handshake?.router || probe.overviewRouter || {};

const probeMatchesPreferredBase = (probe) => {
    const hint = probeRouterHint(probe);
    const preferredBase = hint.preferred_api_base_url || hint.preferred_base_url || '';
    const normalized = normalizeApiBase(preferredBase);

    return Boolean(normalized && normalized === probe.apiBase);
};

const probeScore = (probe, token) => {
    const latency = Number.isFinite(probe.latencyMs) ? probe.latencyMs : 100000;
    const hint = probeRouterHint(probe);
    const health = hint.node_health || {};
    const memoryPenalty = memoryPressurePenalty(health);
    const swapPenalty = Number.isFinite(health.swap_used_percent)
        ? Number(health.swap_used_percent) * 45
        : 0;
    const cpuCores = Math.max(Number(health.cpu_cores || 1), 1);
    const loadPenalty = Number.isFinite(health.load_one)
        ? (Number(health.load_one) / cpuCores) * 200
        : 0;
    const authPenalty = token && !probe.authenticated ? 50000 : 0;
    const preferredBonus = probeMatchesPreferredBase(probe) ? -25000 : 0;

    return latency + memoryPenalty + swapPenalty + loadPenalty + authPenalty + preferredBonus;
};

const memoryPressurePenalty = (health) => {
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

const parseTailscalePeers = (status) => Object.values(status?.Peer || {})
    .map((peer) => ({
        id: peer.ID || null,
        hostName: peer.HostName || null,
        dnsName: stripTrailingDot(peer.DNSName || ''),
        tailscaleIPs: Array.isArray(peer.TailscaleIPs) ? peer.TailscaleIPs : [],
        online: Boolean(peer.Online),
        active: Boolean(peer.Active),
        os: peer.OS || null,
        lastSeen: peer.LastSeen || null,
    }))
    .filter((peer) => tailscalePeerHosts(peer).length > 0);

const pingTailscalePeers = async (peers) => Promise.all(peers.map(async (peer) => {
    const target = stripTrailingDot(peer.dnsName) || peer.tailscaleIPs[0] || stripTrailingDot(peer.hostName);

    if (!target) {
        return {
            ...peer,
            pingable: false,
            pingMessage: 'No Tailscale hostname or IP was available.',
        };
    }

    const ping = await runCommand('tailscale', ['ping', '--c', '1', '--timeout', '1s', target], 2000);

    return {
        ...peer,
        pingTarget: target,
        pingable: ping.ok,
        pingMessage: (ping.stdout || ping.stderr).trim().split('\n').at(-1) || null,
    };
}));

const tailscaleStatus = async () => {
    const status = await runCommand('tailscale', ['status', '--json'], 3000);

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
    const peers = await pingTailscalePeers(parseTailscalePeers(parsed));

    return {
        installed: true,
        running: true,
        self: parsed.Self?.HostName || null,
        dnsName: stripTrailingDot(parsed.Self?.DNSName || ''),
        ipv4: ip.ok ? ip.stdout.trim().split(/\s+/)[0] : null,
        peers,
        onlinePeers: peers.filter((peer) => peer.online).length,
        pingablePeers: peers.filter((peer) => peer.pingable).length,
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

const requestAcceptsHtml = (request) => {
    const accept = String(request.headers.accept || '');

    return accept.includes('text/html') || accept.includes('application/xhtml+xml');
};

const requestWantsJson = (request) => {
    const accept = String(request.headers.accept || '');

    return accept.includes('application/json')
        || accept.includes('text/json')
        || String(request.url || '').startsWith('/api/');
};

const localRequestUrl = (request, listen, listenPort) => new URL(
    request.url || '/',
    `http://${request.headers.host || `${listen}:${listenPort}`}`,
);

const escapeHtml = (value) => String(value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;');

const routerOriginFor = (listen, listenPort) => {
    const port = String(listenPort || '').trim();

    return port === '80'
        ? `http://${listen}`
        : `http://${listen}:${port}`;
};

const offlineHtml = ({ officeName, targetOrigin, errorMessage }) => {
    const escapedOfficeName = escapeHtml(officeName || 'CreditSoft Office');

    return `<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>${escapedOfficeName} unavailable</title>
  <style>
    :root { color-scheme: light; font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
    body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #f7f3ea; color: #1c1917; }
    main { width: min(92vw, 640px); border: 1px solid #e7d8bd; background: #fffaf0; padding: 28px; box-shadow: 0 24px 60px rgba(120, 113, 108, 0.18); }
    .eyebrow { margin: 0 0 8px; color: #a16207; font-size: 12px; font-weight: 800; letter-spacing: .18em; text-transform: uppercase; }
    h1 { margin: 0 0 12px; font-size: 24px; line-height: 1.2; }
    p { margin: 0 0 14px; color: #57534e; line-height: 1.6; }
    code { display: block; white-space: pre-wrap; word-break: break-word; border: 1px solid #eadfcb; background: #fff; padding: 12px; color: #44403c; }
    a, button { appearance: none; border: 0; background: #eab308; color: #1c1917; font-weight: 700; padding: 11px 14px; text-decoration: none; cursor: pointer; }
    .actions { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 18px; }
    .hint { border-top: 1px solid #eadfcb; margin-top: 18px; padding-top: 16px; font-size: 14px; }
  </style>
</head>
<body>
  <main>
    <p class="eyebrow">${escapedOfficeName}</p>
    <h1>Your local office app is temporarily unavailable.</h1>
    <p>The local router is running, but it could not reach the selected office server node.</p>
    <code>Target: ${escapeHtml(targetOrigin)}\nError: ${escapeHtml(errorMessage)}</code>
    <div class="actions">
      <button type="button" onclick="window.location.reload()">Reconnect</button>
      <a href="/__creditsoft/client/status">Router status</a>
    </div>
    <p class="hint">When this workstation connects, press <strong>Command-D</strong> on Mac or <strong>Ctrl-D</strong> on Windows/Linux to bookmark this office. If Chrome or Edge shows an install icon, use it to keep the CreditSoft PWA in the dock or taskbar for this business profile.</p>
  </main>
</body>
</html>`;
};

const sendProxyFailure = ({ request, response, targetOrigin, listen, listenPort, error, officeName }) => {
    const requestUrl = localRequestUrl(request, listen, listenPort);
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
        response.end(offlineHtml({ officeName, targetOrigin, errorMessage }));
        return;
    }

    response.writeHead(502, { 'content-type': 'application/json; charset=utf-8' });
    response.end(JSON.stringify({
        message: 'CreditSoft local router could not reach the office server.',
        targetOrigin,
        error: errorMessage,
    }, null, 2));
};

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

const startLocalRouter = ({ selected, token, listen, listenPort, dashboardPath, officeName }) => new Promise((resolve, reject) => {
    const targetOrigin = originFromApiBase(selected.apiBase);
    const routerOrigin = routerOriginFor(listen, listenPort);
    const server = createServer((request, response) => {
        const requestUrl = localRequestUrl(request, listen, listenPort);

        if (requestUrl.pathname === '/__creditsoft/client/status') {
            response.writeHead(200, { 'content-type': 'application/json; charset=utf-8' });
            response.end(JSON.stringify({
                status: 'ok',
                targetOrigin,
                apiBase: selected.apiBase,
                latencyMs: selected.latencyMs ?? null,
                authenticated: selected.authenticated,
                tokenProvided: Boolean(token),
            }, null, 2));
            return;
        }

        proxyRequest({
            request,
            response,
            targetOrigin,
            routerOrigin,
            token,
        }).catch((error) => sendProxyFailure({
            request,
            response,
            targetOrigin,
            listen,
            listenPort,
            error,
            officeName,
        }));
    });

    server.on('error', reject);
    server.listen(Number.parseInt(listenPort, 10), listen, () => {
        resolve({
            server,
            origin: routerOrigin,
            dashboardUrl: `${routerOrigin}${dashboardPath}`,
            targetOrigin,
        });
    });
});

const startWaitingRouter = ({ token, listen, listenPort, dashboardPath, officeName, candidates, probes }) => new Promise((resolve, reject) => {
    const routerOrigin = routerOriginFor(listen, listenPort);
    const errorMessage = candidates.length > 0
        ? 'No configured, paired, local, or Tailscale office API lane answered yet.'
        : 'No office API lanes are configured yet.';
    const server = createServer((request, response) => {
        const requestUrl = localRequestUrl(request, listen, listenPort);

        if (requestUrl.pathname === '/__creditsoft/client/status') {
            response.writeHead(503, { 'content-type': 'application/json; charset=utf-8' });
            response.end(JSON.stringify({
                status: 'waiting',
                message: errorMessage,
                tokenProvided: Boolean(token),
                candidateCount: candidates.length,
                probes,
            }, null, 2));
            return;
        }

        if (requestAcceptsHtml(request) && !requestWantsJson(request)) {
            response.writeHead(503, {
                'content-type': 'text/html; charset=utf-8',
                'cache-control': 'no-store',
            });
            response.end(offlineHtml({
                officeName,
                targetOrigin: 'No office server selected',
                errorMessage,
            }));
            return;
        }

        response.writeHead(503, { 'content-type': 'application/json; charset=utf-8' });
        response.end(JSON.stringify({
            message: 'CreditSoft local router is waiting for an office server.',
            error: errorMessage,
        }, null, 2));
    });

    server.on('error', reject);
    server.listen(Number.parseInt(listenPort, 10), listen, () => {
        resolve({
            server,
            origin: routerOrigin,
            dashboardUrl: `${routerOrigin}${dashboardPath}`,
            targetOrigin: null,
        });
    });
});

const saveConfig = (config) => {
    mkdirSync(CONFIG_DIR, { recursive: true });
    writeFileSync(CONFIG_PATH, `${JSON.stringify(config, null, 2)}\n`, { mode: 0o600 });
};

const shouldPromptForToken = (args, token) => args.promptToken
    && !token
    && !args.json
    && (args.serve || args.open)
    && process.stdin.isTTY
    && process.stdout.isTTY;

const promptForToken = async () => new Promise((resolve, reject) => {
    const wasRaw = process.stdin.isRaw;
    let token = '';

    const cleanup = () => {
        process.stdin.off('keypress', onKeypress);

        if (process.stdin.isTTY) {
            process.stdin.setRawMode(Boolean(wasRaw));
        }

        process.stdout.write('\n');
    };

    const onKeypress = (character, key = {}) => {
        if (key.ctrl && key.name === 'c') {
            cleanup();
            reject(new Error('API key prompt cancelled.'));
            return;
        }

        if (key.name === 'return' || key.name === 'enter') {
            cleanup();
            resolve(token.trim());
            return;
        }

        if (key.name === 'backspace' || key.name === 'delete') {
            token = token.slice(0, -1);
            return;
        }

        if (typeof character === 'string' && character.length === 1 && !key.ctrl && !key.meta) {
            token += character;
        }
    };

    readline.emitKeypressEvents(process.stdin);
    process.stdout.write('Personal CreditSoft API key (not saved; press Enter to skip): ');
    process.stdin.on('keypress', onKeypress);
    process.stdin.setRawMode(true);
    process.stdin.resume();
});

const main = async () => {
    const args = parseArgs(process.argv.slice(2));

    if (args.help) {
        console.log(help);
        return 0;
    }

    const config = readConfig();
    const pairing = parsePairing(args.pair);
    const providedToken = args.token || pairing.token || '';
    const token = shouldPromptForToken(args, providedToken)
        ? await promptForToken()
        : providedToken;
    const strategy = normalizeStrategy(pairing.selectionStrategy || args.strategy);
    const tailnet = await tailscaleStatus();
    const candidates = buildCandidates(args, config, pairing, tailnet);
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

    const dashboardPath = pairing.dashboardPath || config.dashboardPath || DEFAULT_DASHBOARD_PATH;
    const officeName = pairing.officeName || config.officeName || 'CreditSoft Office';
    const dashboardUrl = selected ? `${originFromApiBase(selected.apiBase)}${dashboardPath}` : null;
    const router = args.serve
        ? (selected
            ? await startLocalRouter({
            selected,
            token,
            listen: args.listen,
            listenPort: args.listenPort,
            dashboardPath,
            officeName,
            })
            : await startWaitingRouter({
                token,
                listen: args.listen,
                listenPort: args.listenPort,
                dashboardPath,
                officeName,
                candidates,
                probes,
            }))
        : null;
    const launchUrl = router?.dashboardUrl || dashboardUrl;
    let opened = false;

    if (selected && launchUrl && args.open) {
        const openResult = await openUrl(launchUrl);
        opened = openResult.ok;
    }

    if (args.save && selected) {
        saveConfig({
            officeName,
            lastConnectedBaseUrl: selected.apiBase,
            candidateBaseUrls: unique([selected.apiBase, ...candidates]),
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
        } : null,
        strategy,
        opened,
        tokenProvided: Boolean(token),
        tailscale: tailnet,
        candidateCount: candidates.length,
        tailscaleCandidateCount: args.tailscaleDiscover
            ? tailscaleCandidateBases(tailnet, args.tailscalePorts).length
            : 0,
        tailscalePorts: args.tailscalePorts,
        probes,
        configPath: CONFIG_PATH,
    };

    if (args.json) {
        console.log(JSON.stringify(result, null, 2));
    } else {
        console.log('CreditSoft Intranet Client');
        console.log(`Tailscale: ${tailnet.running ? `running (${tailnet.dnsName || tailnet.ipv4 || 'connected'}; ${tailnet.onlinePeers ?? 0} online peers, ${tailnet.pingablePeers ?? 0} pingable)` : tailnet.message}`);
        console.log(`Candidates: ${result.candidateCount} total${result.tailscaleCandidateCount ? `, ${result.tailscaleCandidateCount} from Tailscale ports ${result.tailscalePorts.join(',')}` : ''}`);
        console.log(`Selection: ${strategy}`);
        console.log(`API: ${result.selectedApiBase || 'no reachable API found'}${selected?.latencyMs ? ` (${selected.latencyMs}ms)` : ''}`);
        console.log(`Authenticated: ${result.authenticated ? 'yes' : (result.tokenProvided ? 'no' : 'no token supplied')}`);

        if (router) {
            console.log(`Local router: ${router.origin} -> ${router.targetOrigin}`);
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
