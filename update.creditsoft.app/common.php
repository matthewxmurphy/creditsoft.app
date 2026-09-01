<?php
declare(strict_types=1);

foreach ([
    dirname(__DIR__) . '/credit_config.php',
    dirname(__DIR__) . '/web-meta/credit_config.php',
    dirname(__DIR__, 2) . '/credit_config.php',
] as $configPath) {
    if (is_file($configPath)) {
        require_once $configPath;
        break;
    }
}

$pricingConfigPaths = [
    dirname(__DIR__) . '/pricing-config.php',
    dirname(__DIR__) . '/web/pricing-config.php',
    dirname(__DIR__) . '/legacy/web-php-20260426214317/pricing-config.php',
];

foreach ($pricingConfigPaths as $pricingConfigPath) {
    if (is_file($pricingConfigPath)) {
        require_once $pricingConfigPath;
        break;
    }
}

function update_creditsoft_config_value(array $names): string
{
    foreach ($names as $name) {
        if (defined($name) && trim((string) constant($name)) !== '') {
            return trim((string) constant($name));
        }

        $value = getenv($name);
        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }
    }

    return '';
}

function update_creditsoft_turnstile_site_key(): string
{
    return update_creditsoft_config_value([
        'CREDITSOFT_TURNSTILE_SITE_KEY',
        'TURNSTILE_SITE_KEY',
        'CLOUDFLARE_TURNSTILE_SITE_KEY',
    ]);
}

function update_creditsoft_turnstile_secret(): string
{
    return update_creditsoft_config_value([
        'CREDITSOFT_TURNSTILE_SECRET_KEY',
        'TURNSTILE_SECRET_KEY',
        'CLOUDFLARE_TURNSTILE_SECRET_KEY',
    ]);
}

function update_creditsoft_turnstile_is_valid(?string $token): bool
{
    $secret = update_creditsoft_turnstile_secret();

    if ($secret === '') {
        return true;
    }

    $token = trim((string) $token);

    if ($token === '') {
        return false;
    }

    $payload = http_build_query([
        'secret' => $secret,
        'response' => $token,
        'remoteip' => $_SERVER['REMOTE_ADDR'] ?? null,
    ]);

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => $payload,
            'timeout' => 5,
        ],
    ]);

    $response = @file_get_contents('https://challenges.cloudflare.com/turnstile/v0/siteverify', false, $context);
    $decoded = is_string($response) ? json_decode($response, true) : null;

    return is_array($decoded) && (bool) ($decoded['success'] ?? false);
}

function update_creditsoft_email_domain_has_mx(string $email): bool
{
    $domain = substr((string) strrchr(strtolower(trim($email)), '@'), 1);

    if ($domain === '' || ! str_contains($domain, '.')) {
        return false;
    }

    if (function_exists('idn_to_ascii')) {
        $ascii = idn_to_ascii($domain, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
        if (is_string($ascii) && $ascii !== '') {
            $domain = strtolower($ascii);
        }
    }

    return function_exists('checkdnsrr')
        ? (checkdnsrr($domain, 'MX') || checkdnsrr($domain, 'A') || checkdnsrr($domain, 'AAAA'))
        : true;
}

function update_creditsoft_text_looks_human(string $value): bool
{
    $value = trim($value);

    if ($value === '') {
        return false;
    }

    if (preg_match('/^[a-z]{8,}$/i', $value) === 1 && ! preg_match('/[aeiou]/i', $value)) {
        return false;
    }

    return preg_match('/[a-z0-9]/i', $value) === 1;
}

function update_creditsoft_feed_path(): string
{
    return __DIR__.'/data/update-feed.json';
}

function update_creditsoft_public_renewal_url(): string
{
    return 'https://www.creditsoft.app/renewal/';
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

    return [];
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
        'professional', 'enterprise-basic', 'enterprise_basic', 'basic' => 'enterprise',
        'enterprise', 'enterprise-pro', 'enterprise_pro', 'pro', 'api', 'api_version' => 'enterprise_pro',
        default => array_key_exists($normalizedPlan, $plans) ? $normalizedPlan : 'enterprise',
    };
    $selected = $plans[$catalogKey] ?? $plans['enterprise'] ?? reset($plans);

    if (! is_array($selected) || $selected === []) {
        return [
            'plan_key' => $catalogKey,
            'plan_name' => 'CreditSoft',
            'billing' => 'monthly',
            'interval_label' => 'month',
            'base_amount' => null,
            'base_amount_label' => null,
            'discount_percent' => 0,
            'discount_amount' => null,
            'discount_amount_label' => null,
            'zelle_amount' => null,
            'zelle_amount_label' => null,
        ];
    }
    $normalizedBilling = strtolower(trim($billing));
    $billingKey = in_array($normalizedBilling, ['yearly', 'annual'], true) ? 'yearly' : 'monthly';
    $baseAmount = (float) ($selected[$billingKey] ?? 0);
    $discountPercent = function_exists('creditsoft_site_manual_payment_discount_percent')
        ? creditsoft_site_manual_payment_discount_percent()
        : 0;
    $discountAmount = $baseAmount > 0 ? round($baseAmount * ($discountPercent / 100), 2) : null;
    $zelleAmount = $baseAmount > 0
        ? (function_exists('creditsoft_site_manual_payment_amount')
            ? creditsoft_site_manual_payment_amount($baseAmount, $discountPercent)
            : round($baseAmount - (float) $discountAmount, 2))
        : null;

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

    return update_creditsoft_generated_qr_uri($payload);
}

function update_creditsoft_generated_qr_uri(string $payload): string
{
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

    return 'https://www.creditsoft.app/updates.creditsoft.app';
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
            'renewal_url' => update_creditsoft_public_renewal_url(),
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
            'renewal_url' => update_creditsoft_public_renewal_url(),
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
        'renewal_url' => update_creditsoft_public_renewal_url(),
        'support_url' => 'https://creditsoft.app/',
        'update_required' => false,
        'minimum_version' => null,
    ], $decoded, [
        'download_url' => $decoded['download_url'] ?? update_creditsoft_site_url(),
        'renewal_url' => $decoded['renewal_url'] ?? update_creditsoft_public_renewal_url(),
        'support_url' => $decoded['support_url'] ?? 'https://creditsoft.app/',
    ]);
}
