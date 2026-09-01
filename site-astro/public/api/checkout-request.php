<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/pricing-config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

function creditsoft_checkout_api_json(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function creditsoft_checkout_api_config_value(array $names): string
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

function creditsoft_checkout_api_turnstile_secret(): string
{
    return creditsoft_checkout_api_config_value([
        'CREDITSOFT_TURNSTILE_SECRET_KEY',
        'TURNSTILE_SECRET_KEY',
        'CLOUDFLARE_TURNSTILE_SECRET_KEY',
    ]);
}

function creditsoft_checkout_api_turnstile_is_valid(?string $token): bool
{
    $secret = creditsoft_checkout_api_turnstile_secret();

    $token = trim((string) $token);

    if ($secret === '' || $token === '') {
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

function creditsoft_checkout_api_email_domain_has_mx(string $email): bool
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

function creditsoft_checkout_api_text_looks_human(string $value): bool
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

function creditsoft_checkout_api_money(float $amount): string
{
    return '$'.number_format($amount, 2);
}

function creditsoft_checkout_api_public_plan_key(string $value): string
{
    $normalized = strtolower(trim($value));
    $normalized = str_replace(['-', ' '], '_', $normalized);

    return match ($normalized) {
        'professional', 'enterprise_basic', 'basic', 'core' => 'enterprise',
        'enterprise', 'enterprise_pro', 'pro', 'api', 'api_version' => 'enterprise_pro',
        default => function_exists('creditsoft_site_public_plan_key') ? creditsoft_site_public_plan_key($normalized) : 'enterprise',
    };
}

function creditsoft_checkout_api_billing_key(string $value): string
{
    $normalized = strtolower(trim($value));

    return match ($normalized) {
        'yearly', 'annual', 'annually' => 'yearly',
        'ten-year', 'ten_year', '10-year', '10_year', 'lifetime' => 'ten-year',
        default => 'monthly',
    };
}

function creditsoft_checkout_api_payment_method(string $value): string
{
    $normalized = strtolower(trim($value));

    return match ($normalized) {
        'cash-app', 'cash_app', 'cashapp', 'square' => 'cash_app',
        'zelle' => 'zelle',
        default => 'zelle_or_cash_app',
    };
}

function creditsoft_checkout_api_server_nodes(array $input): int
{
    $quantity = max(0, min(50, (int) ($input['server_nodes'] ?? 0)));
    $addOnsInput = $input['add_ons'] ?? $input['addons'] ?? [];

    if (! is_array($addOnsInput)) {
        return $quantity;
    }

    foreach ($addOnsInput as $addOn) {
        if (! is_array($addOn)) {
            continue;
        }

        $key = strtolower(trim((string) ($addOn['key'] ?? $addOn['source_key'] ?? '')));

        if (in_array($key, ['server_node', 'cluster', 'office_node'], true)) {
            $quantity = max($quantity, max(1, min(50, (int) ($addOn['quantity'] ?? 1))));
        }
    }

    return $quantity;
}

function creditsoft_checkout_api_invoice_quote(array $input): array
{
    if (
        ! function_exists('creditsoft_site_pricing_db')
        || ! function_exists('creditsoft_site_checkout_price_quote')
        || ! function_exists('creditsoft_site_pricing_load')
    ) {
        return ['success' => false, 'status' => 503, 'error' => 'Database pricing is not loaded on this checkout endpoint.'];
    }

    $billing = creditsoft_checkout_api_billing_key((string) ($input['billing'] ?? 'monthly'));

    if ($billing === 'ten-year') {
        return ['success' => false, 'status' => 422, 'error' => '10-year checkout needs a scoped invoice before payment.'];
    }

    $publicPlanKey = creditsoft_checkout_api_public_plan_key((string) ($input['plan'] ?? $input['public_plan_key'] ?? 'enterprise'));
    $serverNodes = creditsoft_checkout_api_server_nodes($input);
    $quote = creditsoft_site_checkout_price_quote($publicPlanKey, $billing, []);

    if (empty($quote['success'])) {
        return [
            'success' => false,
            'status' => (int) ($quote['status'] ?? 503),
            'error' => (string) ($quote['error'] ?? 'Unable to quote checkout from database pricing.'),
        ];
    }

    $pricing = creditsoft_site_pricing_load();
    $addons = is_array($pricing['addons'] ?? null) ? $pricing['addons'] : [];
    $cluster = is_array($addons['cluster'] ?? null) ? $addons['cluster'] : null;
    $addonRows = [];
    $addonAmount = 0.0;
    $addonListAmount = 0.0;
    $serverNodeUnitAmount = is_array($cluster) ? round((float) ($cluster[$billing] ?? 0), 2) : null;
    $serverNodeUnitListAmount = is_array($cluster) ? round((float) ($cluster['list_'.$billing] ?? 0), 2) : null;

    if ($serverNodes > 0) {
        if (! is_array($cluster)) {
            return ['success' => false, 'status' => 422, 'error' => 'Server node pricing is not configured in the database.'];
        }

        $unitAmount = (float) $serverNodeUnitAmount;
        $unitListAmount = (float) $serverNodeUnitListAmount;

        if ($unitAmount <= 0) {
            return ['success' => false, 'status' => 422, 'error' => 'Server node has no payable database price.'];
        }

        $addonAmount = round($unitAmount * $serverNodes, 2);
        $addonListAmount = round($unitListAmount * $serverNodes, 2);
        $addonRows[] = [
            'key' => 'server_node',
            'source_key' => 'cluster',
            'name' => (string) ($cluster['name'] ?? 'Additional server node'),
            'quantity' => $serverNodes,
            'billing' => $billing,
            'unit_amount' => $unitAmount,
            'unit_list_amount' => $unitListAmount,
            'amount' => $addonAmount,
            'list_amount' => $addonListAmount,
        ];
    }

    $planAmount = round((float) ($quote['plan_amount'] ?? 0), 2);
    $planListAmount = round((float) (($quote['pricing_snapshot']['plan']['list_amount'] ?? null) ?? $quote['list_amount'] ?? 0), 2);
    $baseAmount = round($planAmount + $addonAmount, 2);
    $listAmount = round($planListAmount + $addonListAmount, 2);
    $discountPercent = function_exists('creditsoft_site_manual_payment_discount_percent')
        ? creditsoft_site_manual_payment_discount_percent()
        : 0;
    $amount = function_exists('creditsoft_site_manual_payment_amount')
        ? creditsoft_site_manual_payment_amount($baseAmount, $discountPercent)
        : $baseAmount;
    $amount = round($amount, 2);
    $planName = (string) ($quote['plan_name'] ?? 'CreditSoft');

    if ($addonRows !== []) {
        $planName .= ' + ' . ($serverNodes === 1 ? '1 server node' : $serverNodes.' server nodes');
    }

    $snapshot = is_array($quote['pricing_snapshot'] ?? null) ? $quote['pricing_snapshot'] : [];
    $snapshot['checkout_invoice'] = [
        'source' => 'site_pricing_config',
        'created_at' => gmdate('c'),
        'plan_amount' => $planAmount,
        'addon_amount' => $addonAmount,
        'base_amount' => $baseAmount,
        'list_amount' => $listAmount,
        'payment_amount' => $amount,
        'manual_payment_discount_percent' => $discountPercent,
    ];
    $snapshot['addons'] = $addonRows;

    return [
        'success' => true,
        'pricing_source' => 'site_pricing_config',
        'public_plan_key' => $publicPlanKey,
        'license_plan_key' => (string) ($quote['license_plan_key'] ?? $publicPlanKey),
        'billing' => $billing,
        'plan_name' => $planName,
        'plan_amount' => $planAmount,
        'addon_amount' => $addonAmount,
        'base_amount' => $baseAmount,
        'list_amount' => $listAmount,
        'amount' => $amount,
        'zelle_amount' => $amount,
        'zelle_discount_percent' => $discountPercent,
        'add_ons' => $addonRows,
        'addons' => array_values(array_unique(array_filter(array_map(
            static fn (array $row): string => (string) ($row['source_key'] ?? $row['key'] ?? ''),
            $addonRows,
        )))),
        'server_nodes' => $serverNodes,
        'server_node_unit_amount' => $serverNodeUnitAmount,
        'server_node_unit_label' => $serverNodeUnitAmount !== null && $serverNodeUnitAmount > 0 ? creditsoft_checkout_api_money($serverNodeUnitAmount) : null,
        'server_node_unit_list_amount' => $serverNodeUnitListAmount,
        'server_node_unit_list_label' => $serverNodeUnitListAmount !== null && $serverNodeUnitListAmount > 0 ? creditsoft_checkout_api_money($serverNodeUnitListAmount) : null,
        'pricing_snapshot' => $snapshot,
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (($_GET['quote'] ?? '') !== '1') {
        creditsoft_checkout_api_json(405, ['success' => false, 'error' => 'Method not allowed']);
    }

    $quote = creditsoft_checkout_api_invoice_quote($_GET);

    if (empty($quote['success'])) {
        creditsoft_checkout_api_json((int) ($quote['status'] ?? 503), $quote);
    }

    creditsoft_checkout_api_json(200, array_merge($quote, [
        'amount_label' => creditsoft_checkout_api_money((float) $quote['amount']),
        'base_amount_label' => creditsoft_checkout_api_money((float) $quote['base_amount']),
        'list_amount_label' => creditsoft_checkout_api_money((float) $quote['list_amount']),
    ]));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    creditsoft_checkout_api_json(405, ['success' => false, 'error' => 'Method not allowed']);
}

$input = json_decode((string) file_get_contents('php://input'), true);

if (! is_array($input)) {
    creditsoft_checkout_api_json(422, ['success' => false, 'error' => 'Invalid request body']);
}

$customerEmail = filter_var((string) ($input['customer_email'] ?? ''), FILTER_SANITIZE_EMAIL);
$customerPhone = trim((string) ($input['customer_phone'] ?? ''));
$paymentSource = trim((string) ($input['payment_source'] ?? ''));
$paymentMethod = creditsoft_checkout_api_payment_method((string) ($input['payment_method'] ?? 'zelle_or_cash_app'));
$officeName = trim((string) ($input['office_name'] ?? ''));
$turnstileToken = trim((string) ($input['turnstile_token'] ?? $input['cf-turnstile-response'] ?? ''));
$honeypot = trim((string) ($input['website'] ?? ''));

if (! $customerEmail || ! filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
    creditsoft_checkout_api_json(422, ['success' => false, 'error' => 'A valid customer email is required.']);
}

if (! creditsoft_checkout_api_email_domain_has_mx($customerEmail)) {
    creditsoft_checkout_api_json(422, ['success' => false, 'error' => 'Use an email address with an active mail domain.']);
}

if ($honeypot !== '') {
    creditsoft_checkout_api_json(422, ['success' => false, 'error' => 'Unable to create the checkout request.']);
}

if (! creditsoft_checkout_api_turnstile_is_valid($turnstileToken)) {
    creditsoft_checkout_api_json(422, ['success' => false, 'error' => 'The browser check did not pass. Please refresh and try again.']);
}

if ($customerPhone === '' || $officeName === '' || ! creditsoft_checkout_api_text_looks_human($officeName)) {
    creditsoft_checkout_api_json(422, ['success' => false, 'error' => 'Phone and office name are required.']);
}

$quote = creditsoft_checkout_api_invoice_quote($input);

if (empty($quote['success'])) {
    creditsoft_checkout_api_json((int) ($quote['status'] ?? 503), $quote);
}

$pdo = function_exists('creditsoft_site_pricing_db') ? creditsoft_site_pricing_db() : null;

if (! $pdo instanceof PDO || ! function_exists('creditsoft_site_checkout_ensure_table') || ! function_exists('creditsoft_site_checkout_reference')) {
    creditsoft_checkout_api_json(503, ['success' => false, 'error' => 'Checkout database is not available.']);
}

try {
    creditsoft_site_checkout_ensure_table($pdo);
    $checkoutNumber = creditsoft_site_checkout_reference($pdo);
    $clientPortalUrl = 'https://www.creditsoft.app/account/?email='.rawurlencode($customerEmail).'&reference='.rawurlencode($checkoutNumber);
    $paymentMemo = 'CreditSoft checkout:'.$checkoutNumber.' email:'.$customerEmail;
    $submittedAt = gmdate('Y-m-d H:i:s');
    $addonsJson = json_encode($quote['add_ons'], JSON_UNESCAPED_SLASHES);
    $snapshotJson = json_encode($quote['pricing_snapshot'], JSON_UNESCAPED_SLASHES);

    if (! is_string($addonsJson) || ! is_string($snapshotJson)) {
        creditsoft_checkout_api_json(500, ['success' => false, 'error' => 'Unable to encode checkout invoice.']);
    }

    $stmt = $pdo->prepare(
        "INSERT INTO payment_checkout_requests (
            checkout_reference, submitted_at, public_plan_key, license_plan_key, billing, addons_json, plan_name,
            amount, base_amount, plan_amount, addon_amount, list_amount, zelle_amount, zelle_discount_percent,
            payment_method, customer_email, customer_phone, payment_source, payment_amount_sent, payment_transaction_id,
            cash_app_request_id, cash_app_reference_id, cash_app_status, cash_app_mobile_url, cash_app_desktop_url,
            payment_memo_email, office_name, notes, ip_address, user_agent, pricing_snapshot_json, created_at, updated_at
        ) VALUES (
            :checkout_reference, :submitted_at, :public_plan_key, :license_plan_key, :billing, :addons_json, :plan_name,
            :amount, :base_amount, :plan_amount, :addon_amount, :list_amount, :zelle_amount, :zelle_discount_percent,
            :payment_method, :customer_email, :customer_phone, :payment_source, NULL, NULL,
            NULL, NULL, NULL, NULL, NULL,
            :payment_memo_email, :office_name, :notes, :ip_address, :user_agent, :pricing_snapshot_json, NOW(), NOW()
        )"
    );

    $stmt->execute([
        'checkout_reference' => $checkoutNumber,
        'submitted_at' => $submittedAt,
        'public_plan_key' => $quote['public_plan_key'],
        'license_plan_key' => $quote['license_plan_key'],
        'billing' => $quote['billing'],
        'addons_json' => $addonsJson,
        'plan_name' => $quote['plan_name'],
        'amount' => $quote['amount'],
        'base_amount' => $quote['base_amount'],
        'plan_amount' => $quote['plan_amount'],
        'addon_amount' => $quote['addon_amount'],
        'list_amount' => $quote['list_amount'],
        'zelle_amount' => $quote['zelle_amount'],
        'zelle_discount_percent' => $quote['zelle_discount_percent'],
        'payment_method' => $paymentMethod,
        'customer_email' => $customerEmail,
        'customer_phone' => $customerPhone,
        'payment_source' => $paymentSource,
        'payment_memo_email' => $customerEmail,
        'office_name' => $officeName,
        'notes' => trim((string) ($input['notes'] ?? '')),
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        'pricing_snapshot_json' => $snapshotJson,
    ]);
} catch (Throwable) {
    creditsoft_checkout_api_json(500, ['success' => false, 'error' => 'Unable to save the checkout invoice.']);
}

creditsoft_checkout_api_json(200, [
    'success' => true,
    'checkout_number' => $checkoutNumber,
    'checkout_reference' => $checkoutNumber,
    'customer_email' => $customerEmail,
    'payment_memo' => $paymentMemo,
    'client_portal_url' => $clientPortalUrl,
    'account_dashboard_url' => $clientPortalUrl,
    'customer_dashboard_url' => $clientPortalUrl,
    'pricing_source' => $quote['pricing_source'],
    'public_plan_key' => $quote['public_plan_key'],
    'license_plan_key' => $quote['license_plan_key'],
    'billing' => $quote['billing'],
    'plan_name' => $quote['plan_name'],
    'amount' => $quote['amount'],
    'amount_label' => creditsoft_checkout_api_money((float) $quote['amount']),
    'base_amount' => $quote['base_amount'],
    'base_amount_label' => creditsoft_checkout_api_money((float) $quote['base_amount']),
    'list_amount' => $quote['list_amount'],
    'list_amount_label' => creditsoft_checkout_api_money((float) $quote['list_amount']),
    'add_ons' => $quote['add_ons'],
    'server_nodes' => $quote['server_nodes'],
    'zelle' => [
        'payee' => 'Matthew Murphy',
        'destination' => 'hello@creditsoft.app',
        'memo' => $paymentMemo,
        'amount' => $quote['amount'],
    ],
    'cash_app' => [
        'cashtag' => '$creditsoft',
        'url' => 'https://cash.app/$creditsoft',
        'note' => $paymentMemo,
        'amount' => $quote['amount'],
    ],
]);
