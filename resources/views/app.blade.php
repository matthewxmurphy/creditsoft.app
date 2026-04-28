<!DOCTYPE html>
@php
    $creditsoftInstallHost = in_array(request()->getHost(), ['127.0.0.1', 'localhost'], true);
@endphp
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"  @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#111111">
        <meta name="application-name" content="CreditSoft">
        <meta name="apple-mobile-web-app-title" content="CreditSoft">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: oklch(1 0 0);
            }

            html.dark {
                background-color: oklch(0.145 0 0);
            }

            .creditsoft-debug-overlay[hidden] {
                display: none;
            }

            .creditsoft-debug-overlay {
                position: fixed;
                inset: 1rem;
                z-index: 9999;
                display: flex;
                align-items: flex-start;
                justify-content: center;
                pointer-events: none;
            }

            .creditsoft-debug-overlay__panel {
                width: min(56rem, calc(100vw - 2rem));
                max-height: calc(100vh - 2rem);
                overflow: auto;
                border: 1px solid rgba(239, 68, 68, 0.45);
                border-radius: 1.25rem;
                background: rgba(28, 25, 23, 0.96);
                box-shadow: 0 24px 80px rgba(15, 23, 42, 0.4);
                color: rgb(250 250 249);
                padding: 1rem 1rem 1.1rem;
                pointer-events: auto;
            }

            .creditsoft-debug-overlay__eyebrow {
                margin: 0;
                color: rgb(252 165 165);
                font-size: 0.68rem;
                font-weight: 700;
                letter-spacing: 0.24em;
                text-transform: uppercase;
            }

            .creditsoft-debug-overlay__title {
                margin: 0.45rem 0 0;
                color: white;
                font-size: 1.2rem;
                line-height: 1.25;
                font-weight: 700;
            }

            .creditsoft-debug-overlay__copy {
                margin: 0.65rem 0 0;
                color: rgb(231 229 228);
                font-size: 0.92rem;
                line-height: 1.6;
            }

            .creditsoft-debug-overlay__meta {
                margin-top: 0.85rem;
                padding: 0.8rem 0.9rem;
                border-radius: 0.9rem;
                background: rgba(255, 255, 255, 0.06);
                color: rgb(214 211 209);
                font-size: 0.82rem;
                line-height: 1.55;
                word-break: break-word;
            }

            .creditsoft-debug-overlay__log {
                margin: 0.85rem 0 0;
                padding: 0.9rem 1rem;
                border-radius: 0.95rem;
                background: rgba(12, 10, 9, 0.82);
                color: rgb(253 224 71);
                font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
                font-size: 0.8rem;
                line-height: 1.55;
                white-space: pre-wrap;
                word-break: break-word;
            }

            .creditsoft-debug-overlay__actions {
                margin-top: 0.9rem;
                display: flex;
                flex-wrap: wrap;
                gap: 0.65rem;
            }

            .creditsoft-debug-overlay__button {
                appearance: none;
                border: 1px solid rgba(255, 255, 255, 0.16);
                border-radius: 999px;
                background: rgba(255, 255, 255, 0.08);
                color: white;
                cursor: pointer;
                font: inherit;
                font-size: 0.84rem;
                font-weight: 600;
                padding: 0.7rem 1rem;
            }

            .creditsoft-debug-overlay__button:hover,
            .creditsoft-debug-overlay__button:focus-visible {
                background: rgba(255, 255, 255, 0.16);
                outline: none;
            }

            a[aria-label="Billing and revenue"] {
                display: none !important;
            }

            .creditsoft-emergency-dashboard {
                max-width: 1180px;
                margin: 0 auto;
                padding: 2rem 1.5rem 2.5rem;
                display: grid;
                gap: 1.5rem;
                font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            }

            .creditsoft-emergency-dashboard__metrics {
                display: grid;
                gap: 1rem;
                grid-template-columns: repeat(4, minmax(0, 1fr));
            }

            .creditsoft-emergency-dashboard__summary {
                display: grid;
                gap: 1rem;
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }

            .creditsoft-emergency-dashboard__card {
                border: 1px solid rgba(214, 211, 209, 0.9);
                border-radius: 1.75rem;
                background: rgba(255, 255, 255, 0.94);
                box-shadow: 0 24px 60px rgba(28, 25, 23, 0.08);
                padding: 1.35rem 1.4rem;
            }

            .creditsoft-emergency-dashboard__hero {
                border: 1px solid rgba(214, 211, 209, 0.9);
                border-radius: 1.9rem;
                background:
                    radial-gradient(circle at top right, rgba(59, 130, 246, 0.12), transparent 28rem),
                    rgba(255, 255, 255, 0.96);
                box-shadow: 0 28px 70px rgba(28, 25, 23, 0.1);
                padding: 1.6rem 1.6rem 1.75rem;
            }

            .creditsoft-emergency-dashboard__eyebrow {
                margin: 0;
                color: rgb(120 113 108);
                font-size: 0.68rem;
                font-weight: 700;
                letter-spacing: 0.24em;
                text-transform: uppercase;
            }

            .creditsoft-emergency-dashboard__value {
                margin: 0.75rem 0 0;
                color: rgb(28 25 23);
                font-size: 2.45rem;
                line-height: 1;
                font-weight: 700;
                letter-spacing: -0.04em;
            }

            .creditsoft-emergency-dashboard__hint {
                margin: 0.55rem 0 0;
                color: rgb(87 83 78);
                font-size: 0.92rem;
                line-height: 1.5;
            }

            .creditsoft-emergency-dashboard__title {
                margin: 0.55rem 0 0;
                color: rgb(28 25 23);
                font-size: 2rem;
                line-height: 1.05;
                font-weight: 750;
                letter-spacing: -0.04em;
            }

            .creditsoft-emergency-dashboard__copy {
                margin: 0.85rem 0 0;
                max-width: 56rem;
                color: rgb(68 64 60);
                font-size: 0.98rem;
                line-height: 1.75;
            }

            .creditsoft-emergency-dashboard__reason {
                margin: 0.9rem 0 0;
                color: rgb(120 113 108);
                font-size: 0.78rem;
                line-height: 1.55;
            }

            @media (max-width: 1100px) {
                .creditsoft-emergency-dashboard__metrics {
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                }

                .creditsoft-emergency-dashboard__summary {
                    grid-template-columns: 1fr;
                }
            }

            @media (max-width: 768px) {
                .creditsoft-emergency-dashboard {
                    padding-inline: 1rem;
                }

                .creditsoft-emergency-dashboard__metrics {
                    grid-template-columns: 1fr;
                }

                .creditsoft-emergency-dashboard__card,
                .creditsoft-emergency-dashboard__hero {
                    border-radius: 1.35rem;
                    padding: 1.15rem 1.05rem;
                }

                .creditsoft-emergency-dashboard__title {
                    font-size: 1.55rem;
                }

                .creditsoft-emergency-dashboard__value {
                    font-size: 2rem;
                }
            }
        </style>

        <link rel="icon" href="/favicon.svg?v=2" type="image/svg+xml">
        <link rel="alternate icon" href="/favicon.ico?v=2" sizes="any">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png?v=2">
        @if ($creditsoftInstallHost)
            <link rel="manifest" href="/manifest.webmanifest?v=3">
        @endif

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

        <script>
            (function () {
                const debugEnabled = ['127.0.0.1', 'localhost'].includes(window.location.hostname)
                    || window.location.search.includes('creditsoftDebug=1');
                const watchdogEnabled = window.location.search.includes('creditsoftDebug=1');

                if (!debugEnabled) {
                    return;
                }

                const debugState = {
                    entries: [],
                    shown: false,
                };

                function formatError(input) {
                    if (!input) {
                        return 'Unknown error';
                    }

                    if (input instanceof Error) {
                        return input.stack || `${input.name}: ${input.message}`;
                    }

                    if (typeof input === 'object') {
                        try {
                            return JSON.stringify(input, null, 2);
                        } catch (error) {
                            return String(input);
                        }
                    }

                    return String(input);
                }

                function pushEntry(kind, value) {
                    const timestamp = new Date().toLocaleTimeString();
                    debugState.entries.push(`[${timestamp}] ${kind}\n${formatError(value)}`);
                    debugState.entries = debugState.entries.slice(-10);
                }

                function ensureOverlay() {
                    let overlay = document.getElementById('creditsoft-debug-overlay');

                    if (!overlay) {
                        overlay = document.createElement('div');
                        overlay.id = 'creditsoft-debug-overlay';
                        overlay.className = 'creditsoft-debug-overlay';
                        overlay.hidden = true;
                        overlay.innerHTML = `
                            <div class="creditsoft-debug-overlay__panel">
                                <p class="creditsoft-debug-overlay__eyebrow">CreditSoft debug</p>
                                <h2 class="creditsoft-debug-overlay__title">The app hit a browser-side error before it could render.</h2>
                                <p class="creditsoft-debug-overlay__copy">This box is only here for local debugging. It should show us the real frontend error instead of leaving a blank white page.</p>
                                <div class="creditsoft-debug-overlay__meta" data-creditsoft-debug-meta></div>
                                <pre class="creditsoft-debug-overlay__log" data-creditsoft-debug-log></pre>
                                <div class="creditsoft-debug-overlay__actions">
                                    <button type="button" class="creditsoft-debug-overlay__button" data-creditsoft-debug-copy>Copy debug details</button>
                                    <button type="button" class="creditsoft-debug-overlay__button" data-creditsoft-debug-reload>Reload page</button>
                                    <button type="button" class="creditsoft-debug-overlay__button" data-creditsoft-debug-close>Hide</button>
                                </div>
                            </div>
                        `;
                        document.body.appendChild(overlay);

                        overlay.querySelector('[data-creditsoft-debug-copy]')?.addEventListener('click', async () => {
                            const payload = overlay.querySelector('[data-creditsoft-debug-log]')?.textContent || '';
                            try {
                                await navigator.clipboard.writeText(payload);
                            } catch (error) {
                                pushEntry('Clipboard error', error);
                                renderOverlay('Could not copy debug details automatically.');
                            }
                        });

                        overlay.querySelector('[data-creditsoft-debug-reload]')?.addEventListener('click', () => {
                            window.location.reload();
                        });

                        overlay.querySelector('[data-creditsoft-debug-close]')?.addEventListener('click', () => {
                            overlay.hidden = true;
                        });
                    }

                    return overlay;
                }

                function renderOverlay(reason) {
                    const overlay = ensureOverlay();
                    const meta = overlay.querySelector('[data-creditsoft-debug-meta]');
                    const log = overlay.querySelector('[data-creditsoft-debug-log]');
                    const inertiaRoot = document.getElementById('app');

                    if (meta) {
                        meta.textContent = [
                            `Reason: ${reason}`,
                            `Path: ${window.location.pathname}`,
                            `Title: ${document.title || '(blank title)'}`,
                            `Body classes: ${document.body?.className || '(none)'}`,
                            `App root present: ${inertiaRoot ? 'yes' : 'no'}`,
                            `App root children: ${inertiaRoot?.children?.length ?? 0}`,
                        ].join(' | ');
                    }

                    if (log) {
                        log.textContent = debugState.entries.join('\n\n');
                    }

                    overlay.hidden = false;
                    debugState.shown = true;
                }

                function hideOverlayIfMounted() {
                    const overlay = document.getElementById('creditsoft-debug-overlay');
                    const inertiaRoot = document.getElementById('app');
                    const hasVisibleAppMarkup = Boolean(inertiaRoot && inertiaRoot.querySelector('*'));

                    if (overlay && hasVisibleAppMarkup) {
                        overlay.hidden = true;
                    }
                }

                window.__creditsoftDebug = {
                    pushEntry,
                    renderOverlay,
                };

                function readInertiaPagePayload() {
                    const inertiaRoot = document.getElementById('app');
                    const raw = inertiaRoot?.dataset?.page;

                    if (!raw) {
                        const script = document.querySelector('script[data-page="app"][type="application/json"]');
                        const inlineRaw = script?.textContent?.trim();

                        if (!inlineRaw) {
                            return null;
                        }

                        try {
                            return JSON.parse(inlineRaw);
                        } catch (error) {
                            pushEntry('Inertia script payload parse failure', error);
                            return null;
                        }
                    }

                    try {
                        return JSON.parse(raw);
                    } catch (error) {
                        pushEntry('Inertia payload parse failure', error);
                        return null;
                    }
                }

                function formatCount(value) {
                    return new Intl.NumberFormat('en-US').format(Number(value ?? 0));
                }

                function formatMoney(value) {
                    return new Intl.NumberFormat('en-US', {
                        style: 'currency',
                        currency: 'USD',
                        maximumFractionDigits: 0,
                    }).format(Number(value ?? 0));
                }

                function renderEmergencyDashboardFallback(reason) {
                    if (window.location.pathname !== '/dashboard') {
                        return false;
                    }

                    const inertiaRoot = document.getElementById('app');
                    const page = readInertiaPagePayload();

                    if (!inertiaRoot || !page || page.component !== 'Dashboard') {
                        return false;
                    }

                    if (inertiaRoot.dataset.creditsoftEmergencyDashboard === 'true') {
                        return true;
                    }

                    const props = page.props || {};
                    const kpis = props.kpis || {};
                    const latestCycleSummary = props.latestCycleSummary || {};

                    inertiaRoot.dataset.creditsoftEmergencyDashboard = 'true';
                    inertiaRoot.innerHTML = `
                        <main class="creditsoft-emergency-dashboard">
                            <section class="creditsoft-emergency-dashboard__metrics">
                                <div class="creditsoft-emergency-dashboard__card">
                                    <p class="creditsoft-emergency-dashboard__eyebrow">Monthly recurring revenue</p>
                                    <p class="creditsoft-emergency-dashboard__value">${formatMoney(kpis.mrr)}</p>
                                    <p class="creditsoft-emergency-dashboard__hint">Current headline metric</p>
                                </div>
                                <div class="creditsoft-emergency-dashboard__card">
                                    <p class="creditsoft-emergency-dashboard__eyebrow">Active clients</p>
                                    <p class="creditsoft-emergency-dashboard__value">${formatCount(kpis.clients)}</p>
                                    <p class="creditsoft-emergency-dashboard__hint">Local dossiers on this installation</p>
                                </div>
                                <div class="creditsoft-emergency-dashboard__card">
                                    <p class="creditsoft-emergency-dashboard__eyebrow">Open tasks</p>
                                    <p class="creditsoft-emergency-dashboard__value">${formatCount(kpis.open_tasks)}</p>
                                    <p class="creditsoft-emergency-dashboard__hint">Inbox and SOP work still in motion</p>
                                </div>
                                <div class="creditsoft-emergency-dashboard__card">
                                    <p class="creditsoft-emergency-dashboard__eyebrow">Open violations</p>
                                    <p class="creditsoft-emergency-dashboard__value">${formatCount(kpis.open_violations)}</p>
                                    <p class="creditsoft-emergency-dashboard__hint">Candidates waiting on review or action</p>
                                </div>
                            </section>
                            <section class="creditsoft-emergency-dashboard__hero">
                                <p class="creditsoft-emergency-dashboard__eyebrow">Dashboard recovery mode</p>
                                <h1 class="creditsoft-emergency-dashboard__title">CreditSoft is back in a safe fallback view.</h1>
                                <p class="creditsoft-emergency-dashboard__copy">The local office app hit a dashboard bundle failure, so CreditSoft rendered this lightweight recovery view from the server data instead of leaving you on a blank page. We can keep moving while the full bundle is cleaned up.</p>
                                <p class="creditsoft-emergency-dashboard__reason">Reason: ${String(reason && reason.message ? reason.message : reason || 'Dashboard import failed.')}</p>
                            </section>
                            <section class="creditsoft-emergency-dashboard__summary">
                                <div class="creditsoft-emergency-dashboard__card">
                                    <p class="creditsoft-emergency-dashboard__eyebrow">Accounts reviewed</p>
                                    <p class="creditsoft-emergency-dashboard__value">${formatCount(latestCycleSummary.total_accounts)}</p>
                                    <p class="creditsoft-emergency-dashboard__hint">Latest reporting cycle</p>
                                </div>
                                <div class="creditsoft-emergency-dashboard__card">
                                    <p class="creditsoft-emergency-dashboard__eyebrow">Priority disputes</p>
                                    <p class="creditsoft-emergency-dashboard__value">${formatCount(latestCycleSummary.priority_disputes)}</p>
                                    <p class="creditsoft-emergency-dashboard__hint">Marked priority this cycle</p>
                                </div>
                                <div class="creditsoft-emergency-dashboard__card">
                                    <p class="creditsoft-emergency-dashboard__eyebrow">Utilization targets</p>
                                    <p class="creditsoft-emergency-dashboard__value">${formatCount(latestCycleSummary.over_thirty_percent)}</p>
                                    <p class="creditsoft-emergency-dashboard__hint">Accounts over 30%</p>
                                </div>
                            </section>
                        </main>
                    `;

                    pushEntry('Emergency dashboard fallback', 'Rendered dashboard from inline shell data.');
                    return true;
                }

                pushEntry('Debug bootstrap', `Ready on ${window.location.href}`);

                window.addEventListener('error', (event) => {
                    const details = {
                        message: event.message || 'Unknown script error',
                        filename: event.filename || '(inline)',
                        line: event.lineno || 0,
                        column: event.colno || 0,
                        stack: event.error?.stack || null,
                    };
                    pushEntry('Window error', details);
                    if (renderEmergencyDashboardFallback(event.error || details)) {
                        return;
                    }
                    renderOverlay('A JavaScript error fired.');
                });

                window.addEventListener('unhandledrejection', (event) => {
                    pushEntry('Unhandled promise rejection', event.reason || event);
                    if (renderEmergencyDashboardFallback(event.reason || event)) {
                        return;
                    }
                    renderOverlay('A promise rejected without being handled.');
                });

                const originalConsoleError = console.error.bind(console);
                console.error = function (...args) {
                    pushEntry('Console error', args.map((arg) => formatError(arg)).join('\n'));
                    originalConsoleError(...args);
                };

                document.addEventListener('DOMContentLoaded', () => {
                    if (!watchdogEnabled) {
                        return;
                    }

                    window.setTimeout(() => {
                        const inertiaRoot = document.getElementById('app');
                        const bodyText = (document.body?.innerText || '').trim();
                        const hasVisibleAppMarkup = Boolean(inertiaRoot && inertiaRoot.querySelector('*'));

                        if (!debugState.shown && !hasVisibleAppMarkup && bodyText.length < 80) {
                            pushEntry('Mount watchdog', 'The page shell loaded but no visible app markup appeared.');
                            renderOverlay('The page shell loaded, but the app never visibly mounted.');
                        }
                    }, 7000);

                    const inertiaRoot = document.getElementById('app');

                    if (inertiaRoot && typeof MutationObserver !== 'undefined') {
                        const observer = new MutationObserver(() => {
                            hideOverlayIfMounted();

                            if (inertiaRoot.querySelector('*')) {
                                observer.disconnect();
                            }
                        });

                        observer.observe(inertiaRoot, { childList: true, subtree: true });
                    }
                });
            })();
        </script>

        @vite(['resources/css/app.css', 'resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        <x-inertia::head>
            <title>CreditSoft</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
