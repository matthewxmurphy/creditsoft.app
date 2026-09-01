<?php
declare(strict_types=1);

if (! defined('CREDITSOFT_SITE_PRICING_CONFIG_LOADED')) {
    define('CREDITSOFT_SITE_PRICING_CONFIG_LOADED', true);

    foreach ([
        dirname(__DIR__) . '/credit_config.php',
        dirname(__DIR__) . '/web-meta/credit_config.php',
        dirname(__DIR__, 2) . '/credit_config.php',
        ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/../credit_config.php',
        ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/credit_config.php',
    ] as $configPath) {
        if (is_string($configPath) && $configPath !== '' && file_exists($configPath)) {
            require_once $configPath;
            break;
        }
    }

    $overlayConfigPath = dirname(__DIR__) . '/web-meta/credit_config.php';
    if (file_exists($overlayConfigPath)) {
        require_once $overlayConfigPath;
    }
}

function creditsoft_site_pricing_db(): ?PDO
{
    static $pdo = false;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    if (function_exists('creditsoft_lead_db')) {
        $leadPdo = creditsoft_lead_db();

        if ($leadPdo instanceof PDO) {
            return $pdo = $leadPdo;
        }
    }

    if (! defined('DB_HOST') || ! defined('DB_NAME') || ! defined('DB_USER') || ! defined('DB_PASS')) {
        return null;
    }

    try {
        $pdo = new PDO(
            'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    } catch (Throwable) {
        return null;
    }

    return $pdo;
}

function creditsoft_site_pricing_ensure_table(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `site_pricing_config` (
            `id` TINYINT UNSIGNED NOT NULL PRIMARY KEY,
            `pricing_json` LONGTEXT NOT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

function creditsoft_site_pricing_defaults(): array
{
    return [
        'note' => 'Early adopter pricing is already 25% off. Yearly billing adds another 20% discount.',
        'manual_payment_discount_percent' => 0,
        'plans' => [
            'enterprise' => [
                'name' => 'Enterprise',
                'featured' => false,
                'sale_badge_monthly' => '25% off early adopter',
                'sale_badge_yearly' => '45% off list while early adopter pricing is live',
                'sale_badge_lifetime' => 'Interest pricing',
                'monthly' => 99.95,
                'list_monthly' => 133.27,
                'yearly' => 959.52,
                'list_yearly' => 1599.24,
                'description' => [
                    'monthly' => 'CreditSoft Enterprise with the local intranet, client portal, letters, Metro2 review, and legal-intake screening built in.',
                    'yearly' => 'Yearly keeps the 25% early adopter sale and stacks another 20% off for the office.',
                    'lifetime' => 'Tell us what this office would actually pay for a one-time install.',
                ],
                'features' => [
                    'Unlimited users',
                    'Unlimited clients',
                    'Metro2 review, letters, and briefs',
                    'Client portal',
                    'FCRA / FDCPA lawsuit intake',
                    'Priority support',
                    'Browser companion not included',
                ],
            ],
            'enterprise_pro' => [
                'name' => 'Enterprise Pro',
                'featured' => true,
                'sale_badge_monthly' => '25% off early adopter',
                'sale_badge_yearly' => '45% off list while early adopter pricing is live',
                'sale_badge_lifetime' => 'Interest pricing',
                'monthly' => 169.95,
                'list_monthly' => 226.60,
                'yearly' => 1631.52,
                'list_yearly' => 2719.20,
                'description' => [
                    'monthly' => '25% off early adopter pricing • Includes the Chrome browser plugin and API connector',
                    'yearly' => 'Yearly keeps the 25% early adopter sale and stacks another 20% off for the office.',
                    'lifetime' => 'Tell us what Enterprise Pro with browser automation is worth to your office.',
                ],
                'features' => [
                    'Everything in Enterprise',
                    'Chrome browser companion',
                    'API access',
                    'FCRA / FDCPA lawsuit intake',
                    'Automation workflows',
                    'Priority support',
                ],
            ],
        ],
        'addons' => [
            'cluster' => [
                'name' => 'Cluster license',
                'short_name' => 'Cluster',
                'monthly' => 29.95,
                'list_monthly' => 39.95,
                'yearly' => 287.52,
                'list_yearly' => 479.40,
                'description' => 'Additional office install for a second office, branch, front desk machine, or dedicated local node.',
                'features' => [
                    'Additional local office install',
                    'Works with private networking and backup patterns',
                    'Keeps ownership cleaner than forcing every location onto one box',
                ],
            ],
        ],
    ];
}

function creditsoft_site_pricing_storage_path(): string
{
    return dirname(__DIR__) . '/web-meta/site-pricing.json';
}

function creditsoft_site_license_plan_key(string $planKey): string
{
    $normalized = strtolower(trim($planKey));
    $normalized = str_replace(['-', ' '], '_', $normalized);

    return match ($normalized) {
        'enterprise_pro', 'pro', 'api', 'api_version' => 'enterprise_pro',
        'cluster', 'cluster_license', 'office_cluster' => 'cluster',
        default => 'enterprise',
    };
}

function creditsoft_site_public_plan_key(string $planKey): string
{
    $normalized = strtolower(trim($planKey));
    $normalized = str_replace(['-', ' '], '_', $normalized);

    return match ($normalized) {
        'enterprise_pro', 'pro', 'api', 'api_version' => 'enterprise_pro',
        'cluster', 'cluster_license', 'office_cluster' => 'cluster',
        default => 'enterprise',
    };
}

function creditsoft_site_checkout_plan_slug(string $planKey): string
{
    return match (creditsoft_site_public_plan_key($planKey)) {
        'enterprise_pro' => 'enterprise-pro',
        'cluster' => 'cluster',
        default => 'enterprise',
    };
}

function creditsoft_site_checkout_addons(): array
{
    $pricing = creditsoft_site_pricing_load();
    $addons = is_array($pricing['addons'] ?? null) ? $pricing['addons'] : [];

    return is_array($addons) ? $addons : [];
}

function creditsoft_site_pricing_sanitize(array $input, bool $strict = false): array
{
    $defaults = creditsoft_site_pricing_defaults();
    $clean = [
        'note' => trim((string) ($input['note'] ?? ($strict ? '' : $defaults['note']))),
        'manual_payment_discount_percent' => max(0, min(100, (int) ($input['manual_payment_discount_percent'] ?? ($strict ? 0 : $defaults['manual_payment_discount_percent'])))),
        'plans' => [],
        'addons' => [],
    ];

    foreach ($defaults['plans'] as $planKey => $defaultPlan) {
        $rawPlan = $input['plans'][$planKey] ?? [];

        if ($strict && ! is_array($rawPlan)) {
            continue;
        }

        if ($strict && ! array_key_exists($planKey, (array) ($input['plans'] ?? []))) {
            continue;
        }

        $clean['plans'][$planKey] = [
            'name' => trim((string) ($rawPlan['name'] ?? $defaultPlan['name'])) ?: $defaultPlan['name'],
            'featured' => ! empty($rawPlan['featured']),
            'sale_badge_monthly' => trim((string) ($rawPlan['sale_badge_monthly'] ?? $defaultPlan['sale_badge_monthly'])) ?: $defaultPlan['sale_badge_monthly'],
            'sale_badge_yearly' => trim((string) ($rawPlan['sale_badge_yearly'] ?? $defaultPlan['sale_badge_yearly'])) ?: $defaultPlan['sale_badge_yearly'],
            'sale_badge_lifetime' => trim((string) ($rawPlan['sale_badge_lifetime'] ?? $defaultPlan['sale_badge_lifetime'])) ?: $defaultPlan['sale_badge_lifetime'],
            'monthly' => round((float) ($rawPlan['monthly'] ?? ($strict ? 0 : $defaultPlan['monthly'])), 2),
            'list_monthly' => round((float) ($rawPlan['list_monthly'] ?? ($strict ? 0 : $defaultPlan['list_monthly'])), 2),
            'yearly' => round((float) ($rawPlan['yearly'] ?? ($strict ? 0 : $defaultPlan['yearly'])), 2),
            'list_yearly' => round((float) ($rawPlan['list_yearly'] ?? ($strict ? 0 : $defaultPlan['list_yearly'])), 2),
            'description' => [
                'monthly' => trim((string) (($rawPlan['description']['monthly'] ?? null) ?? $defaultPlan['description']['monthly'])) ?: $defaultPlan['description']['monthly'],
                'yearly' => trim((string) (($rawPlan['description']['yearly'] ?? null) ?? $defaultPlan['description']['yearly'])) ?: $defaultPlan['description']['yearly'],
                'lifetime' => trim((string) (($rawPlan['description']['lifetime'] ?? null) ?? $defaultPlan['description']['lifetime'])) ?: $defaultPlan['description']['lifetime'],
            ],
            'features' => [],
        ];

        $rawFeatures = $rawPlan['features'] ?? $defaultPlan['features'];
        if (is_string($rawFeatures)) {
            $rawFeatures = preg_split('/\r\n|\r|\n/', $rawFeatures);
        }

        if (! is_array($rawFeatures)) {
            $rawFeatures = $defaultPlan['features'];
        }

        foreach ($rawFeatures as $feature) {
            $feature = trim((string) $feature);
            if ($feature !== '') {
                $clean['plans'][$planKey]['features'][] = $feature;
            }
        }

        if ($clean['plans'][$planKey]['features'] === []) {
            $clean['plans'][$planKey]['features'] = $defaultPlan['features'];
        }
    }

    foreach (($defaults['addons'] ?? []) as $addonKey => $defaultAddon) {
        $rawAddon = $input['addons'][$addonKey] ?? [];

        if ($strict && ! is_array($rawAddon)) {
            continue;
        }

        if ($strict && ! array_key_exists($addonKey, (array) ($input['addons'] ?? []))) {
            continue;
        }

        $clean['addons'][$addonKey] = [
            'name' => trim((string) ($rawAddon['name'] ?? $defaultAddon['name'])) ?: $defaultAddon['name'],
            'short_name' => trim((string) ($rawAddon['short_name'] ?? $defaultAddon['short_name'])) ?: $defaultAddon['short_name'],
            'monthly' => round((float) ($rawAddon['monthly'] ?? ($strict ? 0 : $defaultAddon['monthly'])), 2),
            'list_monthly' => round((float) ($rawAddon['list_monthly'] ?? ($strict ? 0 : $defaultAddon['list_monthly'])), 2),
            'yearly' => round((float) ($rawAddon['yearly'] ?? ($strict ? 0 : $defaultAddon['yearly'])), 2),
            'list_yearly' => round((float) ($rawAddon['list_yearly'] ?? ($strict ? 0 : $defaultAddon['list_yearly'])), 2),
            'description' => trim((string) ($rawAddon['description'] ?? $defaultAddon['description'])) ?: $defaultAddon['description'],
            'features' => [],
        ];

        $rawFeatures = $rawAddon['features'] ?? $defaultAddon['features'];
        if (is_string($rawFeatures)) {
            $rawFeatures = preg_split('/\r\n|\r|\n/', $rawFeatures);
        }

        if (! is_array($rawFeatures)) {
            $rawFeatures = $defaultAddon['features'];
        }

        foreach ($rawFeatures as $feature) {
            $feature = trim((string) $feature);
            if ($feature !== '') {
                $clean['addons'][$addonKey]['features'][] = $feature;
            }
        }

        if ($clean['addons'][$addonKey]['features'] === []) {
            $clean['addons'][$addonKey]['features'] = $defaultAddon['features'];
        }
    }

    return $clean;
}

function creditsoft_site_pricing_unavailable(string $reason): array
{
    return [
        'pricing_available' => false,
        'pricing_error' => $reason,
        'note' => '',
        'manual_payment_discount_percent' => 0,
        'plans' => [],
        'addons' => [],
    ];
}

function creditsoft_site_pricing_available(array $pricing): bool
{
    return ! empty($pricing['pricing_available'])
        && is_array($pricing['plans'] ?? null)
        && $pricing['plans'] !== [];
}

function creditsoft_site_pricing_seed_data(): array
{
    $path = creditsoft_site_pricing_storage_path();

    if (is_file($path)) {
        $decoded = json_decode((string) file_get_contents($path), true);

        if (is_array($decoded)) {
            return creditsoft_site_pricing_sanitize($decoded);
        }
    }

    return creditsoft_site_pricing_sanitize(creditsoft_site_pricing_defaults());
}

function creditsoft_site_pricing_load(): array
{
    static $cached = null;

    if (is_array($cached)) {
        return $cached;
    }

    $pdo = creditsoft_site_pricing_db();

    if (! $pdo instanceof PDO) {
        return $cached = creditsoft_site_pricing_unavailable('Pricing database is not available.');
    }

    try {
        creditsoft_site_pricing_ensure_table($pdo);
        $stmt = $pdo->prepare('SELECT pricing_json FROM site_pricing_config WHERE id = 1 LIMIT 1');
        $stmt->execute();
        $encoded = $stmt->fetchColumn();

        if (! is_string($encoded) || trim($encoded) === '') {
            return $cached = creditsoft_site_pricing_unavailable('Pricing database row is missing.');
        }

        $decoded = json_decode($encoded, true);

        if (! is_array($decoded)) {
            return $cached = creditsoft_site_pricing_unavailable('Pricing database row is invalid JSON.');
        }

        $pricing = creditsoft_site_pricing_sanitize($decoded, true);
        $pricing['pricing_available'] = true;
        $pricing['pricing_source'] = 'site_pricing_config';

        if ($pricing['plans'] === []) {
            return $cached = creditsoft_site_pricing_unavailable('Pricing database row has no active plans.');
        }

        return $cached = $pricing;
    } catch (Throwable $exception) {
        return $cached = creditsoft_site_pricing_unavailable('Pricing database read failed.');
    }
}

function creditsoft_site_pricing_clear_cache(): void
{
    // The load cache is request-scoped, so this helper exists for future explicit reload hooks.
}

function creditsoft_site_pricing_source_label(): string
{
    return 'MariaDB table: site_pricing_config';
}

function creditsoft_site_pricing_save(array $input): bool
{
    $clean = creditsoft_site_pricing_sanitize($input);
    $encoded = json_encode($clean, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    if (! is_string($encoded)) {
        return false;
    }

    $pdo = creditsoft_site_pricing_db();

    if (! $pdo instanceof PDO) {
        return false;
    }

    try {
        creditsoft_site_pricing_ensure_table($pdo);
        $stmt = $pdo->prepare(
            "INSERT INTO site_pricing_config (id, pricing_json, created_at, updated_at)
             VALUES (1, ?, NOW(), NOW())
             ON DUPLICATE KEY UPDATE pricing_json = VALUES(pricing_json), updated_at = NOW()"
        );

        return $stmt->execute([$encoded]);
    } catch (Throwable) {
        return false;
    }
}

function creditsoft_site_pricing_install_defaults(?array $seed = null): bool
{
    $clean = creditsoft_site_pricing_sanitize($seed ?? creditsoft_site_pricing_seed_data());
    $encoded = json_encode($clean, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    $pdo = creditsoft_site_pricing_db();

    if (! is_string($encoded) || ! $pdo instanceof PDO) {
        return false;
    }

    try {
        creditsoft_site_pricing_ensure_table($pdo);
        $stmt = $pdo->prepare(
            "INSERT INTO site_pricing_config (id, pricing_json, created_at, updated_at)
             VALUES (1, ?, NOW(), NOW())
             ON DUPLICATE KEY UPDATE pricing_json = VALUES(pricing_json), updated_at = NOW()"
        );

        return $stmt->execute([$encoded]);
    } catch (Throwable) {
        return false;
    }
}

function creditsoft_site_manual_payment_discount_percent(): int
{
    $pricing = creditsoft_site_pricing_load();

    return max(0, min(100, (int) ($pricing['manual_payment_discount_percent'] ?? 0)));
}

function creditsoft_site_manual_payment_amount(float $baseAmount, ?int $discountPercent = null): float
{
    $baseCents = (int) round(max(0.0, $baseAmount) * 100);
    $discount = $discountPercent ?? creditsoft_site_manual_payment_discount_percent();
    $discount = max(0, min(100, $discount));
    $paymentCents = intdiv($baseCents * (100 - $discount), 100);

    return round($paymentCents / 100, 2);
}

function creditsoft_site_checkout_normalize_addons(mixed $input): array
{
    if (is_string($input)) {
        $input = explode(',', $input);
    }

    if (! is_array($input)) {
        return [];
    }

    return array_values(array_unique(array_filter(array_map(
        static fn (mixed $value): string => str_replace(['-', ' '], '_', strtolower(trim((string) $value))),
        $input,
    ))));
}

function creditsoft_site_checkout_price_quote(string $planKey, string $billing = 'monthly', array $addonKeys = []): array
{
    $pricing = creditsoft_site_pricing_load();

    if (! creditsoft_site_pricing_available($pricing)) {
        return [
            'success' => false,
            'status' => 503,
            'error' => (string) ($pricing['pricing_error'] ?? 'Pricing is not available.'),
        ];
    }

    $publicPlanKey = creditsoft_site_public_plan_key($planKey);
    $billing = in_array($billing, ['monthly', 'yearly', 'lifetime'], true) ? $billing : 'monthly';
    $isClusterOnly = $publicPlanKey === 'cluster';

    if ($isClusterOnly && $billing === 'lifetime') {
        $billing = 'monthly';
    }

    if ($billing === 'lifetime') {
        return [
            'success' => false,
            'status' => 422,
            'error' => 'Lifetime interest pricing cannot create an automatic payment invoice.',
        ];
    }

    $plans = is_array($pricing['plans'] ?? null) ? $pricing['plans'] : [];
    $addons = is_array($pricing['addons'] ?? null) ? $pricing['addons'] : [];

    if ($isClusterOnly) {
        if (! is_array($addons['cluster'] ?? null)) {
            return [
                'success' => false,
                'status' => 422,
                'error' => 'Cluster pricing is not configured in the database.',
            ];
        }

        $selectedPlan = $addons['cluster'];
        $licensePlanKey = 'cluster';
        $selectedAddons = [];
        $planAmount = round((float) ($selectedPlan[$billing] ?? 0), 2);
        $planListAmount = round((float) ($selectedPlan['list_'.$billing] ?? 0), 2);
    } else {
        if (! is_array($plans[$publicPlanKey] ?? null)) {
            return [
                'success' => false,
                'status' => 422,
                'error' => 'Selected plan pricing is not configured in the database.',
            ];
        }

        $selectedPlan = $plans[$publicPlanKey];
        $licensePlanKey = creditsoft_site_license_plan_key($publicPlanKey);
        $selectedAddons = [];

        foreach (creditsoft_site_checkout_normalize_addons($addonKeys) as $addonKey) {
            if (is_array($addons[$addonKey] ?? null)) {
                $selectedAddons[$addonKey] = $addons[$addonKey];
            }
        }

        $planAmount = round((float) ($selectedPlan[$billing] ?? 0), 2);
        $planListAmount = round((float) ($selectedPlan['list_'.$billing] ?? 0), 2);
    }

    if ($planAmount <= 0) {
        return [
            'success' => false,
            'status' => 422,
            'error' => 'Selected plan has no payable database price.',
        ];
    }

    $addonAmount = 0.0;
    $addonListAmount = 0.0;

    foreach ($selectedAddons as $addon) {
        $addonAmount += round((float) ($addon[$billing] ?? 0), 2);
        $addonListAmount += round((float) ($addon['list_'.$billing] ?? 0), 2);
    }

    $baseAmount = round($planAmount + $addonAmount, 2);
    $listAmount = round($planListAmount + $addonListAmount, 2);
    $discountPercent = creditsoft_site_manual_payment_discount_percent();
    $manualPaymentAmount = creditsoft_site_manual_payment_amount($baseAmount, $discountPercent);
    $planName = (string) ($selectedPlan['name'] ?? 'CreditSoft');

    if (! $isClusterOnly && $selectedAddons !== []) {
        $addonNames = array_map(
            static fn (array $addon): string => (string) ($addon['short_name'] ?? $addon['name'] ?? 'Add-on'),
            array_values($selectedAddons),
        );
        $planName .= ' + ' . implode(' + ', $addonNames);
    }

    return [
        'success' => true,
        'pricing_source' => 'site_pricing_config',
        'public_plan_key' => $publicPlanKey,
        'license_plan_key' => $licensePlanKey,
        'checkout_plan_slug' => creditsoft_site_checkout_plan_slug($publicPlanKey),
        'billing' => $billing,
        'addons' => array_keys($selectedAddons),
        'selected_addons' => $selectedAddons,
        'plan_name' => $planName,
        'amount' => $manualPaymentAmount,
        'base_amount' => $baseAmount,
        'plan_amount' => $planAmount,
        'addon_amount' => round($addonAmount, 2),
        'list_amount' => $listAmount,
        'zelle_amount' => $manualPaymentAmount,
        'zelle_discount_percent' => $discountPercent,
        'pricing_snapshot' => [
            'source' => 'site_pricing_config',
            'loaded_at' => gmdate('c'),
            'manual_payment_discount_percent' => $discountPercent,
            'plan' => [
                'public_plan_key' => $publicPlanKey,
                'license_plan_key' => $licensePlanKey,
                'billing' => $billing,
                'name' => $planName,
                'amount' => $planAmount,
                'list_amount' => $planListAmount,
            ],
            'addons' => $selectedAddons,
            'invoice' => [
                'base_amount' => $baseAmount,
                'manual_payment_amount' => $manualPaymentAmount,
                'list_amount' => $listAmount,
            ],
        ],
    ];
}

function creditsoft_site_checkout_ensure_table(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `payment_checkout_requests` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `checkout_reference` VARCHAR(64) NOT NULL UNIQUE,
            `submitted_at` DATETIME NOT NULL,
            `public_plan_key` VARCHAR(64) NOT NULL,
            `license_plan_key` VARCHAR(64) NOT NULL,
            `billing` VARCHAR(32) NOT NULL,
            `addons_json` LONGTEXT NOT NULL,
            `plan_name` VARCHAR(255) NOT NULL,
            `amount` DECIMAL(10,2) DEFAULT NULL,
            `base_amount` DECIMAL(10,2) DEFAULT NULL,
            `plan_amount` DECIMAL(10,2) DEFAULT NULL,
            `addon_amount` DECIMAL(10,2) DEFAULT NULL,
            `list_amount` DECIMAL(10,2) DEFAULT NULL,
            `zelle_amount` DECIMAL(10,2) DEFAULT NULL,
            `zelle_discount_percent` TINYINT UNSIGNED NOT NULL DEFAULT 0,
            `payment_method` VARCHAR(32) NOT NULL,
            `customer_email` VARCHAR(255) NOT NULL,
            `customer_phone` VARCHAR(80) NOT NULL,
            `payment_source` VARCHAR(255) NOT NULL,
            `payment_amount_sent` DECIMAL(10,2) DEFAULT NULL,
            `payment_transaction_id` VARCHAR(255) DEFAULT NULL,
            `cash_app_request_id` VARCHAR(255) DEFAULT NULL,
            `cash_app_reference_id` VARCHAR(255) DEFAULT NULL,
            `cash_app_status` VARCHAR(64) DEFAULT NULL,
            `cash_app_mobile_url` TEXT DEFAULT NULL,
            `cash_app_desktop_url` TEXT DEFAULT NULL,
            `payment_memo_email` VARCHAR(255) DEFAULT NULL,
            `office_name` VARCHAR(255) DEFAULT NULL,
            `notes` TEXT DEFAULT NULL,
            `ip_address` VARCHAR(64) DEFAULT NULL,
            `user_agent` TEXT DEFAULT NULL,
            `pricing_snapshot_json` LONGTEXT NOT NULL,
            `archived_at` DATETIME DEFAULT NULL,
            `archive_reason` VARCHAR(255) DEFAULT NULL,
            `archived_by` VARCHAR(255) DEFAULT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `payment_checkout_requests_submitted_at_idx` (`submitted_at`),
            INDEX `payment_checkout_requests_customer_email_idx` (`customer_email`),
            INDEX `payment_checkout_requests_payment_method_idx` (`payment_method`),
            INDEX `payment_checkout_requests_transaction_idx` (`payment_transaction_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    try {
        $pdo->exec("ALTER TABLE `payment_checkout_requests` MODIFY `payment_amount_sent` DECIMAL(10,2) DEFAULT NULL");
    } catch (Throwable) {
        // Older hosts may already have the right shape or lack ALTER rights; inserts still surface real errors.
    }

    foreach ([
        'archived_at' => "ALTER TABLE `payment_checkout_requests` ADD COLUMN `archived_at` DATETIME DEFAULT NULL",
        'archive_reason' => "ALTER TABLE `payment_checkout_requests` ADD COLUMN `archive_reason` VARCHAR(255) DEFAULT NULL",
        'archived_by' => "ALTER TABLE `payment_checkout_requests` ADD COLUMN `archived_by` VARCHAR(255) DEFAULT NULL",
    ] as $column => $sql) {
        try {
            $exists = $pdo->prepare(
                'SELECT COUNT(*)
                 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = ?
                   AND COLUMN_NAME = ?'
            );
            $exists->execute(['payment_checkout_requests', $column]);

            if ((int) $exists->fetchColumn() === 0) {
                $pdo->exec($sql);
            }
        } catch (Throwable) {
            // Archive support is additive; payment checkout must keep working on constrained hosts.
        }
    }
}

function creditsoft_site_checkout_reference(PDO $pdo): string
{
    creditsoft_site_checkout_ensure_table($pdo);

    for ($attempt = 0; $attempt < 8; $attempt++) {
        $reference = 'CS-'.strtoupper(bin2hex(random_bytes(4)));
        $stmt = $pdo->prepare('SELECT id FROM payment_checkout_requests WHERE checkout_reference = ? LIMIT 1');
        $stmt->execute([$reference]);

        if (! $stmt->fetchColumn()) {
            return $reference;
        }
    }

    return 'CS-'.strtoupper(bin2hex(random_bytes(8)));
}

function creditsoft_site_checkout_rows(PDO $pdo, int $limit = 300): array
{
    creditsoft_site_checkout_ensure_table($pdo);
    $limit = max(1, min(1000, $limit));
    $stmt = $pdo->query(
        "SELECT *
         FROM payment_checkout_requests
         WHERE archived_at IS NULL
         ORDER BY submitted_at DESC, id DESC
         LIMIT ".$limit
    );
    $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

    if (! is_array($rows)) {
        return [];
    }

    foreach ($rows as &$row) {
        $addons = json_decode((string) ($row['addons_json'] ?? '[]'), true);
        $snapshot = json_decode((string) ($row['pricing_snapshot_json'] ?? '{}'), true);
        $row['add_ons'] = is_array($addons) ? $addons : [];
        $row['addons'] = [];

        foreach ($row['add_ons'] as $addon) {
            if (is_array($addon)) {
                $addonKey = (string) ($addon['source_key'] ?? $addon['key'] ?? '');
                if ($addonKey !== '') {
                    $row['addons'][] = $addonKey;
                }
            } elseif (is_string($addon) && trim($addon) !== '') {
                $row['addons'][] = trim($addon);
            }
        }

        $row['addons'] = array_values(array_unique($row['addons']));
        $row['pricing_snapshot'] = is_array($snapshot) ? $snapshot : [];
        $row['submitted_at'] = (string) ($row['submitted_at'] ?? '');
    }
    unset($row);

    return $rows;
}
