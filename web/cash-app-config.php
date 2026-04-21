<?php
declare(strict_types=1);

if (! defined('CREDITSOFT_SITE_CASH_APP_CONFIG_LOADED')) {
    define('CREDITSOFT_SITE_CASH_APP_CONFIG_LOADED', true);

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

if (! function_exists('creditsoft_site_cash_app_config_value')) {
    function creditsoft_site_cash_app_config_value(string $constant, string $env, string $default = ''): string
    {
        if (defined($constant)) {
            $value = trim((string) constant($constant));

            return $value !== '' ? $value : $default;
        }

        $value = getenv($env);

        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }

        return $default;
    }
}

if (! function_exists('creditsoft_site_cash_app_enabled')) {
    function creditsoft_site_cash_app_enabled(): bool
    {
        $enabled = creditsoft_site_cash_app_config_value(
            'CREDITSOFT_CASH_APP_PAY_ENABLED',
            'CASH_APP_PAY_ENABLED',
            'true',
        );

        return ! in_array(strtolower($enabled), ['0', 'false', 'off', 'no'], true);
    }
}

if (! function_exists('creditsoft_site_cash_app_configured')) {
    function creditsoft_site_cash_app_configured(): bool
    {
        return creditsoft_site_cash_app_enabled()
            && creditsoft_site_cash_app_config_value('CREDITSOFT_CASH_APP_PAY_CLIENT_ID', 'CASH_APP_PAY_CLIENT_ID') !== ''
            && creditsoft_site_cash_app_config_value('CREDITSOFT_CASH_APP_PAY_SCOPE_ID', 'CASH_APP_PAY_SCOPE_ID') !== '';
    }
}
