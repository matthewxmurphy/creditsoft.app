<?php
declare(strict_types=1);

function creditsoft_site_content_defaults(): array
{
    return [
        'home' => [
            'hero_badge' => 'Metro2-First Credit Repair',
            'hero_title_primary' => 'Compliance-First Credit Repair Platform',
            'hero_title_highlight' => '',
            'hero_copy' => 'Operate an AI-driven, local-first CRM and intranet, manage Metro2 cases correctly, and deploy a branded website seamlessly tied to intake, client portal, and automated updates.',
            'primary_cta_label' => 'Start Office Fit Check',
            'primary_cta_href' => '/subscribe',
            'secondary_cta_label' => 'Take the Red Flags Quiz',
            'secondary_cta_href' => '/lawsuit-test',
            'pricing_heading' => 'Packaging built for real offices.',
            'pricing_subtitle' => 'Choose the software plan that fits your workflow, then add the browser companion, branded website, or legal-intake add-on only if you need them.',
            'pricing_note' => 'The homepage stays focused on the product. Full plan details, discounts, and add-ons live on the pricing page.',
            'fit_check_heading' => 'Start the office fit check.',
            'fit_check_subtitle' => 'The homepage should not end in a random waitlist box. Start here, then we take you to the second step where we learn how your office works right now.',
            'fit_check_intro' => 'Give us the basic office contact here. The next screen handles the real qualification questions.',
        ],
        'pricing' => [
            'eyebrow' => 'Pricing',
            'title' => 'Simple pricing for the CreditSoft office system.',
            'subtitle' => 'Start with the core software plan. Add the browser companion, extra office installs, legal-intake tools, or a managed website only when the office needs them.',
            'note' => 'Prices update from the same admin-controlled pricing file used by checkout.',
            'support_card_one_title' => 'Core office software',
            'support_card_one_text' => 'Local-first CRM and intranet workflows for Metro2 review, letters, briefs, audit trails, client portals, and office operations.',
            'support_card_two_title' => 'Browser companion',
            'support_card_two_text' => 'Office-paired browser automation for supported provider imports, direct API capture routing, and less manual intake work.',
            'support_card_three_title' => 'Managed websites',
            'support_card_three_text' => 'Branded public sites tied into CreditSoft CRM, portal, intake, and follow-up instead of a disconnected brochure.',
        ],
    ];
}

function creditsoft_site_content_storage_path(): string
{
    return dirname(__DIR__) . '/web-meta/site-content.json';
}

function creditsoft_site_content_sanitize(array $input): array
{
    $defaults = creditsoft_site_content_defaults();
    $clean = [];

    foreach ($defaults as $sectionKey => $sectionValues) {
        $clean[$sectionKey] = [];

        foreach ($sectionValues as $fieldKey => $defaultValue) {
            $value = $input[$sectionKey][$fieldKey] ?? $defaultValue;
            $value = trim((string) $value);
            $clean[$sectionKey][$fieldKey] = $value !== '' ? $value : $defaultValue;
        }
    }

    return $clean;
}

function creditsoft_site_content_load(): array
{
    static $cached = null;

    if (is_array($cached)) {
        return $cached;
    }

    $defaults = creditsoft_site_content_defaults();
    $path = creditsoft_site_content_storage_path();

    if (! is_file($path)) {
        return $cached = $defaults;
    }

    $decoded = json_decode((string) file_get_contents($path), true);

    if (! is_array($decoded)) {
        return $cached = $defaults;
    }

    return $cached = creditsoft_site_content_sanitize($decoded);
}

function creditsoft_site_content_save(array $input): bool
{
    $clean = creditsoft_site_content_sanitize($input);
    $encoded = json_encode($clean, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    if (! is_string($encoded)) {
        return false;
    }

    $path = creditsoft_site_content_storage_path();
    $directory = dirname($path);

    if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
        return false;
    }

    return file_put_contents($path, $encoded . PHP_EOL) !== false;
}
