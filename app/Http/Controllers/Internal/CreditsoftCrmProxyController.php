<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class CreditsoftCrmProxyController extends Controller
{
    protected const PREFIX = '/__creditsoft/crm';

    public function __invoke(Request $request, string $path = ''): SymfonyResponse
    {
        return $this->proxy($request, $path === '' ? '/' : '/'.ltrim($path, '/'));
    }

    public function asset(Request $request, string $path = ''): SymfonyResponse
    {
        return $this->proxy($request, '/assets/'.ltrim($path, '/'));
    }

    public function image(Request $request, string $path = ''): SymfonyResponse
    {
        return $this->proxy($request, '/images/'.ltrim($path, '/'));
    }

    public function object(Request $request, string $path = ''): SymfonyResponse
    {
        return $this->proxy($request, '/objects/'.ltrim($path, '/'));
    }

    protected function proxy(Request $request, string $targetPath): SymfonyResponse
    {
        abort_unless((bool) config('creditsoft.integrations.crm.enabled', false), 404);

        $crmOrigin = rtrim((string) config('creditsoft.integrations.crm.base_url', ''), '/');

        abort_unless($crmOrigin !== '', 404);

        $targetUrl = $crmOrigin.$targetPath;

        if ($request->getQueryString()) {
            $targetUrl .= '?'.$request->getQueryString();
        }

        $method = strtoupper($request->method());
        $options = [
            'headers' => $this->requestHeaders($request),
        ];

        if (! in_array($method, ['GET', 'HEAD'], true)) {
            $options['body'] = $request->getContent();
        }

        $upstream = Http::timeout(30)
            ->withOptions(['allow_redirects' => false])
            ->send($method, $targetUrl, $options);

        $body = $upstream->body();
        $contentType = (string) $upstream->header('content-type', '');

        if (str_contains($contentType, 'text/html')) {
            $body = $this->rewriteHtml($body);
        }

        $response = response($body, $upstream->status());

        foreach ($this->responseHeaders($request, $upstream->toPsrResponse()->getHeaders(), $crmOrigin) as $name => $values) {
            foreach ($values as $value) {
                $response->headers->set($name, $value, false);
            }
        }

        return $response;
    }

    protected function requestHeaders(Request $request): array
    {
        $headers = [];

        foreach ($request->headers->all() as $name => $values) {
            $normalized = strtolower($name);

            if (in_array($normalized, $this->hopByHopHeaders(), true)) {
                continue;
            }

            $headers[$name] = implode(', ', $values);
        }

        $headers['x-creditsoft-client-router'] = 'laravel';
        $headers['ngrok-skip-browser-warning'] = 'true';
        $headers['x-forwarded-proto'] = $request->getScheme();
        $headers['x-forwarded-host'] = $request->getHttpHost();

        return $headers;
    }

    protected function responseHeaders(Request $request, array $upstreamHeaders, string $crmOrigin): array
    {
        $headers = [];
        $proxyOrigin = $request->getSchemeAndHttpHost().self::PREFIX;

        foreach ($upstreamHeaders as $name => $values) {
            $normalized = strtolower($name);

            if (in_array($normalized, [...$this->hopByHopHeaders(), 'content-length', 'set-cookie'], true)) {
                continue;
            }

            foreach ($values as $value) {
                if ($normalized === 'location') {
                    $value = $this->rewriteLocation($value, $crmOrigin, $proxyOrigin);
                }

                $headers[$name][] = $value;
            }
        }

        foreach ($upstreamHeaders['Set-Cookie'] ?? $upstreamHeaders['set-cookie'] ?? [] as $cookie) {
            $headers['Set-Cookie'][] = $this->rewriteCookie($cookie);
        }

        return $headers;
    }

    protected function rewriteLocation(string $location, string $crmOrigin, string $proxyOrigin): string
    {
        if (str_starts_with($location, $crmOrigin)) {
            return $proxyOrigin.substr($location, strlen($crmOrigin));
        }

        if (str_starts_with($location, '/')) {
            return $proxyOrigin.$location;
        }

        return $location;
    }

    protected function rewriteCookie(string $cookie): string
    {
        return preg_replace([
            '/;\s*Domain=[^;]+/i',
            '/;\s*Secure/i',
        ], '', $cookie) ?? $cookie;
    }

    protected function rewriteHtml(string $html): string
    {
        $envScript = '<script id="creditsoft-crm-router-env">'
            .'window._env_=Object.assign({},window._env_||{},{REACT_APP_SERVER_BASE_URL:"'.self::PREFIX.'"});'
            .'if(window.location.pathname.indexOf("'.self::PREFIX.'/")===0){window.history.replaceState(null,document.title,"/"+window.location.search+window.location.hash);}'
            .'</script>';

        $rewritten = str_contains($html, 'creditsoft-crm-router-env')
            ? $html
            : str_replace('</head>', $envScript.'</head>', $html);

        if (! str_contains($rewritten, 'creditsoft-crm-admin-bridge')) {
            $rewritten = str_replace('</head>', $this->creditsoftAdminBridgeScript().'</head>', $rewritten);
        }

        return str_replace(
            [
                'href="/assets/',
                'src="/assets/',
                'href="/favicon',
                'src="/favicon',
                'href="/manifest',
                'src="/manifest',
                'href="/apple-touch-icon',
                'src="/apple-touch-icon',
                'href="/images/',
                'src="/images/',
            ],
            [
                'href="'.self::PREFIX.'/assets/',
                'src="'.self::PREFIX.'/assets/',
                'href="'.self::PREFIX.'/favicon',
                'src="'.self::PREFIX.'/favicon',
                'href="'.self::PREFIX.'/manifest',
                'src="'.self::PREFIX.'/manifest',
                'href="'.self::PREFIX.'/apple-touch-icon',
                'src="'.self::PREFIX.'/apple-touch-icon',
                'href="'.self::PREFIX.'/images/',
                'src="'.self::PREFIX.'/images/',
            ],
            $rewritten,
        );
    }

    protected function creditsoftAdminBridgeScript(): string
    {
        $payload = [
            'apiWebhooksUrl' => url('/settings/api-webhooks'),
            'aiSettingsUrl' => url('/settings/ai'),
            'crmUrl' => url('/crm'),
            'publicDocsUrl' => 'https://www.creditsoft.app/resources#/user-guide/introduction',
            'aiEndpoint' => url('/internal/ai/chat'),
            'apiBase' => url('/api/v1'),
            'webhooks' => [
                [
                    'method' => 'POST',
                    'path' => '/api/v1/clients',
                    'label' => 'Website lead intake',
                    'note' => 'Creates leads or clients from public forms, client sites, and partner funnels.',
                ],
                [
                    'method' => 'POST',
                    'path' => '/api/v1/clients/{cuid}/documents',
                    'label' => 'Client portal documents',
                    'note' => 'Uploads IDs, proof of address, agreements, and portal files into the local office record.',
                ],
                [
                    'method' => 'POST',
                    'path' => '/api/v1/browser-companion/intake',
                    'label' => 'Browser companion captures',
                    'note' => 'Receives SmartCredit, IdentityIQ, DisputeFox, and document capture payloads.',
                ],
                [
                    'method' => 'POST',
                    'path' => '/api/v1/cluster-actions/apply',
                    'label' => 'Multi-office action sync',
                    'note' => 'Queues deletes, archives, status updates, and other office actions for peer nodes.',
                ],
                [
                    'method' => 'POST',
                    'path' => '/api/v1/cluster-db-events/receive',
                    'label' => 'Replica event intake',
                    'note' => 'Accepts database event receipts from another office node when it comes back online.',
                ],
                [
                    'method' => 'GET',
                    'path' => '/api/v1/meta/callback',
                    'label' => 'Meta callback',
                    'note' => 'Completes Facebook, Instagram, and social publishing connection callbacks.',
                ],
            ],
            'ai' => [
                'defaultProvider' => (string) config('ai.default', 'openrouter_creditsoft'),
                'providers' => ['OpenRouter', 'Ollama Cloud', 'OpenCode Zen'],
            ],
        ];

        $json = json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR,
        );

        $script = <<<'HTML'
<script id="creditsoft-crm-admin-bridge">
(() => {
  const config = __CREDITSOFT_CRM_ADMIN_BRIDGE_CONFIG__;
  const panelId = 'creditsoft-crm-admin-bridge-panel';
  const styleId = 'creditsoft-crm-admin-bridge-style';

  const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#39;',
  })[char]);

  const pageText = () => document.body?.innerText || '';
  const looksLikeCrmSettings = () => {
    const text = pageText();
    return window.location.pathname.includes('/settings/admin-panel')
      || text.includes('APIs & Webhooks')
      || text.includes('API Keys')
      || text.includes('Webhooks')
      || text.includes('AI');
  };

  const addStyle = () => {
    if (document.getElementById(styleId)) {
      return;
    }

    const style = document.createElement('style');
    style.id = styleId;
    style.textContent = `
      .creditsoft-crm-admin-bridge-panel {
        width: min(1180px, calc(100% - 24px));
        margin: 16px auto 24px;
        border: 1px solid rgba(241, 194, 122, 0.38);
        border-radius: 14px;
        background: linear-gradient(135deg, rgba(13, 13, 18, 0.97), rgba(31, 28, 22, 0.97));
        box-shadow: 0 20px 52px rgba(0, 0, 0, 0.24);
        color: #f8fafc;
        font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        padding: 20px;
      }

      .creditsoft-crm-admin-bridge-panel * {
        box-sizing: border-box;
      }

      .creditsoft-crm-admin-bridge-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 18px;
        margin-bottom: 18px;
      }

      .creditsoft-crm-admin-bridge-panel h2 {
        margin: 0;
        color: #fff7ed;
        font-size: 22px;
        line-height: 1.2;
        letter-spacing: 0;
      }

      .creditsoft-crm-admin-bridge-panel p {
        margin: 7px 0 0;
        color: rgba(255, 255, 255, 0.74);
        font-size: 14px;
        line-height: 1.55;
      }

      .creditsoft-crm-admin-bridge-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        justify-content: flex-end;
      }

      .creditsoft-crm-admin-bridge-actions a {
        display: inline-flex;
        align-items: center;
        min-height: 38px;
        border-radius: 9px;
        padding: 0 13px;
        background: rgba(241, 194, 122, 0.14);
        border: 1px solid rgba(241, 194, 122, 0.32);
        color: #fff7ed !important;
        text-decoration: none;
        font-size: 13px;
        font-weight: 700;
      }

      .creditsoft-crm-admin-bridge-grid {
        display: grid;
        grid-template-columns: minmax(0, 1.35fr) minmax(280px, 0.65fr);
        gap: 16px;
      }

      .creditsoft-crm-admin-bridge-list,
      .creditsoft-crm-admin-bridge-ai {
        border: 1px solid rgba(255, 255, 255, 0.09);
        border-radius: 12px;
        background: rgba(255, 255, 255, 0.045);
        padding: 14px;
      }

      .creditsoft-crm-admin-bridge-row {
        display: grid;
        grid-template-columns: 74px minmax(185px, 0.8fr) minmax(240px, 1fr);
        gap: 12px;
        padding: 12px 0;
        border-top: 1px solid rgba(255, 255, 255, 0.08);
      }

      .creditsoft-crm-admin-bridge-row:first-child {
        border-top: 0;
        padding-top: 0;
      }

      .creditsoft-crm-admin-bridge-method,
      .creditsoft-crm-admin-bridge-code {
        color: #fde68a;
        font-family: "SFMono-Regular", Consolas, "Liberation Mono", monospace;
        font-size: 12px;
      }

      .creditsoft-crm-admin-bridge-code {
        overflow-wrap: anywhere;
      }

      .creditsoft-crm-admin-bridge-label {
        color: #ffffff;
        font-size: 13px;
        font-weight: 800;
      }

      .creditsoft-crm-admin-bridge-note {
        color: rgba(255, 255, 255, 0.7);
        font-size: 12px;
        line-height: 1.45;
      }

      .creditsoft-crm-admin-bridge-ai dl {
        display: grid;
        gap: 10px;
        margin: 14px 0 0;
      }

      .creditsoft-crm-admin-bridge-ai dt {
        color: rgba(255, 255, 255, 0.58);
        font-size: 11px;
        font-weight: 800;
        letter-spacing: 0.12em;
        text-transform: uppercase;
      }

      .creditsoft-crm-admin-bridge-ai dd {
        margin: 3px 0 0;
        color: #fff;
        font-size: 13px;
        overflow-wrap: anywhere;
      }

      @media (max-width: 980px) {
        .creditsoft-crm-admin-bridge-header,
        .creditsoft-crm-admin-bridge-grid,
        .creditsoft-crm-admin-bridge-row {
          grid-template-columns: 1fr;
        }

        .creditsoft-crm-admin-bridge-header {
          display: grid;
        }

        .creditsoft-crm-admin-bridge-actions {
          justify-content: flex-start;
        }
      }
    `;
    document.head.appendChild(style);
  };

  const rowsHtml = () => config.webhooks.map((hook) => `
    <div class="creditsoft-crm-admin-bridge-row">
      <span class="creditsoft-crm-admin-bridge-method">${escapeHtml(hook.method)}</span>
      <div>
        <div class="creditsoft-crm-admin-bridge-label">${escapeHtml(hook.label)}</div>
        <div class="creditsoft-crm-admin-bridge-code">${escapeHtml(hook.path)}</div>
      </div>
      <div class="creditsoft-crm-admin-bridge-note">${escapeHtml(hook.note)}</div>
    </div>
  `).join('');

  const panelHtml = () => `
    <div class="creditsoft-crm-admin-bridge-header">
      <div>
        <h2>CreditSoft webhooks and AI are handled by the intranet</h2>
        <p>This CRM screen is Twenty's generic admin area. These are the CreditSoft routes the CRM, portal, website, companion, and office nodes actually use.</p>
      </div>
      <div class="creditsoft-crm-admin-bridge-actions">
        <a href="${escapeHtml(config.apiWebhooksUrl)}" target="_top" rel="noopener">Open API/Webhooks</a>
        <a href="${escapeHtml(config.aiSettingsUrl)}" target="_top" rel="noopener">Open AI settings</a>
        <a href="${escapeHtml(config.publicDocsUrl)}" target="_blank" rel="noopener">User guide</a>
      </div>
    </div>
    <div class="creditsoft-crm-admin-bridge-grid">
      <section class="creditsoft-crm-admin-bridge-list" aria-label="CreditSoft webhook routes">
        ${rowsHtml()}
      </section>
      <aside class="creditsoft-crm-admin-bridge-ai" aria-label="CreditSoft AI settings">
        <h2>Built-in AI</h2>
        <p>The assistant, CTO recommendations, letters, briefs, and review helpers use the intranet AI settings, not CRM-only API keys.</p>
        <dl>
          <div>
            <dt>Default provider</dt>
            <dd>${escapeHtml(config.ai.defaultProvider)}</dd>
          </div>
          <div>
            <dt>Chat endpoint</dt>
            <dd>${escapeHtml(config.aiEndpoint)}</dd>
          </div>
          <div>
            <dt>Supported providers</dt>
            <dd>${escapeHtml(config.ai.providers.join(', '))}</dd>
          </div>
          <div>
            <dt>API base</dt>
            <dd>${escapeHtml(config.apiBase)}</dd>
          </div>
        </dl>
      </aside>
    </div>
  `;

  const findMount = () => {
    const heading = Array.from(document.querySelectorAll('h1, h2, h3'))
      .find((node) => /APIs & Webhooks|Admin Panel|AI/i.test(node.textContent || ''));
    const main = document.querySelector('main, [role="main"], [data-testid="page-content"]');
    const root = document.getElementById('root');

    if (heading?.parentElement) {
      let node = heading.parentElement;
      for (let i = 0; i < 4 && node?.parentElement; i += 1) {
        const rect = node.getBoundingClientRect();
        if (rect.width > 520) {
          return node;
        }
        node = node.parentElement;
      }
      return heading.parentElement;
    }

    return main || root || document.body;
  };

  const render = () => {
    if (!document.body || !looksLikeCrmSettings()) {
      return;
    }

    addStyle();

    const existing = document.getElementById(panelId);
    const mount = findMount();

    if (!mount) {
      return;
    }

    if (existing && existing.parentElement === mount) {
      return;
    }

    existing?.remove();

    const panel = document.createElement('section');
    panel.id = panelId;
    panel.className = 'creditsoft-crm-admin-bridge-panel';
    panel.innerHTML = panelHtml();
    mount.insertBefore(panel, mount.firstChild);
  };

  render();
  window.addEventListener('hashchange', () => setTimeout(render, 80));
  window.addEventListener('popstate', () => setTimeout(render, 80));

  const observer = new MutationObserver(() => render());
  observer.observe(document.documentElement, { childList: true, subtree: true });
  setInterval(render, 1500);
})();
</script>
HTML;

        return str_replace('__CREDITSOFT_CRM_ADMIN_BRIDGE_CONFIG__', $json, $script);
    }

    protected function hopByHopHeaders(): array
    {
        return [
            'connection',
            'host',
            'keep-alive',
            'proxy-authenticate',
            'proxy-authorization',
            'te',
            'trailer',
            'transfer-encoding',
            'upgrade',
        ];
    }
}
