<?php
declare(strict_types=1);

function creditsoft_site_pricing_defaults(): array
{
    return [
        'note' => 'Early adopter pricing is already 25% off. Yearly billing adds another 20% discount.',
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
    return [
        'cluster' => [
            'name' => 'Cluster license',
            'short_name' => 'Cluster',
            'monthly' => 19.95,
            'list_monthly' => 29.95,
            'yearly' => 191.52,
            'list_yearly' => 359.40,
            'description' => 'Additional office install for a second office, branch, front desk machine, or dedicated local node.',
            'features' => [
                'Additional local office install',
                'Works with private networking and backup patterns',
                'Keeps ownership cleaner than forcing every location onto one box',
            ],
        ],
    ];
}

function creditsoft_site_pricing_sanitize(array $input): array
{
    $defaults = creditsoft_site_pricing_defaults();
    $clean = [
        'note' => trim((string) ($input['note'] ?? $defaults['note'])),
        'plans' => [],
    ];

    foreach ($defaults['plans'] as $planKey => $defaultPlan) {
        $rawPlan = $input['plans'][$planKey] ?? [];
        $clean['plans'][$planKey] = [
            'name' => trim((string) ($rawPlan['name'] ?? $defaultPlan['name'])) ?: $defaultPlan['name'],
            'featured' => ! empty($rawPlan['featured']),
            'sale_badge_monthly' => trim((string) ($rawPlan['sale_badge_monthly'] ?? $defaultPlan['sale_badge_monthly'])) ?: $defaultPlan['sale_badge_monthly'],
            'sale_badge_yearly' => trim((string) ($rawPlan['sale_badge_yearly'] ?? $defaultPlan['sale_badge_yearly'])) ?: $defaultPlan['sale_badge_yearly'],
            'sale_badge_lifetime' => trim((string) ($rawPlan['sale_badge_lifetime'] ?? $defaultPlan['sale_badge_lifetime'])) ?: $defaultPlan['sale_badge_lifetime'],
            'monthly' => round((float) ($rawPlan['monthly'] ?? $defaultPlan['monthly']), 2),
            'list_monthly' => round((float) ($rawPlan['list_monthly'] ?? $defaultPlan['list_monthly']), 2),
            'yearly' => round((float) ($rawPlan['yearly'] ?? $defaultPlan['yearly']), 2),
            'list_yearly' => round((float) ($rawPlan['list_yearly'] ?? $defaultPlan['list_yearly']), 2),
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

    return $clean;
}

function creditsoft_site_pricing_load(): array
{
    static $cached = null;

    if (is_array($cached)) {
        return $cached;
    }

    $defaults = creditsoft_site_pricing_defaults();
    $path = creditsoft_site_pricing_storage_path();

    if (! is_file($path)) {
        return $cached = $defaults;
    }

    $decoded = json_decode((string) file_get_contents($path), true);

    if (! is_array($decoded)) {
        return $cached = $defaults;
    }

    return $cached = creditsoft_site_pricing_sanitize($decoded);
}

function creditsoft_site_pricing_save(array $input): bool
{
    $clean = creditsoft_site_pricing_sanitize($input);
    $encoded = json_encode($clean, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    if (! is_string($encoded)) {
        return false;
    }

    $path = creditsoft_site_pricing_storage_path();
    $directory = dirname($path);

    if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
        return false;
    }

    return file_put_contents($path, $encoded . PHP_EOL) !== false;
}
