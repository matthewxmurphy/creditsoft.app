#!/usr/bin/env node

import { spawn } from 'node:child_process';
import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { createServer } from 'node:http';
import os from 'node:os';
import path from 'node:path';

const DEFAULT_LOCAL_API_BASE = 'http://127.0.0.1:8001/api/v1';
const CLIENT_VERSION = '2026.5.6.4';
const DEFAULT_DASHBOARD_PATH = '/dashboard?source=intranet-client';
const DEFAULT_ROUTER_HOST = '127.0.0.1';
const DEFAULT_ROUTER_PORT = '8877';
const CRM_PROXY_PREFIX = '/__creditsoft/crm';
const CRM_SPA_ROUTE_PREFIXES = ['/objects', '/settings/admin-panel'];
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

const isLoopbackApiBase = (apiBase) => {
    try {
        const parsed = new URL(apiBase);
        const host = parsed.hostname.toLowerCase();

        return host === 'localhost' || host === '127.0.0.1' || host === '::1';
    } catch {
        return false;
    }
};

const splitApiBases = (value) => {
    if (typeof value !== 'string') {
        return [];
    }

    return value
        .split(/[\n,;]+/)
        .map((candidate) => candidate.trim())
        .filter(Boolean);
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

    const configuredCandidates = unique([
        ...args.bases,
        ...hostCandidate,
        ...splitApiBases(process.env.CREDITSOFT_API_BASES),
        process.env.CREDITSOFT_API_BASE_URL,
        ...(Array.isArray(pairing.candidateBaseUrls) ? pairing.candidateBaseUrls : []),
        pairing.api_base_url,
        pairing.base_url,
    ].map(normalizeApiBase));

    const savedCandidates = unique([
        config.lastConnectedBaseUrl,
        ...(Array.isArray(config.candidateBaseUrls) ? config.candidateBaseUrls : []),
    ].map(normalizeApiBase));

    const candidates = unique([...configuredCandidates, ...savedCandidates]);
    const hasRemoteCandidate = candidates.some((candidate) => !isLoopbackApiBase(candidate));
    const usableCandidates = hasRemoteCandidate
        ? candidates.filter((candidate) => !isLoopbackApiBase(candidate))
        : candidates;

    return usableCandidates.length > 0 ? usableCandidates : [DEFAULT_LOCAL_API_BASE];
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
                'ngrok-skip-browser-warning': 'true',
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
    headers.set('ngrok-skip-browser-warning', 'true');
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
    const adminBridgeScript = `<script id="creditsoft-crm-admin-bridge">
(() => {
  const panelId = 'creditsoft-crm-admin-bridge-panel';
  const styleId = 'creditsoft-crm-admin-bridge-style';
  const config = {
    apiWebhooksUrl: '/settings/api-webhooks',
    aiSettingsUrl: '/settings/ai',
    publicDocsUrl: 'https://www.creditsoft.app/resources#/user-guide/introduction',
    aiEndpoint: '/internal/ai/chat',
    apiBase: '/api/v1',
    webhooks: [
      ['POST', '/api/v1/clients', 'Website lead intake', 'Creates leads or clients from public forms and partner funnels.'],
      ['POST', '/api/v1/clients/{cuid}/documents', 'Client portal documents', 'Uploads IDs, proof of address, agreements, and portal files.'],
      ['POST', '/api/v1/browser-companion/intake', 'Browser companion captures', 'Receives SmartCredit, IdentityIQ, DisputeFox, and document captures.'],
      ['POST', '/api/v1/cluster-actions/apply', 'Multi-office action sync', 'Queues deletes, archives, status updates, and peer-node actions.'],
      ['POST', '/api/v1/cluster-db-events/receive', 'Replica event intake', 'Accepts queued database events when another office node returns.'],
      ['GET', '/api/v1/meta/callback', 'Meta callback', 'Completes Facebook, Instagram, and social publishing callbacks.'],
    ],
  };
  const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));
  const text = () => document.body?.innerText || '';
  const shouldRender = () => window.location.pathname.includes('/settings/admin-panel') || /APIs & Webhooks|API Keys|Webhooks|\\bAI\\b/i.test(text());
  const style = () => {
    if (document.getElementById(styleId)) return;
    const node = document.createElement('style');
    node.id = styleId;
    node.textContent = '.creditsoft-crm-admin-bridge-panel{width:min(1180px,calc(100% - 24px));margin:16px auto 24px;border:1px solid rgba(241,194,122,.38);border-radius:14px;background:linear-gradient(135deg,rgba(13,13,18,.97),rgba(31,28,22,.97));box-shadow:0 20px 52px rgba(0,0,0,.24);color:#f8fafc;font-family:Inter,ui-sans-serif,system-ui,sans-serif;padding:20px}.creditsoft-crm-admin-bridge-panel *{box-sizing:border-box}.creditsoft-crm-admin-bridge-head{display:flex;align-items:flex-start;justify-content:space-between;gap:18px;margin-bottom:18px}.creditsoft-crm-admin-bridge-panel h2{margin:0;color:#fff7ed;font-size:22px;line-height:1.2;letter-spacing:0}.creditsoft-crm-admin-bridge-panel p{margin:7px 0 0;color:rgba(255,255,255,.74);font-size:14px;line-height:1.55}.creditsoft-crm-admin-bridge-actions{display:flex;flex-wrap:wrap;gap:10px;justify-content:flex-end}.creditsoft-crm-admin-bridge-actions a{display:inline-flex;align-items:center;min-height:38px;border-radius:9px;padding:0 13px;background:rgba(241,194,122,.14);border:1px solid rgba(241,194,122,.32);color:#fff7ed!important;text-decoration:none;font-size:13px;font-weight:700}.creditsoft-crm-admin-bridge-grid{display:grid;grid-template-columns:minmax(0,1.35fr) minmax(280px,.65fr);gap:16px}.creditsoft-crm-admin-bridge-list,.creditsoft-crm-admin-bridge-ai{border:1px solid rgba(255,255,255,.09);border-radius:12px;background:rgba(255,255,255,.045);padding:14px}.creditsoft-crm-admin-bridge-row{display:grid;grid-template-columns:74px minmax(185px,.8fr) minmax(240px,1fr);gap:12px;padding:12px 0;border-top:1px solid rgba(255,255,255,.08)}.creditsoft-crm-admin-bridge-row:first-child{border-top:0;padding-top:0}.creditsoft-crm-admin-bridge-method,.creditsoft-crm-admin-bridge-code{color:#fde68a;font-family:SFMono-Regular,Consolas,monospace;font-size:12px}.creditsoft-crm-admin-bridge-code{overflow-wrap:anywhere}.creditsoft-crm-admin-bridge-label{color:#fff;font-size:13px;font-weight:800}.creditsoft-crm-admin-bridge-note{color:rgba(255,255,255,.7);font-size:12px;line-height:1.45}.creditsoft-crm-admin-bridge-ai dl{display:grid;gap:10px;margin:14px 0 0}.creditsoft-crm-admin-bridge-ai dt{color:rgba(255,255,255,.58);font-size:11px;font-weight:800;letter-spacing:.12em;text-transform:uppercase}.creditsoft-crm-admin-bridge-ai dd{margin:3px 0 0;color:#fff;font-size:13px;overflow-wrap:anywhere}@media(max-width:980px){.creditsoft-crm-admin-bridge-head,.creditsoft-crm-admin-bridge-grid,.creditsoft-crm-admin-bridge-row{display:grid;grid-template-columns:1fr}.creditsoft-crm-admin-bridge-actions{justify-content:flex-start}}';
    document.head.appendChild(node);
  };
  const rows = () => config.webhooks.map(([method, path, label, note]) => '<div class="creditsoft-crm-admin-bridge-row"><span class="creditsoft-crm-admin-bridge-method">'+esc(method)+'</span><div><div class="creditsoft-crm-admin-bridge-label">'+esc(label)+'</div><div class="creditsoft-crm-admin-bridge-code">'+esc(path)+'</div></div><div class="creditsoft-crm-admin-bridge-note">'+esc(note)+'</div></div>').join('');
  const mount = () => {
    const heading = Array.from(document.querySelectorAll('h1,h2,h3')).find((node) => /APIs & Webhooks|Admin Panel|AI/i.test(node.textContent || ''));
    return heading?.parentElement || document.querySelector('main,[role="main"],[data-testid="page-content"]') || document.getElementById('root') || document.body;
  };
  const render = () => {
    if (!document.body || !shouldRender()) return;
    style();
    const target = mount();
    if (!target) return;
    const existing = document.getElementById(panelId);
    if (existing && existing.parentElement === target) return;
    existing?.remove();
    const panel = document.createElement('section');
    panel.id = panelId;
    panel.className = 'creditsoft-crm-admin-bridge-panel';
    panel.innerHTML = '<div class="creditsoft-crm-admin-bridge-head"><div><h2>CreditSoft webhooks and AI are handled by the intranet</h2><p>This CRM screen is Twenty\\'s generic admin area. These are the CreditSoft routes the CRM, portal, website, companion, and office nodes actually use.</p></div><div class="creditsoft-crm-admin-bridge-actions"><a href="'+esc(config.apiWebhooksUrl)+'" target="_top" rel="noopener">Open API/Webhooks</a><a href="'+esc(config.aiSettingsUrl)+'" target="_top" rel="noopener">Open AI settings</a><a href="'+esc(config.publicDocsUrl)+'" target="_blank" rel="noopener">User guide</a></div></div><div class="creditsoft-crm-admin-bridge-grid"><section class="creditsoft-crm-admin-bridge-list" aria-label="CreditSoft webhook routes">'+rows()+'</section><aside class="creditsoft-crm-admin-bridge-ai" aria-label="CreditSoft AI settings"><h2>Built-in AI</h2><p>The assistant, CTO recommendations, letters, briefs, and review helpers use the intranet AI settings, not CRM-only API keys.</p><dl><div><dt>Chat endpoint</dt><dd>'+esc(config.aiEndpoint)+'</dd></div><div><dt>API base</dt><dd>'+esc(config.apiBase)+'</dd></div><div><dt>Providers</dt><dd>OpenRouter, Ollama Cloud, OpenCode Zen</dd></div></dl></aside></div>';
    target.insertBefore(panel, target.firstChild);
  };
  render();
  window.addEventListener('hashchange', () => setTimeout(render, 80));
  window.addEventListener('popstate', () => setTimeout(render, 80));
  new MutationObserver(render).observe(document.documentElement, {childList: true, subtree: true});
  setInterval(render, 1500);
})();
</script>`;
    let rewritten = html.includes('creditsoft-crm-router-env')
        ? html
        : html.replace('</head>', `${envScript}</head>`);

    rewritten = rewritten.includes('creditsoft-crm-admin-bridge')
        ? rewritten
        : rewritten.replace('</head>', `${adminBridgeScript}</head>`);

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
                clientVersion: CLIENT_VERSION,
                targetOrigin,
                apiBase: selected.apiBase,
                apiName: selected.apiName ?? null,
                latencyMs: selected.latencyMs ?? null,
                authenticated: selected.authenticated,
                authStatus: selected.authStatus ?? null,
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
        clientVersion: CLIENT_VERSION,
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
