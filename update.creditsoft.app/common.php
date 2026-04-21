<?php
declare(strict_types=1);

$pricingConfigPath = dirname(__DIR__) . '/web/pricing-config.php';

if (is_file($pricingConfigPath)) {
    require_once $pricingConfigPath;
}

function update_creditsoft_feed_path(): string
{
    return __DIR__.'/data/update-feed.json';
}

function update_creditsoft_plan_catalog(): array
{
    if (function_exists('creditsoft_site_pricing_load')) {
        $pricing = creditsoft_site_pricing_load();
        $plans = $pricing['plans'] ?? [];

        if (is_array($plans) && $plans !== []) {
            $catalog = [];

            foreach ($plans as $planKey => $plan) {
                $catalog[$planKey] = [
                    'name' => (string) ($plan['name'] ?? $planKey),
                    'monthly' => (float) ($plan['monthly'] ?? 0),
                    'monthly_list' => (float) ($plan['list_monthly'] ?? 0),
                    'yearly' => (float) ($plan['yearly'] ?? 0),
                    'yearly_list' => (float) ($plan['list_yearly'] ?? 0),
                    'features' => array_values(array_filter(array_map('strval', (array) ($plan['features'] ?? [])))),
                ];
            }

            if ($catalog !== []) {
                return $catalog;
            }
        }
    }

    return [
        'professional' => [
            'name' => 'Enterprise',
            'monthly' => 89.95,
            'monthly_list' => 119.95,
            'yearly' => 863.52,
            'yearly_list' => 1439.40,
            'features' => [
                'Unlimited clients',
                'Unlimited users',
                'Metro2 review, letters, and briefs',
                'Client portal',
                'Priority support',
                'No Chrome browser plugin',
            ],
        ],
        'enterprise' => [
            'name' => 'Enterprise Pro',
            'monthly' => 199.95,
            'monthly_list' => 266.60,
            'yearly' => 1919.52,
            'yearly_list' => 3199.20,
            'features' => [
                'Everything in Enterprise',
                'Chrome browser companion',
                'API access',
                'Automation workflows',
                'Priority support',
            ],
        ],
    ];
}

function update_creditsoft_money(float $amount): string
{
    return '$'.number_format($amount, 2);
}

function update_creditsoft_zelle_quote(string $planKey = 'enterprise', string $billing = 'monthly'): array
{
    $plans = update_creditsoft_plan_catalog();
    $normalizedPlan = strtolower(trim($planKey));
    $catalogKey = match ($normalizedPlan) {
        'professional', 'enterprise-basic', 'enterprise_basic', 'basic' => 'professional',
        'enterprise', 'enterprise-pro', 'enterprise_pro', 'pro', 'api', 'api_version' => 'enterprise',
        default => array_key_exists($normalizedPlan, $plans) ? $normalizedPlan : 'enterprise',
    };
    $selected = $plans[$catalogKey] ?? $plans['enterprise'] ?? reset($plans);
    $normalizedBilling = strtolower(trim($billing));
    $billingKey = in_array($normalizedBilling, ['yearly', 'annual'], true) ? 'yearly' : 'monthly';
    $baseAmount = (float) ($selected[$billingKey] ?? 0);
    $discountPercent = 10;
    $discountAmount = $baseAmount > 0 ? round($baseAmount * ($discountPercent / 100), 2) : null;
    $zelleAmount = $baseAmount > 0 ? round($baseAmount - (float) $discountAmount, 2) : null;

    return [
        'plan_key' => $catalogKey,
        'plan_name' => (string) ($selected['name'] ?? 'CreditSoft'),
        'billing' => $billingKey,
        'interval_label' => $billingKey === 'yearly' ? 'year' : 'month',
        'base_amount' => $baseAmount > 0 ? $baseAmount : null,
        'base_amount_label' => $baseAmount > 0 ? update_creditsoft_money($baseAmount) : null,
        'discount_percent' => $discountPercent,
        'discount_amount' => $discountAmount,
        'discount_amount_label' => $discountAmount !== null ? update_creditsoft_money($discountAmount) : null,
        'zelle_amount' => $zelleAmount,
        'zelle_amount_label' => $zelleAmount !== null ? update_creditsoft_money($zelleAmount) : null,
    ];
}

function update_creditsoft_qr_uri(string $payload): string
{
    $liveZelleQr = __DIR__.'/assets/payments/zelle.png';

    if (is_file($liveZelleQr)) {
        return update_creditsoft_site_url('assets/payments/zelle.png');
    }

    $autoload = dirname(__DIR__).'/vendor/autoload.php';

    if (is_file($autoload)) {
        try {
            require_once $autoload;
            $renderer = new \BaconQrCode\Renderer\ImageRenderer(
                new \BaconQrCode\Renderer\RendererStyle\RendererStyle(320),
                new \BaconQrCode\Renderer\Image\SvgImageBackEnd(),
            );
            $svg = (new \BaconQrCode\Writer($renderer))->writeString($payload);

            return 'data:image/svg+xml;base64,'.base64_encode($svg);
        } catch (\Throwable) {
        }
    }

    return 'https://api.qrserver.com/v1/create-qr-code/?size=320x320&data='.rawurlencode($payload);
}

function update_creditsoft_detect_base_url(): string
{
    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $https = ($_SERVER['HTTPS'] ?? '') !== '' && strtolower((string) $_SERVER['HTTPS']) !== 'off';
    $scheme = $https ? 'https' : 'http';
    $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '');

    if ($host !== '' && str_contains($requestUri, '/updates.creditsoft.app/')) {
        return $scheme.'://'.$host.'/updates.creditsoft.app';
    }

    if ($host !== '' && str_contains($requestUri, '/update.creditsoft.app/')) {
        return $scheme.'://'.$host.'/update.creditsoft.app';
    }

    if ($host !== '') {
        return $scheme.'://'.$host;
    }

    return 'https://updates.creditsoft.app';
}

function update_creditsoft_site_url(string $path = ''): string
{
    $base = update_creditsoft_detect_base_url();

    return rtrim($base, '/').'/'.ltrim($path, '/');
}

function update_creditsoft_load_feed(): array
{
    $path = update_creditsoft_feed_path();

    if (! is_file($path)) {
        return [
            'product' => 'CreditSoft Intranet',
            'channel' => 'stable',
            'latest_version' => '1.0.0',
            'latest_build' => null,
            'published_at' => null,
            'headline' => 'Update feed unavailable',
            'summary' => 'The update metadata file is missing.',
            'notes' => [],
            'download_url' => update_creditsoft_site_url(),
            'renewal_url' => update_creditsoft_site_url('renew.php'),
            'support_url' => 'https://creditsoft.app/',
            'update_required' => false,
            'minimum_version' => null,
        ];
    }

    $decoded = json_decode((string) file_get_contents($path), true);

    if (! is_array($decoded)) {
        return [
            'product' => 'CreditSoft Intranet',
            'channel' => 'stable',
            'latest_version' => '1.0.0',
            'latest_build' => null,
            'published_at' => null,
            'headline' => 'Update feed unavailable',
            'summary' => 'The update metadata file could not be parsed.',
            'notes' => [],
            'download_url' => update_creditsoft_site_url(),
            'renewal_url' => update_creditsoft_site_url('renew.php'),
            'support_url' => 'https://creditsoft.app/',
            'update_required' => false,
            'minimum_version' => null,
        ];
    }

    return array_replace_recursive([
        'product' => 'CreditSoft Intranet',
        'channel' => 'stable',
        'latest_version' => '1.0.0',
        'latest_build' => null,
        'published_at' => null,
        'headline' => 'CreditSoft update available',
        'summary' => '',
        'notes' => [],
        'download_url' => update_creditsoft_site_url(),
        'renewal_url' => update_creditsoft_site_url('renew.php'),
        'support_url' => 'https://creditsoft.app/',
        'update_required' => false,
        'minimum_version' => null,
    ], $decoded, [
        'download_url' => $decoded['download_url'] ?? update_creditsoft_site_url(),
        'renewal_url' => $decoded['renewal_url'] ?? update_creditsoft_site_url('renew.php'),
        'support_url' => $decoded['support_url'] ?? 'https://creditsoft.app/',
    ]);
}
