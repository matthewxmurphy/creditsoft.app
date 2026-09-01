<?php
declare(strict_types=1);

/**
 * Zelle and Cash App payment automation for the public CreditSoft admin.
 *
 * The mailbox password intentionally lives in the private credit_config.php or
 * server environment, not in this public web-root file.
 */

function cs_site_zelle_config_value(string $constant, string $env, ?string $default = null): ?string
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

function cs_site_zelle_mailbox_config(): array
{
    return [
        'host' => cs_site_zelle_config_value('CREDITSOFT_ZELLE_IMAP_HOST', 'CREDITSOFT_ZELLE_IMAP_HOST', 'mail.creditsoft.app'),
        'port' => (int) (cs_site_zelle_config_value('CREDITSOFT_ZELLE_IMAP_PORT', 'CREDITSOFT_ZELLE_IMAP_PORT', '993') ?: 993),
        'username' => cs_site_zelle_config_value('CREDITSOFT_ZELLE_IMAP_USERNAME', 'CREDITSOFT_ZELLE_IMAP_USERNAME', 'z@creditsoft.app'),
        'password' => cs_site_zelle_config_value('CREDITSOFT_ZELLE_IMAP_PASSWORD', 'CREDITSOFT_ZELLE_IMAP_PASSWORD'),
        'folder' => cs_site_zelle_config_value('CREDITSOFT_ZELLE_IMAP_FOLDER', 'CREDITSOFT_ZELLE_IMAP_FOLDER', 'INBOX'),
        'from_email' => cs_site_zelle_config_value('CREDITSOFT_ZELLE_FROM_EMAIL', 'CREDITSOFT_ZELLE_FROM_EMAIL', 'hello@creditsoft.app'),
        'from_name' => cs_site_zelle_config_value('CREDITSOFT_ZELLE_FROM_NAME', 'CREDITSOFT_ZELLE_FROM_NAME', 'CreditSoft'),
        'cron_token' => cs_site_zelle_config_value('CREDITSOFT_ZELLE_CRON_TOKEN', 'CREDITSOFT_ZELLE_CRON_TOKEN'),
    ];
}

function cs_site_zelle_smtp_config(): array
{
    $imap = cs_site_zelle_mailbox_config();
    $host = cs_site_zelle_config_value('CREDITSOFT_SMTP_HOST', 'CREDITSOFT_SMTP_HOST')
        ?: cs_site_zelle_config_value('CREDITSOFT_ZELLE_SMTP_HOST', 'CREDITSOFT_ZELLE_SMTP_HOST')
        ?: (string) ($imap['host'] ?? '');
    $port = (int) (
        cs_site_zelle_config_value('CREDITSOFT_SMTP_PORT', 'CREDITSOFT_SMTP_PORT')
        ?: cs_site_zelle_config_value('CREDITSOFT_ZELLE_SMTP_PORT', 'CREDITSOFT_ZELLE_SMTP_PORT')
        ?: '465'
    );
    $username = cs_site_zelle_config_value('CREDITSOFT_SMTP_USERNAME', 'CREDITSOFT_SMTP_USERNAME')
        ?: cs_site_zelle_config_value('CREDITSOFT_ZELLE_SMTP_USERNAME', 'CREDITSOFT_ZELLE_SMTP_USERNAME')
        ?: (string) ($imap['username'] ?? '');
    $password = cs_site_zelle_config_value('CREDITSOFT_SMTP_PASSWORD', 'CREDITSOFT_SMTP_PASSWORD')
        ?: cs_site_zelle_config_value('CREDITSOFT_ZELLE_SMTP_PASSWORD', 'CREDITSOFT_ZELLE_SMTP_PASSWORD')
        ?: (string) ($imap['password'] ?? '');
    $security = strtolower((string) (
        cs_site_zelle_config_value('CREDITSOFT_SMTP_SECURITY', 'CREDITSOFT_SMTP_SECURITY')
        ?: cs_site_zelle_config_value('CREDITSOFT_ZELLE_SMTP_SECURITY', 'CREDITSOFT_ZELLE_SMTP_SECURITY')
        ?: ($port === 465 ? 'ssl' : 'tls')
    ));

    return [
        'host' => trim($host),
        'port' => $port > 0 ? $port : 587,
        'username' => trim($username),
        'password' => $password,
        'security' => in_array($security, ['ssl', 'tls', 'none'], true) ? $security : 'tls',
    ];
}

function cs_site_zelle_mail_error(?string $message = null): string
{
    static $lastError = '';

    if ($message !== null) {
        $lastError = cs_site_zelle_clean_text($message, 500);
    }

    return $lastError;
}

function cs_site_zelle_money(?float $amount): string
{
    return $amount === null ? '-' : '$'.number_format($amount, 2);
}

function cs_site_zelle_payment_provider_key(?string $value): string
{
    $value = strtolower(trim((string) $value));
    $value = str_replace(['-', ' '], '_', $value);

    return match ($value) {
        'cashapp', 'cash_app', 'cash', 'square', 'square_cash' => 'cash_app',
        'zelle' => 'zelle',
        default => $value !== '' ? $value : 'zelle',
    };
}

function cs_site_zelle_payment_provider_label(?string $value): string
{
    $key = cs_site_zelle_payment_provider_key($value);

    return match ($key) {
        'cash_app' => 'Cash App',
        'zelle' => 'Zelle',
        default => cs_site_zelle_label_from_key($key) ?: 'Payment',
    };
}

function cs_site_zelle_cash_app_fee_percent(): float
{
    $configured = cs_site_zelle_config_value(
        'CREDITSOFT_CASH_APP_BUSINESS_FEE_PERCENT',
        'CREDITSOFT_CASH_APP_BUSINESS_FEE_PERCENT',
        '2.7'
    );
    $percent = is_numeric($configured) ? (float) $configured : 2.7;

    return max(0.0, min(20.0, $percent));
}

function cs_site_zelle_payment_fee_amount(string $provider, float $amount): float
{
    if (cs_site_zelle_payment_provider_key($provider) !== 'cash_app' || $amount <= 0) {
        return 0.0;
    }

    return round($amount * (cs_site_zelle_cash_app_fee_percent() / 100), 2);
}

function cs_site_zelle_clean_text(?string $value, int $max = 255): string
{
    $value = trim(preg_replace('/\s+/', ' ', (string) $value) ?: '');

    return $value === '' ? '' : mb_substr($value, 0, $max);
}

function cs_site_zelle_clean_sender_display_name(?string $name): string
{
    $name = cs_site_zelle_clean_text($name, 255);

    if ($name === '') {
        return '';
    }

    $name = preg_replace('/^(?:zelle\s+)?payment\s+(?:from\s+)?/i', '', $name) ?: $name;
    $name = preg_replace('/^(?:from|sender|paid by)\s*:?\s+/i', '', $name) ?: $name;
    $name = preg_replace('/\s+(?:sent|paid)\s+you(?:\s+money)?$/i', '', $name) ?: $name;

    return cs_site_zelle_clean_text($name, 255);
}

function cs_site_zelle_label_from_key(?string $value): string
{
    $value = cs_site_zelle_clean_text(str_replace(['_', '-'], ' ', (string) $value), 120);

    return $value === '' ? '' : ucwords($value);
}

function cs_site_zelle_normalize_email(?string $email): string
{
    $email = strtolower(trim((string) $email));

    return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
}

function cs_site_zelle_normalize_phone(?string $phone): string
{
    return preg_replace('/\D+/', '', (string) $phone) ?: '';
}

function cs_site_zelle_normalize_name(?string $name): string
{
    $name = strtolower(trim((string) $name));
    $name = preg_replace('/[^a-z0-9\s]+/', ' ', $name) ?: '';

    return trim(preg_replace('/\s+/', ' ', $name) ?: '');
}

function cs_site_zelle_normalize_transaction_id(?string $value): string
{
    return preg_replace('/[^A-Z0-9]+/', '', strtoupper(trim((string) $value))) ?: '';
}

function cs_site_zelle_meta_path(string $filename): string
{
    return dirname(__DIR__, 2) . '/web-meta/' . ltrim($filename, '/');
}

function cs_site_zelle_alias_rows_from_value(?string $raw): array
{
    $raw = trim((string) $raw);

    if ($raw === '') {
        return [];
    }

    $json = json_decode($raw, true);
    if (is_array($json)) {
        return $json;
    }

    $rows = [];
    foreach (preg_split('/\r\n|\r|\n|;/', $raw) ?: [] as $line) {
        $line = trim((string) $line);
        if ($line === '') {
            continue;
        }

        $parts = preg_split('/\s*(?:=>|=|\|)\s*/', $line, 2);
        if (is_array($parts) && count($parts) === 2) {
            $rows[trim((string) $parts[0])] = trim((string) $parts[1]);
        }
    }

    return $rows;
}

function cs_site_zelle_payer_email_aliases(): array
{
    $rows = [];
    $configured = cs_site_zelle_config_value(
        'CREDITSOFT_ZELLE_PAYER_EMAIL_ALIASES',
        'CREDITSOFT_ZELLE_PAYER_EMAIL_ALIASES',
        ''
    );

    foreach (cs_site_zelle_alias_rows_from_value($configured) as $name => $email) {
        $rows[(string) $name] = (string) $email;
    }

    $aliasPath = cs_site_zelle_meta_path('zelle-payer-aliases.json');
    if (is_file($aliasPath)) {
        $decoded = json_decode((string) file_get_contents($aliasPath), true);
        if (is_array($decoded)) {
            foreach ($decoded as $name => $email) {
                $rows[(string) $name] = (string) $email;
            }
        }
    }

    $aliases = [];
    foreach ($rows as $name => $email) {
        $normalizedName = cs_site_zelle_normalize_name((string) $name);
        $normalizedEmail = cs_site_zelle_normalize_email((string) $email);

        if ($normalizedName !== '' && $normalizedEmail !== '') {
            $aliases[$normalizedName] = $normalizedEmail;
        }
    }

    return $aliases;
}

function cs_site_zelle_alias_email_for_name(string $name): string
{
    $name = cs_site_zelle_normalize_name($name);

    if ($name === '') {
        return '';
    }

    foreach (cs_site_zelle_payer_email_aliases() as $aliasName => $email) {
        if ($name === $aliasName || str_contains($name, $aliasName) || str_contains($aliasName, $name)) {
            return $email;
        }
    }

    return '';
}

function cs_site_zelle_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
    $stmt->execute([$table]);

    return (bool) $stmt->fetchColumn();
}

function cs_site_zelle_column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?
           AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);

    return (int) $stmt->fetchColumn() > 0;
}

function cs_site_zelle_ensure_license_tables(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `licenses` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `license_key` VARCHAR(128) NOT NULL UNIQUE,
            `customer_email` VARCHAR(255) NOT NULL,
            `customer_name` VARCHAR(255) DEFAULT NULL,
            `plan` VARCHAR(64) NOT NULL DEFAULT 'enterprise',
            `status` VARCHAR(32) NOT NULL DEFAULT 'active',
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `expires_at` DATETIME DEFAULT NULL,
            `last_validated` DATETIME DEFAULT NULL,
            `domain` VARCHAR(255) DEFAULT NULL,
            `ip_address` VARCHAR(64) DEFAULT NULL,
            `archived_at` DATETIME DEFAULT NULL,
            `archive_reason` VARCHAR(255) DEFAULT NULL,
            `archived_by` VARCHAR(255) DEFAULT NULL,
            INDEX `idx_customer_email` (`customer_email`),
            INDEX `idx_license_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `license_subscriptions` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `license_id` INT NOT NULL,
            `billing_cycle` VARCHAR(32) NOT NULL DEFAULT 'monthly',
            `amount` DECIMAL(10,2) DEFAULT NULL,
            `next_billing` DATETIME DEFAULT NULL,
            `auto_renew` TINYINT(1) NOT NULL DEFAULT 0,
            `last_payment_at` DATETIME DEFAULT NULL,
            `last_payment_status` VARCHAR(32) DEFAULT NULL,
            `failed_attempts` INT NOT NULL DEFAULT 0,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `uniq_license_id` (`license_id`),
            INDEX `idx_next_billing` (`next_billing`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `license_logs` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `license_id` INT DEFAULT NULL,
            `action` VARCHAR(80) NOT NULL,
            `ip_address` VARCHAR(64) DEFAULT NULL,
            `details` TEXT DEFAULT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_license_id` (`license_id`),
            INDEX `idx_action` (`action`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    if (cs_site_zelle_column_exists($pdo, 'license_subscriptions', 'last_payment_status')) {
        try {
            $pdo->exec("ALTER TABLE `license_subscriptions` MODIFY COLUMN `last_payment_status` VARCHAR(32) DEFAULT NULL");
        } catch (Throwable) {
            // Existing hosts may already be correct or may restrict ALTER; payment processing can still continue without exposing the schema error.
        }
    }

    if (cs_site_zelle_column_exists($pdo, 'license_logs', 'action')) {
        try {
            $pdo->exec("ALTER TABLE `license_logs` MODIFY COLUMN `action` VARCHAR(80) NOT NULL");
        } catch (Throwable) {
            // Same compatibility guard for older admin schemas.
        }
    }

    foreach ([
        'archived_at' => "ALTER TABLE `licenses` ADD COLUMN `archived_at` DATETIME DEFAULT NULL",
        'archive_reason' => "ALTER TABLE `licenses` ADD COLUMN `archive_reason` VARCHAR(255) DEFAULT NULL",
        'archived_by' => "ALTER TABLE `licenses` ADD COLUMN `archived_by` VARCHAR(255) DEFAULT NULL",
    ] as $column => $sql) {
        if (! cs_site_zelle_column_exists($pdo, 'licenses', $column)) {
            try {
                $pdo->exec($sql);
            } catch (Throwable) {
                // Archive columns are additive; old payment processing can continue if ALTER is restricted.
            }
        }
    }
}

function cs_site_zelle_ensure_tables(PDO $pdo): void
{
    cs_site_zelle_ensure_license_tables($pdo);

    if (function_exists('creditsoft_lead_ensure_qualification_table')) {
        creditsoft_lead_ensure_qualification_table($pdo);
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `zelle_payment_messages` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `mailbox` VARCHAR(255) NOT NULL,
            `payment_provider` VARCHAR(32) NOT NULL DEFAULT 'zelle',
            `message_uid` VARCHAR(191) NOT NULL,
            `message_id` VARCHAR(255) DEFAULT NULL,
            `received_at` DATETIME DEFAULT NULL,
            `from_name` VARCHAR(255) DEFAULT NULL,
            `from_email` VARCHAR(255) DEFAULT NULL,
            `subject` VARCHAR(500) DEFAULT NULL,
            `body_excerpt` TEXT DEFAULT NULL,
            `amount` DECIMAL(10,2) DEFAULT NULL,
            `sender_name` VARCHAR(255) DEFAULT NULL,
            `sender_email` VARCHAR(255) DEFAULT NULL,
            `sender_phone` VARCHAR(64) DEFAULT NULL,
            `plan_key` VARCHAR(64) DEFAULT NULL,
            `billing` VARCHAR(32) DEFAULT NULL,
            `status` VARCHAR(32) NOT NULL DEFAULT 'new',
            `match_type` VARCHAR(64) DEFAULT NULL,
            `lead_id` INT DEFAULT NULL,
            `license_id` INT DEFAULT NULL,
            `license_key` VARCHAR(128) DEFAULT NULL,
            `onboarding_url` VARCHAR(1000) DEFAULT NULL,
            `email_sent_at` DATETIME DEFAULT NULL,
            `metadata_json` LONGTEXT DEFAULT NULL,
            `processed_at` DATETIME DEFAULT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `uniq_mailbox_uid` (`mailbox`, `message_uid`),
            INDEX `idx_status` (`status`),
            INDEX `idx_sender_email` (`sender_email`),
            INDEX `idx_license_id` (`license_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    foreach ([
        'transaction_id' => "ALTER TABLE `zelle_payment_messages` ADD COLUMN `transaction_id` VARCHAR(255) DEFAULT NULL AFTER `message_id`",
        'payment_provider' => "ALTER TABLE `zelle_payment_messages` ADD COLUMN `payment_provider` VARCHAR(32) NOT NULL DEFAULT 'zelle' AFTER `mailbox`",
        'expected_amount' => "ALTER TABLE `zelle_payment_messages` ADD COLUMN `expected_amount` DECIMAL(10,2) DEFAULT NULL AFTER `amount`",
        'balance_due' => "ALTER TABLE `zelle_payment_messages` ADD COLUMN `balance_due` DECIMAL(10,2) DEFAULT NULL AFTER `expected_amount`",
        'payment_status' => "ALTER TABLE `zelle_payment_messages` ADD COLUMN `payment_status` VARCHAR(32) DEFAULT NULL AFTER `status`",
        'email_tracking_token' => "ALTER TABLE `zelle_payment_messages` ADD COLUMN `email_tracking_token` VARCHAR(80) DEFAULT NULL AFTER `onboarding_url`",
        'balance_email_sent_at' => "ALTER TABLE `zelle_payment_messages` ADD COLUMN `balance_email_sent_at` DATETIME DEFAULT NULL AFTER `email_sent_at`",
        'email_opened_at' => "ALTER TABLE `zelle_payment_messages` ADD COLUMN `email_opened_at` DATETIME DEFAULT NULL AFTER `balance_email_sent_at`",
        'email_last_opened_at' => "ALTER TABLE `zelle_payment_messages` ADD COLUMN `email_last_opened_at` DATETIME DEFAULT NULL AFTER `email_opened_at`",
        'email_open_count' => "ALTER TABLE `zelle_payment_messages` ADD COLUMN `email_open_count` INT NOT NULL DEFAULT 0 AFTER `email_last_opened_at`",
        'email_last_attempt_at' => "ALTER TABLE `zelle_payment_messages` ADD COLUMN `email_last_attempt_at` DATETIME DEFAULT NULL AFTER `email_open_count`",
        'email_attempt_count' => "ALTER TABLE `zelle_payment_messages` ADD COLUMN `email_attempt_count` INT NOT NULL DEFAULT 0 AFTER `email_last_attempt_at`",
        'email_last_error' => "ALTER TABLE `zelle_payment_messages` ADD COLUMN `email_last_error` VARCHAR(500) DEFAULT NULL AFTER `email_attempt_count`",
        'archived_at' => "ALTER TABLE `zelle_payment_messages` ADD COLUMN `archived_at` DATETIME DEFAULT NULL",
        'archive_reason' => "ALTER TABLE `zelle_payment_messages` ADD COLUMN `archive_reason` VARCHAR(255) DEFAULT NULL",
        'archived_by' => "ALTER TABLE `zelle_payment_messages` ADD COLUMN `archived_by` VARCHAR(255) DEFAULT NULL",
    ] as $column => $sql) {
        if (! cs_site_zelle_column_exists($pdo, 'zelle_payment_messages', $column)) {
            $pdo->exec($sql);
        }
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `customer_onboarding_tokens` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `lead_id` INT DEFAULT NULL,
            `license_id` INT DEFAULT NULL,
            `email` VARCHAR(255) NOT NULL,
            `token_hash` CHAR(64) NOT NULL UNIQUE,
            `purpose` VARCHAR(80) NOT NULL DEFAULT 'finish_onboarding',
            `expires_at` DATETIME NOT NULL,
            `used_at` DATETIME DEFAULT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_email` (`email`),
            INDEX `idx_lead_id` (`lead_id`),
            INDEX `idx_license_id` (`license_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `payment_support_tickets` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `ticket_number` VARCHAR(64) NOT NULL UNIQUE,
            `customer_name` VARCHAR(255) DEFAULT NULL,
            `customer_email` VARCHAR(255) NOT NULL,
            `customer_phone` VARCHAR(80) DEFAULT NULL,
            `amount` DECIMAL(10,2) DEFAULT NULL,
            `payment_date` DATE DEFAULT NULL,
            `payment_source` VARCHAR(255) DEFAULT NULL,
            `payer_name` VARCHAR(255) DEFAULT NULL,
            `memo_used` VARCHAR(500) DEFAULT NULL,
            `transaction_id` VARCHAR(255) DEFAULT NULL,
            `notes` TEXT DEFAULT NULL,
            `attachment_path` VARCHAR(1000) DEFAULT NULL,
            `attachment_original_name` VARCHAR(255) DEFAULT NULL,
            `attachment_mime` VARCHAR(120) DEFAULT NULL,
            `attachment_size` INT DEFAULT NULL,
            `attachment_download_token` VARCHAR(80) DEFAULT NULL,
            `status` VARCHAR(32) NOT NULL DEFAULT 'new',
            `ip_address` VARCHAR(64) DEFAULT NULL,
            `user_agent` VARCHAR(500) DEFAULT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_customer_email` (`customer_email`),
            INDEX `idx_status` (`status`),
            INDEX `idx_created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    if (! cs_site_zelle_column_exists($pdo, 'payment_support_tickets', 'attachment_download_token')) {
        $pdo->exec("ALTER TABLE `payment_support_tickets` ADD COLUMN `attachment_download_token` VARCHAR(80) DEFAULT NULL AFTER `attachment_size`");
    }
}

function cs_site_zelle_message_seen(PDO $pdo, string $mailbox, string $uid): bool
{
    $stmt = $pdo->prepare('SELECT id FROM zelle_payment_messages WHERE mailbox = ? AND message_uid = ? LIMIT 1');
    $stmt->execute([$mailbox, $uid]);

    return (bool) $stmt->fetchColumn();
}

function cs_site_zelle_decode_header(?string $value): string
{
    $value = (string) $value;

    if ($value === '' || ! function_exists('imap_mime_header_decode')) {
        return $value;
    }

    $parts = @imap_mime_header_decode($value);

    if (! is_array($parts)) {
        return $value;
    }

    $decoded = '';

    foreach ($parts as $part) {
        $charset = strtoupper((string) ($part->charset ?? 'UTF-8'));
        $text = (string) ($part->text ?? '');
        if ($charset !== 'DEFAULT' && $charset !== 'UTF-8' && function_exists('mb_convert_encoding')) {
            $text = mb_convert_encoding($text, 'UTF-8', $charset);
        }
        $decoded .= $text;
    }

    return $decoded;
}

function cs_site_zelle_parse_address(?string $raw): array
{
    $raw = cs_site_zelle_decode_header($raw);
    $email = '';
    $name = trim($raw);

    if (preg_match('/<([^>]+)>/', $raw, $match) === 1) {
        $email = cs_site_zelle_normalize_email($match[1]);
        $name = trim(str_replace($match[0], '', $raw), " \t\n\r\0\x0B\"'");
    } elseif (preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $raw, $match) === 1) {
        $email = cs_site_zelle_normalize_email($match[0]);
        $name = trim(str_replace($match[0], '', $raw), " \t\n\r\0\x0B\"'<>");
    }

    return [
        'name' => cs_site_zelle_clean_text($name, 255),
        'email' => $email,
    ];
}

function cs_site_zelle_trusted_domains(): array
{
    $raw = cs_site_zelle_config_value(
        'CREDITSOFT_ZELLE_TRUSTED_DOMAINS',
        'CREDITSOFT_ZELLE_TRUSTED_DOMAINS',
        'zelle.com,zellepay.com,chase.com,jpmorgan.com'
    );

    $domains = array_values(array_unique(array_filter(array_map(
        static fn (string $domain): string => strtolower(ltrim(trim($domain), '@.')),
        explode(',', (string) $raw),
    ))));

    return array_values(array_unique(array_merge($domains, cs_site_zelle_cash_app_trusted_domains())));
}

function cs_site_zelle_cash_app_trusted_domains(): array
{
    return ['cash.app', 'square.com', 'squareup.com', 'block.xyz'];
}

function cs_site_zelle_trusted_forwarder_emails(): array
{
    $raw = cs_site_zelle_config_value(
        'CREDITSOFT_PAYMENT_FORWARDER_EMAILS',
        'CREDITSOFT_PAYMENT_FORWARDER_EMAILS',
        'hsitech@gmail.com'
    );

    return array_values(array_unique(array_filter(array_map(
        'cs_site_zelle_normalize_email',
        preg_split('/[\s,;]+/', (string) $raw) ?: [],
    ))));
}

function cs_site_zelle_is_trusted_forwarder_email(string $email): bool
{
    $email = cs_site_zelle_normalize_email($email);

    return $email !== '' && in_array($email, cs_site_zelle_trusted_forwarder_emails(), true);
}

function cs_site_zelle_zelle_trusted_domains(): array
{
    return ['zelle.com', 'zellepay.com', 'chase.com', 'jpmorgan.com'];
}

function cs_site_zelle_expected_subject(): string
{
    return cs_site_zelle_config_value(
        'CREDITSOFT_ZELLE_EXPECTED_SUBJECT',
        'CREDITSOFT_ZELLE_EXPECTED_SUBJECT',
        'You received money with Zelle®'
    ) ?: 'You received money with Zelle®';
}

function cs_site_zelle_normalize_subject(string $subject): string
{
    $subject = strtolower(trim($subject));
    $subject = str_replace(['®', '(r)', '&reg;'], '', $subject);
    $subject = preg_replace('/^(?:re|fw|fwd)\s*:\s*/i', '', $subject) ?: $subject;

    return trim(preg_replace('/\s+/', ' ', $subject) ?: '');
}

function cs_site_zelle_subject_is_expected(string $subject): bool
{
    $actual = cs_site_zelle_normalize_subject($subject);
    $expected = cs_site_zelle_normalize_subject(cs_site_zelle_expected_subject());

    if ($actual === '' || $expected === '') {
        return false;
    }

    return $actual === $expected || str_contains($actual, $expected);
}

function cs_site_zelle_provider_domain_candidates(string $fromEmail, array $headerTrust): array
{
    return array_values(array_unique(array_filter(array_merge(
        [cs_site_zelle_email_domain($fromEmail)],
        [(string) ($headerTrust['from_domain'] ?? '')],
        [(string) ($headerTrust['return_path_domain'] ?? '')],
        array_map('strval', (array) ($headerTrust['dkim_domains'] ?? [])),
    ))));
}

function cs_site_zelle_normalize_forwarded_text(string $value): string
{
    $value = preg_replace('/\p{Cf}+/u', '', $value) ?: $value;
    $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    return preg_replace('/\s+/', ' ', $value) ?: '';
}

function cs_site_zelle_forwarded_payment_domains(string $subject, string $body): array
{
    $text = cs_site_zelle_normalize_forwarded_text($subject . "\n" . $body);
    $domains = [];

    if (preg_match_all('/[A-Z0-9._%+\-]+@([A-Z0-9.\-]+\.[A-Z]{2,})/i', $text, $matches) !== false) {
        foreach ($matches[1] ?? [] as $domain) {
            $domains[] = strtolower(ltrim((string) $domain, '@.'));
        }
    }

    foreach (cs_site_zelle_trusted_domains() as $domain) {
        if ($domain !== '' && preg_match('/(?:^|[^a-z0-9.-])' . preg_quote($domain, '/') . '(?:$|[^a-z0-9.-])/i', $text) === 1) {
            $domains[] = $domain;
        }
    }

    if (preg_match('/\bfrom:\s*(?:cash\s*app|square|zelle)\b/i', $text) === 1) {
        if (preg_match('/\b(?:cash\s*app|cashapp)\b/i', $text) === 1) {
            $domains[] = 'cash.app';
        }

        if (preg_match('/\bsquare\b/i', $text) === 1) {
            $domains[] = 'square.com';
        }

        if (preg_match('/\bzelle\b/i', $text) === 1) {
            $domains[] = 'zelle.com';
        }
    }

    return array_values(array_unique(array_filter($domains)));
}

function cs_site_zelle_forwarded_payment_origin(string $subject, string $body): array
{
    $domains = cs_site_zelle_forwarded_payment_domains($subject, $body);
    $cashAppOrigin = cs_site_zelle_any_domain_matches($domains, cs_site_zelle_cash_app_trusted_domains());
    $zelleOrigin = cs_site_zelle_any_domain_matches($domains, cs_site_zelle_zelle_trusted_domains());

    if (! $cashAppOrigin && ! $zelleOrigin) {
        return [
            'trusted' => false,
            'domains' => $domains,
            'provider' => '',
        ];
    }

    $text = strtolower(cs_site_zelle_normalize_forwarded_text($subject . ' ' . $body));
    $hasPaymentLanguage = preg_match('/\b(?:payment|paid|sent|received|accept or decline)\b/i', $text) === 1
        && preg_match('/\$\s*[0-9]{1,6}(?:,[0-9]{3})*(?:\.[0-9]{2})?/', $text) === 1;

    return [
        'trusted' => $hasPaymentLanguage,
        'domains' => $domains,
        'provider' => $cashAppOrigin ? 'cash_app' : 'zelle',
    ];
}

function cs_site_zelle_apply_forwarded_payment_trust(array $headerTrust, string $subject, string $body, string $fromEmail): array
{
    if (! cs_site_zelle_is_trusted_forwarder_email($fromEmail)) {
        return $headerTrust;
    }

    $origin = cs_site_zelle_forwarded_payment_origin($subject, $body);
    if (empty($origin['trusted'])) {
        return $headerTrust;
    }

    $headerTrust['trusted'] = true;
    $headerTrust['status_label'] = 'Trusted forwarded payment';
    $headerTrust['trusted_forwarder'] = cs_site_zelle_normalize_email($fromEmail);
    $headerTrust['forwarded_payment_origin'] = $origin;
    $headerTrust['dkim_domains'] = array_values(array_unique(array_merge(
        array_map('strval', (array) ($headerTrust['dkim_domains'] ?? [])),
        array_map('strval', (array) ($origin['domains'] ?? [])),
    )));
    $headerTrust['reasons'] = array_values(array_unique(array_merge(
        array_map('strval', (array) ($headerTrust['reasons'] ?? [])),
        ['Trusted forwarder', 'Forwarded payment origin matched'],
    )));

    return $headerTrust;
}

function cs_site_zelle_any_domain_matches(array $candidateDomains, array $trustedDomains): bool
{
    foreach ($candidateDomains as $candidateDomain) {
        if (cs_site_zelle_domain_is_trusted((string) $candidateDomain, $trustedDomains)) {
            return true;
        }
    }

    return false;
}

function cs_site_zelle_detect_payment_provider(string $subject, string $body = '', string $fromEmail = '', array $headerTrust = []): array
{
    $text = strtolower(preg_replace('/\s+/', ' ', $subject . ' ' . $body) ?: '');
    $candidateDomains = array_values(array_unique(array_filter(array_merge(
        cs_site_zelle_provider_domain_candidates($fromEmail, $headerTrust),
        cs_site_zelle_forwarded_payment_domains($subject, $body),
    ))));
    $cashAppDomain = cs_site_zelle_any_domain_matches($candidateDomains, cs_site_zelle_cash_app_trusted_domains());
    $zelleDomain = cs_site_zelle_any_domain_matches($candidateDomains, cs_site_zelle_zelle_trusted_domains());
    $cashAppSignal = str_contains($text, 'cash app')
        || str_contains($text, 'cashapp')
        || str_contains($text, 'cashtag')
        || str_contains($text, '$cashtag')
        || (str_contains($text, 'square') && preg_match('/\b(payment|paid|sent|received)\b/i', $text) === 1);
    $genericPaymentSignal = preg_match('/\b(?:sent|paid)\s+you\b|\byou received\b|\bpayment received\b|\bpayment from\b/i', $text) === 1;

    if ($cashAppDomain && ($cashAppSignal || $genericPaymentSignal)) {
        return [
            'key' => 'cash_app',
            'label' => 'Cash App',
            'domains' => $candidateDomains,
        ];
    }

    if (cs_site_zelle_subject_is_expected($subject) || str_contains($text, 'zelle') || ($zelleDomain && $genericPaymentSignal)) {
        return [
            'key' => 'zelle',
            'label' => 'Zelle',
            'domains' => $candidateDomains,
        ];
    }

    return [
        'key' => '',
        'label' => 'Payment',
        'domains' => $candidateDomains,
    ];
}

function cs_site_zelle_email_domain(string $email): string
{
    $email = cs_site_zelle_normalize_email($email);

    if ($email === '' || ! str_contains($email, '@')) {
        return '';
    }

    return strtolower(substr(strrchr($email, '@') ?: '', 1));
}

function cs_site_zelle_domain_is_trusted(string $domain, array $trustedDomains): bool
{
    $domain = strtolower(ltrim(trim($domain), '@.'));

    if ($domain === '') {
        return false;
    }

    foreach ($trustedDomains as $trustedDomain) {
        if ($domain === $trustedDomain || str_ends_with($domain, '.'.$trustedDomain)) {
            return true;
        }
    }

    return false;
}

function cs_site_zelle_validate_headers(string $headers, string $fromEmail): array
{
    $normalized = preg_replace("/\r?\n[ \t]+/", ' ', $headers) ?: $headers;
    $lower = strtolower($normalized);
    $trustedDomains = cs_site_zelle_trusted_domains();
    $fromDomain = cs_site_zelle_email_domain($fromEmail);
    $returnPathDomain = '';
    $dkimDomains = [];

    if (preg_match('/^Return-Path:\s*<?([^>\s]+)>?/mi', $normalized, $match) === 1) {
        $returnPathDomain = cs_site_zelle_email_domain($match[1]);
    }

    if (preg_match_all('/^DKIM-Signature:\s*(.+)$/mi', $normalized, $matches) !== false) {
        foreach ($matches[1] ?? [] as $signature) {
            if (preg_match('/(?:^|;\s*)d=([^;\s]+)/i', (string) $signature, $domainMatch) === 1) {
                $dkimDomains[] = strtolower(trim($domainMatch[1]));
            }
        }
    }

    $spfPass = str_contains($lower, 'spf=pass') || str_contains($lower, 'received-spf: pass');
    $dkimPass = str_contains($lower, 'dkim=pass');
    $dmarcPass = str_contains($lower, 'dmarc=pass');
    $trustedFrom = cs_site_zelle_domain_is_trusted($fromDomain, $trustedDomains);
    $trustedReturnPath = cs_site_zelle_domain_is_trusted($returnPathDomain, $trustedDomains);
    $trustedDkim = false;
    foreach ($dkimDomains as $dkimDomain) {
        if (cs_site_zelle_domain_is_trusted($dkimDomain, $trustedDomains)) {
            $trustedDkim = true;
            break;
        }
    }
    $trusted = $headers !== '' && (
        ($trustedDkim && ($dkimPass || $dmarcPass))
        || ($trustedFrom && ($spfPass || $dmarcPass))
        || ($trustedReturnPath && $spfPass)
    );
    $reasons = [];

    if ($trustedFrom) {
        $reasons[] = 'From domain trusted';
    }

    if ($trustedReturnPath) {
        $reasons[] = 'Return-Path domain trusted';
    }

    if ($trustedDkim) {
        $reasons[] = 'DKIM domain trusted';
    }

    if ($spfPass) {
        $reasons[] = 'SPF pass';
    }

    if ($dkimPass) {
        $reasons[] = 'DKIM pass';
    }

    if ($dmarcPass) {
        $reasons[] = 'DMARC pass';
    }

    if ($reasons === []) {
        $reasons[] = $headers === '' ? 'No headers available' : 'No trusted sender proof found';
    }

    return [
        'trusted' => $trusted,
        'status_label' => $trusted ? 'Trusted sender' : 'Needs header review',
        'from_domain' => $fromDomain,
        'return_path_domain' => $returnPathDomain,
        'dkim_domains' => array_values(array_unique($dkimDomains)),
        'trusted_domains' => $trustedDomains,
        'spf_pass' => $spfPass,
        'dkim_pass' => $dkimPass,
        'dmarc_pass' => $dmarcPass,
        'reasons' => $reasons,
    ];
}

function cs_site_zelle_header_is_trusted_payment_sender(array $headerTrust): bool
{
    return ! empty($headerTrust['trusted']);
}

function cs_site_zelle_header_has_trusted_payment_domain(array $headerTrust): bool
{
    $trustedDomains = (array) ($headerTrust['trusted_domains'] ?? cs_site_zelle_trusted_domains());
    $candidateDomains = array_filter(array_merge(
        [(string) ($headerTrust['from_domain'] ?? '')],
        [(string) ($headerTrust['return_path_domain'] ?? '')],
        array_map('strval', (array) ($headerTrust['dkim_domains'] ?? [])),
    ));

    foreach ($candidateDomains as $candidateDomain) {
        if (cs_site_zelle_domain_is_trusted((string) $candidateDomain, $trustedDomains)) {
            return true;
        }
    }

    return false;
}

function cs_site_zelle_message_has_payment_signal(string $subject, string $body): bool
{
    if (cs_site_zelle_subject_is_expected($subject)) {
        return true;
    }

    $text = strtolower(preg_replace('/\s+/', ' ', $subject . ' ' . $body) ?: '');

    if ($text === '') {
        return false;
    }

    $signals = [
        'zelle',
        'cash app',
        'cashapp',
        'cashtag',
        'you received',
        'sent you',
        'paid you',
        'payment received',
        'payment from',
        'transaction id',
        'confirmation number',
        'activity number',
    ];

    foreach ($signals as $signal) {
        if (str_contains($text, $signal)) {
            return true;
        }
    }

    return false;
}

function cs_site_zelle_subject_has_possible_payment_signal(string $subject): bool
{
    $subject = strtolower(preg_replace('/\s+/', ' ', $subject) ?: '');

    if ($subject === '') {
        return false;
    }

    foreach (['zelle', 'cash app', 'cashapp', 'you received', 'sent you', 'paid you', 'payment received', 'payment from', '$'] as $signal) {
        if (str_contains($subject, $signal)) {
            return true;
        }
    }

    return false;
}

function cs_site_zelle_sql_trusted_sender_predicate(string $column = 'from_email'): array
{
    $parts = [];
    $params = [];

    foreach (cs_site_zelle_trusted_domains() as $domain) {
        $domain = strtolower(trim((string) $domain));

        if ($domain === '') {
            continue;
        }

        $parts[] = "LOWER({$column}) LIKE ?";
        $params[] = '%@' . $domain;
        $parts[] = "LOWER({$column}) LIKE ?";
        $params[] = '%.' . $domain;
    }

    foreach (cs_site_zelle_trusted_forwarder_emails() as $email) {
        if ($email === '') {
            continue;
        }

        $parts[] = "LOWER({$column}) = ?";
        $params[] = $email;
    }

    if ($parts === []) {
        return ['1 = 0', []];
    }

    return ['(' . implode(' OR ', $parts) . ')', $params];
}

function cs_site_zelle_quarantine_untrusted_messages(PDO $pdo): void
{
    if (! cs_site_zelle_table_exists($pdo, 'zelle_payment_messages')) {
        return;
    }

    [$trustedPredicate, $params] = cs_site_zelle_sql_trusted_sender_predicate('from_email');
    $sql = "
        UPDATE zelle_payment_messages
        SET status = 'ignored',
            payment_status = 'ignored',
            match_type = 'non_payment_sender',
            processed_at = NULL,
            updated_at = NOW()
        WHERE status NOT IN ('ignored', 'archived')
          AND archived_at IS NULL
          AND (from_email IS NULL OR from_email = '' OR NOT {$trustedPredicate})
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
}

function cs_site_zelle_decode_part_body(string $body, int $encoding): string
{
    return match ($encoding) {
        3 => base64_decode($body, true) ?: '',
        4 => quoted_printable_decode($body),
        default => $body,
    };
}

function cs_site_zelle_fetch_body_part($imap, object $part, string $partNumber, string $preferredSubtype): string
{
    $type = (int) ($part->type ?? -1);
    $subtype = strtoupper((string) ($part->subtype ?? ''));

    if ($type === 0 && $subtype === strtoupper($preferredSubtype)) {
        $body = @imap_fetchbody($imap, (int) $_SESSION['creditsoft_zelle_fetch_msgno'], $partNumber);

        return cs_site_zelle_decode_part_body((string) $body, (int) ($part->encoding ?? 0));
    }

    if (! empty($part->parts) && is_array($part->parts)) {
        foreach ($part->parts as $index => $child) {
            $childNumber = $partNumber === '' ? (string) ($index + 1) : $partNumber.'.'.($index + 1);
            $body = cs_site_zelle_fetch_body_part($imap, $child, $childNumber, $preferredSubtype);

            if (trim($body) !== '') {
                return $body;
            }
        }
    }

    return '';
}

function cs_site_zelle_fetch_message_body($imap, int $msgno): string
{
    $_SESSION['creditsoft_zelle_fetch_msgno'] = $msgno;
    $structure = @imap_fetchstructure($imap, $msgno);

    if (is_object($structure)) {
        $plain = cs_site_zelle_fetch_body_part($imap, $structure, '1', 'PLAIN');
        if (trim($plain) !== '') {
            unset($_SESSION['creditsoft_zelle_fetch_msgno']);

            return $plain;
        }

        $html = cs_site_zelle_fetch_body_part($imap, $structure, '1', 'HTML');
        if (trim($html) !== '') {
            unset($_SESSION['creditsoft_zelle_fetch_msgno']);

            return html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
    }

    unset($_SESSION['creditsoft_zelle_fetch_msgno']);

    $body = (string) @imap_body($imap, $msgno);

    return html_entity_decode(strip_tags(quoted_printable_decode($body)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function cs_site_zelle_mailbox_string(array $config): string
{
    $host = (string) ($config['host'] ?? 'mail.creditsoft.app');
    $port = (int) ($config['port'] ?? 993);
    $folder = (string) ($config['folder'] ?? 'INBOX');

    return sprintf('{%s:%d/imap/ssl/novalidate-cert}%s', $host, $port, $folder);
}

function cs_site_zelle_delete_mailbox_uid(string $uid): bool
{
    $uid = trim($uid);

    if ($uid === '' || ! function_exists('imap_open')) {
        return false;
    }

    $config = cs_site_zelle_mailbox_config();

    if (empty($config['password'])) {
        return false;
    }

    $imap = @imap_open(cs_site_zelle_mailbox_string($config), (string) $config['username'], (string) $config['password']);

    if (! $imap) {
        return false;
    }

    $deleted = @imap_delete($imap, $uid, FT_UID);

    if ($deleted) {
        @imap_expunge($imap);
    }

    @imap_close($imap);

    return (bool) $deleted;
}

function cs_site_zelle_fetch_mailbox_messages(int $limit = 25): array
{
    $config = cs_site_zelle_mailbox_config();

    if (empty($config['password'])) {
        return [
            'success' => false,
            'error' => 'Zelle mailbox password is not configured in the private site config.',
            'messages' => [],
        ];
    }

    if (! function_exists('imap_open')) {
        return [
            'success' => false,
            'error' => 'The PHP IMAP extension is not enabled on this host.',
            'messages' => [],
        ];
    }

    $mailbox = cs_site_zelle_mailbox_string($config);
    $imap = @imap_open($mailbox, (string) $config['username'], (string) $config['password']);

    if (! $imap) {
        return [
            'success' => false,
            'error' => imap_last_error() ?: 'Could not open the Zelle mailbox.',
            'messages' => [],
        ];
    }

    $uids = @imap_search($imap, 'ALL', SE_UID);

    if (! is_array($uids) || $uids === []) {
        @imap_close($imap);

        return [
            'success' => true,
            'messages' => [],
        ];
    }

    rsort($uids);
    $uids = array_slice($uids, 0, max(500, $limit * 50));
    $messages = [];

    foreach ($uids as $uid) {
        if (count($messages) >= max(1, $limit)) {
            break;
        }

        $msgno = (int) @imap_msgno($imap, (int) $uid);

        if ($msgno <= 0) {
            continue;
        }

        $overview = @imap_fetch_overview($imap, (string) $uid, FT_UID);
        $overview = is_array($overview) ? ($overview[0] ?? null) : null;
        $from = cs_site_zelle_parse_address((string) ($overview->from ?? ''));
        $subject = cs_site_zelle_decode_header((string) ($overview->subject ?? ''));

        $headers = (string) @imap_fetchheader($imap, (int) $uid, FT_UID);
        $headerTrust = cs_site_zelle_validate_headers($headers, $from['email']);
        $trustedForwarder = cs_site_zelle_is_trusted_forwarder_email($from['email']);
        $hasTrustedSender = cs_site_zelle_header_is_trusted_payment_sender($headerTrust)
            || cs_site_zelle_header_has_trusted_payment_domain($headerTrust);

        if (! $hasTrustedSender && ! $trustedForwarder) {
            continue;
        }

        if (! $trustedForwarder) {
            $subjectProvider = cs_site_zelle_detect_payment_provider($subject, '', $from['email'], $headerTrust);
            if ((string) ($subjectProvider['key'] ?? '') === '' && ! cs_site_zelle_subject_has_possible_payment_signal($subject)) {
                continue;
            }
        } elseif (! cs_site_zelle_subject_has_possible_payment_signal($subject) && ! str_contains(strtolower($subject), 'payment pending')) {
            continue;
        }

        $body = cs_site_zelle_fetch_message_body($imap, $msgno);
        $headerTrust = cs_site_zelle_apply_forwarded_payment_trust($headerTrust, $subject, $body, $from['email']);

        if (
            ! cs_site_zelle_header_is_trusted_payment_sender($headerTrust)
            && ! cs_site_zelle_header_has_trusted_payment_domain($headerTrust)
        ) {
            continue;
        }

        if (! cs_site_zelle_message_has_payment_signal($subject, $body)) {
            continue;
        }

        $provider = cs_site_zelle_detect_payment_provider($subject, $body, $from['email'], $headerTrust);
        if ((string) ($provider['key'] ?? '') === '') {
            continue;
        }

        $messages[] = [
            'mailbox' => (string) ($config['username'] ?? 'z@creditsoft.app'),
            'payment_provider' => (string) ($provider['key'] ?? 'zelle'),
            'payment_provider_label' => (string) ($provider['label'] ?? 'Zelle'),
            'uid' => (string) $uid,
            'message_id' => (string) ($overview->message_id ?? ''),
            'received_at' => ! empty($overview->date) ? date('Y-m-d H:i:s', strtotime((string) $overview->date)) : gmdate('Y-m-d H:i:s'),
            'from_name' => $from['name'],
            'from_email' => $from['email'],
            'subject' => $subject,
            'body' => $body,
            'header_trust' => $headerTrust,
        ];
    }

    @imap_close($imap);

    return [
        'success' => true,
        'messages' => $messages,
    ];
}

function cs_site_zelle_extract_amount(string $subject, string $body): ?float
{
    $text = $subject."\n".$body;
    $patterns = [
        '/(?:received|sent|payment|zelle|amount)[^\$]{0,80}\$\s*([0-9]{1,6}(?:,[0-9]{3})*(?:\.[0-9]{2})?)/i',
        '/\$\s*([0-9]{1,6}(?:,[0-9]{3})*(?:\.[0-9]{2})?)/',
        '/USD\s*([0-9]{1,6}(?:,[0-9]{3})*(?:\.[0-9]{2})?)/i',
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $text, $match) === 1) {
            return round((float) str_replace(',', '', $match[1]), 2);
        }
    }

    return null;
}

function cs_site_zelle_extract_transaction_id(string $subject, string $body, string $messageId = ''): string
{
    $text = preg_replace('/\s+/', ' ', $subject . "\n" . $body) ?: '';
    $patterns = [
        '/(?:transaction|confirmation|reference|activity|payment)\s*(?:id|number|#|no\.?)\s*:?\s*([A-Z0-9][A-Z0-9\-_.]{5,80})/i',
        '/\btransaction\s*:?\s*([0-9]{6,30})\b/i',
        '/(?:zelle|chase|cash\s*app|cashapp|square)\s*(?:payment\s*)?(?:id|reference|receipt|receipt\s*number)\s*:?\s*([A-Z0-9][A-Z0-9\-_.]{5,80})/i',
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $text, $match) === 1) {
            return cs_site_zelle_clean_text((string) $match[1], 255);
        }
    }

    return cs_site_zelle_clean_text($messageId, 255);
}

function cs_site_zelle_extract_sender_name(string $subject, string $body, string $fallback = ''): string
{
    $text = preg_replace('/\s+/', ' ', $subject."\n".$body) ?: '';
    $patterns = [
        '/accept\s+or\s+decline\s+(?:the\s+)?\$[0-9,.]+\s+payment\s+from\s+([A-Z][A-Z0-9 .,&\'-]{2,80})(?:\s+before|\s+\[|$)/i',
        '/\bpayment\s+from\s+([A-Z][A-Z0-9 .,&\'-]{2,80})(?:\s+before|\s+\[|$)/i',
        '/\bfrom\s+([A-Z][A-Z0-9 .,&\'-]{2,80})\s+\+\s*\$[0-9,.]+/i',
        '/(?:from|sender|paid by|payment from)\s*:?\s*([A-Z][A-Z0-9 .,&\'-]{2,80})/i',
        '/([A-Z][A-Z0-9 .,&\'-]{2,80})\s+(?:sent|paid)\s+you(?:\s+\$[0-9,.]+)?/i',
        '/you received (?:a )?(?:payment|zelle|cash\s*app)?(?: from)?\s*([A-Z][A-Z0-9 .,&\'-]{2,80})/i',
        '/you received\s+\$[0-9,.]+\s+from\s+([A-Z][A-Z0-9 .,&\'-]{2,80})/i',
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $text, $match) === 1) {
            $candidate = trim((string) $match[1]);
            $candidate = preg_replace('/\s+(?:for|via|through|using|on)\s+.*$/i', '', $candidate) ?: $candidate;

            return cs_site_zelle_clean_sender_display_name($candidate);
        }
    }

    return cs_site_zelle_clean_sender_display_name($fallback);
}

function cs_site_zelle_extract_emails(string $subject, string $body, string $fromEmail = ''): array
{
    $text = $subject."\n".$body;
    preg_match_all('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $text, $matches);

    $config = cs_site_zelle_mailbox_config();
    $blocked = array_filter([
        cs_site_zelle_normalize_email($fromEmail),
        cs_site_zelle_normalize_email((string) ($config['username'] ?? '')),
        cs_site_zelle_normalize_email((string) ($config['from_email'] ?? '')),
    ]);

    return array_values(array_unique(array_filter(
        array_map('cs_site_zelle_normalize_email', $matches[0] ?? []),
        static fn (string $email): bool => $email !== '' && ! in_array($email, $blocked, true),
    )));
}

function cs_site_zelle_extract_memo_text(string $subject, string $body): string
{
    $text = preg_replace('/\s+/', ' ', $subject . "\n" . $body) ?: '';
    $patterns = [
        '/\b(?:memo|note|message|payment memo|zelle memo)\b\s*:?\s*([A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}|[^.;\n\r]{1,180})/i',
        '/\b(?:for|description)\b\s*:?\s*([A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}|[^.;\n\r]{1,180})/i',
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $text, $match) === 1) {
            return cs_site_zelle_clean_text((string) $match[1], 255);
        }
    }

    return '';
}

function cs_site_zelle_extract_phone(string $subject, string $body): string
{
    $text = $subject."\n".$body;

    if (preg_match('/(?:phone|mobile|payer phone|sent from)[^\d]{0,20}((?:\+?1[\s.-]?)?(?:\(?\d{3}\)?[\s.-]?)\d{3}[\s.-]?\d{4})/i', $text, $match) === 1) {
        return cs_site_zelle_normalize_phone($match[1]);
    }

    return '';
}

function cs_site_zelle_checkout_paths(): array
{
    return array_values(array_unique([
        dirname(__DIR__) . '/data/checkout_requests.jsonl',
        dirname(__DIR__) . '/data/renew_requests.jsonl',
        dirname(__DIR__, 2) . '/update.creditsoft.app/data/checkout_requests.jsonl',
        dirname(__DIR__, 2) . '/update.creditsoft.app/data/renew_requests.jsonl',
    ]));
}

function cs_site_zelle_read_jsonl(string $path): array
{
    if (! is_file($path)) {
        return [];
    }

    $rows = [];
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach (is_array($lines) ? $lines : [] as $line) {
        $decoded = json_decode((string) $line, true);
        if (is_array($decoded)) {
            $decoded['_source_path'] = $path;
            $rows[] = $decoded;
        }
    }

    return $rows;
}

function cs_site_zelle_payment_notices(): array
{
    $pdo = function_exists('cs_site_admin_db') ? cs_site_admin_db() : null;

    if (! $pdo instanceof PDO || ! function_exists('creditsoft_site_checkout_rows')) {
        return [];
    }

    try {
        $rows = creditsoft_site_checkout_rows($pdo);
    } catch (Throwable) {
        return [];
    }

    foreach ($rows as &$row) {
        $checkoutReference = (string) ($row['checkout_reference'] ?? '');
        $row['checkout_number'] = $checkoutReference;
        $row['checkout_reference'] = $checkoutReference;
        $row['payment_memo'] = $checkoutReference !== '' && ! empty($row['customer_email'])
            ? 'CreditSoft checkout:'.$checkoutReference.' email:'.$row['customer_email']
            : '';
        $row['payment_memo_email'] = (string) ($row['payment_memo_email'] ?? $row['customer_email'] ?? '');
        $row['plan'] = (string) ($row['public_plan_key'] ?? '');
        $row['amount'] = is_numeric($row['amount'] ?? null) ? round((float) $row['amount'], 2) : null;
        $row['zelle_amount'] = is_numeric($row['zelle_amount'] ?? null) ? round((float) $row['zelle_amount'], 2) : $row['amount'];
        $row['base_amount'] = is_numeric($row['base_amount'] ?? null) ? round((float) $row['base_amount'], 2) : null;
        $row['payment_amount_sent'] = is_numeric($row['payment_amount_sent'] ?? null) ? round((float) $row['payment_amount_sent'], 2) : null;
        $row['_source_path'] = 'payment_checkout_requests';
    }
    unset($row);

    return $rows;
}

function cs_site_zelle_notice_email_candidates(array $notice): array
{
    return array_values(array_unique(array_filter([
        cs_site_zelle_normalize_email($notice['customer_email'] ?? ''),
        cs_site_zelle_normalize_email($notice['payer_email'] ?? ''),
        cs_site_zelle_normalize_email($notice['payment_source'] ?? ''),
        cs_site_zelle_normalize_email($notice['payment_memo_email'] ?? ''),
    ])));
}

function cs_site_zelle_reference_compact(string $value): string
{
    return strtolower(preg_replace('/[^a-z0-9]/i', '', $value) ?: '');
}

function cs_site_zelle_notice_reference_candidates(array $notice): array
{
    $fields = [
        'checkout_number',
        'checkout_reference',
        'renewal_number',
        'renewal_reference',
        'order_number',
        'reference',
        'payment_memo',
        'license_key',
    ];
    $references = [];

    foreach ($fields as $field) {
        $value = cs_site_zelle_clean_text((string) ($notice[$field] ?? ''), 255);
        if ($value !== '') {
            $references[] = $value;
        }
    }

    return array_values(array_unique(array_filter($references)));
}

function cs_site_zelle_match_notice(array $payment): ?array
{
    $paymentAmount = $payment['amount'] ?? null;
    $paymentEmails = array_map('cs_site_zelle_normalize_email', (array) ($payment['emails'] ?? []));
    $paymentPhone = cs_site_zelle_normalize_phone($payment['sender_phone'] ?? '');
    $senderName = cs_site_zelle_normalize_name($payment['sender_name'] ?? '');
    $paymentTransactionId = cs_site_zelle_normalize_transaction_id((string) ($payment['transaction_id'] ?? ''));
    $paymentMatchText = cs_site_zelle_reference_compact(implode("\n", array_filter([
        (string) ($payment['memo_text'] ?? ''),
        (string) ($payment['match_text'] ?? ''),
        (string) ($payment['transaction_id'] ?? ''),
    ])));
    $best = null;
    $bestScore = 0;

    foreach (cs_site_zelle_payment_notices() as $notice) {
        $score = 0;
        $noticeSentAmount = is_numeric($notice['payment_amount_sent'] ?? null) ? round((float) $notice['payment_amount_sent'], 2) : null;
        $noticeAmount = is_numeric($notice['amount'] ?? null) ? round((float) $notice['amount'], 2) : null;
        $noticeTransactionId = cs_site_zelle_normalize_transaction_id((string) ($notice['payment_transaction_id'] ?? $notice['transaction_id'] ?? ''));

        if ($paymentTransactionId !== '' && $noticeTransactionId !== '' && $paymentTransactionId === $noticeTransactionId) {
            $score += 70;
        }

        foreach (cs_site_zelle_notice_reference_candidates($notice) as $reference) {
            $compactReference = cs_site_zelle_reference_compact($reference);
            if ($compactReference !== '' && $paymentMatchText !== '' && str_contains($paymentMatchText, $compactReference)) {
                $score += 85;
                break;
            }
        }

        if ($paymentAmount !== null && $noticeSentAmount !== null && abs((float) $paymentAmount - $noticeSentAmount) <= 0.02) {
            $score += 55;
        } elseif ($paymentAmount !== null && $noticeAmount !== null && abs((float) $paymentAmount - $noticeAmount) <= 0.02) {
            $score += 45;
        }

        $noticeEmails = cs_site_zelle_notice_email_candidates($notice);
        if (array_intersect($paymentEmails, $noticeEmails) !== []) {
            $score += 35;
        }

        $noticePhone = cs_site_zelle_normalize_phone((string) ($notice['customer_phone'] ?? $notice['payer_phone'] ?? $notice['payment_source'] ?? ''));
        if ($paymentPhone !== '' && $noticePhone !== '' && str_ends_with($paymentPhone, substr($noticePhone, -7))) {
            $score += 25;
        }

        $noticeName = cs_site_zelle_normalize_name((string) ($notice['office_name'] ?? $notice['customer_name'] ?? ''));
        if ($senderName !== '' && $noticeName !== '' && (str_contains($senderName, $noticeName) || str_contains($noticeName, $senderName))) {
            $score += 20;
        }

        if ($score > $bestScore) {
            $best = $notice;
            $bestScore = $score;
        }
    }

    if ($best && $bestScore >= 35) {
        $best['_match_score'] = $bestScore;

        return $best;
    }

    return null;
}

function cs_site_zelle_detect_plan_from_amount(?float $amount): array
{
    $best = cs_site_zelle_exact_amount_match($amount) ?? cs_site_zelle_default_payment_plan();

    return array_merge($best, [
        'plan_key' => (string) ($best['plan_key'] ?? 'enterprise'),
        'billing' => (string) ($best['billing'] ?? 'monthly'),
    ]);
}

function cs_site_zelle_manual_discount_percent(): int
{
    if (function_exists('creditsoft_site_manual_payment_discount_percent')) {
        return creditsoft_site_manual_payment_discount_percent();
    }

    return 0;
}

function cs_site_zelle_discounted_amount(float $base, ?int $discountPercent = null): float
{
    $discountPercent = $discountPercent ?? cs_site_zelle_manual_discount_percent();

    if (function_exists('creditsoft_site_manual_payment_amount')) {
        return creditsoft_site_manual_payment_amount($base, $discountPercent);
    }

    $baseCents = (int) round(max(0.0, $base) * 100);
    $discountedCents = intdiv($baseCents * max(0, min(100, 100 - $discountPercent)), 100);

    return round($discountedCents / 100, 2);
}

function cs_site_zelle_expected_amount_catalog(): array
{
    $pricing = function_exists('creditsoft_site_pricing_load') ? creditsoft_site_pricing_load() : [];
    $plans = is_array($pricing['plans'] ?? null) ? $pricing['plans'] : [];
    $addons = function_exists('creditsoft_site_checkout_addons') ? creditsoft_site_checkout_addons() : [];
    $clusterAddon = is_array($addons['cluster'] ?? null) ? $addons['cluster'] : null;
    $rows = [];
    $discountPercent = cs_site_zelle_manual_discount_percent();
    $appendRow = static function (string $publicPlanKey, string $planKey, string $planName, string $billing, float $base, array $addonKeys = []) use (&$rows, $discountPercent): void {
        $base = round($base, 2);

        if ($base <= 0) {
            return;
        }

        $zelle = cs_site_zelle_discounted_amount($base, $discountPercent);
        $rows[] = [
            'public_plan_key' => $publicPlanKey,
            'plan_key' => $planKey,
            'plan_name' => $planName,
            'billing' => $billing,
            'addons' => $addonKeys,
            'base_amount' => $base,
            'expected_amount' => $zelle,
            'discount_percent' => $discountPercent,
        ];
    };

    foreach ($plans as $publicPlanKey => $plan) {
        foreach (['monthly', 'yearly'] as $billing) {
            $base = round((float) ($plan[$billing] ?? 0), 2);
            $licensePlanKey = function_exists('creditsoft_site_license_plan_key')
                    ? creditsoft_site_license_plan_key((string) $publicPlanKey)
                    : (string) $publicPlanKey;
            $planName = (string) ($plan['name'] ?? $publicPlanKey);

            $appendRow((string) $publicPlanKey, $licensePlanKey, $planName, $billing, $base);

            if ($clusterAddon) {
                $clusterBase = round((float) ($clusterAddon[$billing] ?? 0), 2);
                $appendRow(
                    (string) $publicPlanKey . '+cluster',
                    $licensePlanKey,
                    $planName . ' + Cluster',
                    $billing,
                    $base + $clusterBase,
                    ['cluster'],
                );
            }
        }
    }

    if ($clusterAddon) {
        foreach (['monthly', 'yearly'] as $billing) {
            $appendRow(
                'cluster',
                'cluster',
                (string) ($clusterAddon['name'] ?? 'Cluster license'),
                $billing,
                round((float) ($clusterAddon[$billing] ?? 0), 2),
            );
        }
    }

    return $rows;
}

function cs_site_zelle_closest_expected_amount(?float $amount): array
{
    $catalog = cs_site_zelle_expected_amount_catalog();
    $best = [
        'plan_key' => 'enterprise',
        'billing' => 'monthly',
        'base_amount' => null,
        'expected_amount' => null,
        'plan_name' => 'CreditSoft',
        'score' => 999999.0,
    ];

    foreach ($catalog as $row) {
        $expected = (float) ($row['expected_amount'] ?? 0);
        $delta = $amount === null ? 0 : abs($expected - $amount);

        if ($amount === null) {
            $delta = (string) ($row['plan_key'] ?? '') === 'enterprise' && (string) ($row['billing'] ?? '') === 'monthly' ? 0 : 999999.0;
        }

        if ($delta < $best['score']) {
            $best = $row;
            $best['score'] = $delta;
        }
    }

    return $best;
}

function cs_site_zelle_default_payment_plan(): array
{
    foreach (cs_site_zelle_expected_amount_catalog() as $row) {
        if (
            (string) ($row['public_plan_key'] ?? '') === 'enterprise'
            && (string) ($row['billing'] ?? '') === 'monthly'
            && ($row['addons'] ?? []) === []
        ) {
            return $row;
        }
    }

    return [
        'public_plan_key' => 'enterprise',
        'plan_key' => 'enterprise',
        'plan_name' => 'CreditSoft',
        'billing' => 'monthly',
        'addons' => [],
        'base_amount' => null,
        'expected_amount' => null,
        'discount_percent' => cs_site_zelle_manual_discount_percent(),
        'pricing_unavailable' => true,
    ];
}

function cs_site_zelle_exact_amount_match(?float $amount): ?array
{
    if ($amount === null) {
        return null;
    }

    foreach (cs_site_zelle_expected_amount_catalog() as $row) {
        $discounted = is_numeric($row['expected_amount'] ?? null) ? (float) $row['expected_amount'] : null;
        $base = is_numeric($row['base_amount'] ?? null) ? (float) $row['base_amount'] : null;

        if ($discounted !== null && abs($amount - $discounted) <= 0.02) {
            return $row;
        }

        if ($base !== null && abs($amount - $base) <= 0.02) {
            $row['expected_amount'] = $base;
            $row['discount_percent'] = 0;
            $row['payment_terms'] = 'regular_full_price';

            return $row;
        }
    }

    return null;
}

function cs_site_zelle_expected_payment(array $plan, ?array $notice, ?float $amount): array
{
    $expected = null;
    $base = null;
    $planName = 'CreditSoft';
    $discountPercent = cs_site_zelle_manual_discount_percent();
    $hasCheckoutNotice = is_array($notice);
    $publicPlanKey = (string) ($plan['public_plan_key'] ?? '');
    $addons = is_array($plan['addons'] ?? null) ? array_values(array_map('strval', $plan['addons'])) : [];

    if ($notice) {
        $publicPlanKey = (string) ($notice['public_plan_key'] ?? $notice['plan'] ?? $publicPlanKey);
        $addons = is_array($notice['addons'] ?? null) ? array_values(array_map('strval', $notice['addons'])) : $addons;

        foreach (['zelle_amount', 'amount'] as $key) {
            if (is_numeric($notice[$key] ?? null)) {
                $expected = round((float) $notice[$key], 2);
                break;
            }
        }

        if (is_numeric($notice['base_amount'] ?? null)) {
            $base = round((float) $notice['base_amount'], 2);
        }

        if (is_numeric($notice['zelle_discount_percent'] ?? null)) {
            $discountPercent = (int) $notice['zelle_discount_percent'];
        }

        $planName = cs_site_zelle_clean_text((string) ($notice['plan_name'] ?? 'CreditSoft'), 120) ?: 'CreditSoft';
    }

    if ($expected === null) {
        $matched = cs_site_zelle_exact_amount_match($amount);
        $fallback = $matched ?? cs_site_zelle_default_payment_plan();
        $publicPlanKey = (string) ($fallback['public_plan_key'] ?? $publicPlanKey);
        $addons = is_array($fallback['addons'] ?? null) ? array_values(array_map('strval', $fallback['addons'])) : $addons;
        $expected = is_numeric($fallback['expected_amount'] ?? null) ? round((float) $fallback['expected_amount'], 2) : null;
        $base = is_numeric($fallback['base_amount'] ?? null) ? round((float) $fallback['base_amount'], 2) : null;
        $planName = cs_site_zelle_clean_text((string) ($fallback['plan_name'] ?? $planName), 120) ?: $planName;
        $plan['plan_key'] = (string) ($fallback['plan_key'] ?? ($plan['plan_key'] ?? 'enterprise'));
        $plan['billing'] = (string) ($fallback['billing'] ?? ($plan['billing'] ?? 'monthly'));
        $discountPercent = (int) ($fallback['discount_percent'] ?? $discountPercent);

        if ($matched === null && $base !== null) {
            $expected = $base;
            $discountPercent = 0;
        }
    }

    if ($amount !== null && $expected !== null && $amount < ($expected - 0.02) && $base !== null) {
        $expected = $base;
        $discountPercent = 0;
    }

    $delta = $amount !== null && $expected !== null ? round($amount - $expected, 2) : null;
    $balanceDue = $delta !== null && $delta < -0.02 ? abs($delta) : 0.0;
    $status = 'unknown';

    if ($amount === null || $expected === null) {
        $status = 'missing_amount';
    } elseif (abs($delta) <= 0.02) {
        $status = 'paid';
    } elseif ($delta > 0.02) {
        $paidBasePrice = $base !== null && abs($amount - $base) <= 0.02;
        $status = ($hasCheckoutNotice || $paidBasePrice) ? 'overpaid' : 'amount_mismatch';
    } else {
        $status = 'balance_due';
    }

    return [
        'public_plan_key' => $publicPlanKey,
        'plan_key' => (string) ($plan['plan_key'] ?? 'enterprise'),
        'billing' => (string) ($plan['billing'] ?? 'monthly'),
        'plan_name' => $planName,
        'addons' => $addons,
        'base_amount' => $base,
        'expected_amount' => $expected,
        'paid_amount' => $amount,
        'balance_due' => $balanceDue,
        'discount_percent' => $discountPercent,
        'partial_payment_discount_removed' => $amount !== null && $expected !== null && $base !== null && $amount < ($expected - 0.02) && $discountPercent === 0,
        'status' => $status,
        'delta' => $delta,
    ];
}

function cs_site_zelle_customer_match_is_strong(array $customer): bool
{
    $email = cs_site_zelle_normalize_email((string) ($customer['email'] ?? ''));
    $matchType = (string) ($customer['match_type'] ?? '');

    return $email !== '' && in_array($matchType, ['license_email', 'lead_email', 'license_key', 'lead_name'], true);
}

function cs_site_zelle_promote_matched_customer_overpay(array $expectedPayment, array $customer, ?float $amount): array
{
    $expected = is_numeric($expectedPayment['expected_amount'] ?? null)
        ? round((float) $expectedPayment['expected_amount'], 2)
        : null;

    if (
        $amount === null
        || $expected === null
        || (string) ($expectedPayment['status'] ?? '') !== 'amount_mismatch'
        || $amount <= ($expected + 0.02)
        || ! cs_site_zelle_customer_match_is_strong($customer)
    ) {
        return $expectedPayment;
    }

    $expectedPayment['status'] = 'overpaid';
    $expectedPayment['balance_due'] = 0.0;
    $expectedPayment['delta'] = round($amount - $expected, 2);
    $expectedPayment['auto_approved_overpay'] = true;
    $expectedPayment['auto_approved_match_type'] = (string) ($customer['match_type'] ?? '');

    return $expectedPayment;
}

function cs_site_zelle_is_node_only_payment(array $expectedPayment): bool
{
    $planKey = function_exists('creditsoft_site_license_plan_key')
        ? creditsoft_site_license_plan_key((string) ($expectedPayment['plan_key'] ?? ''))
        : (string) ($expectedPayment['plan_key'] ?? '');

    return $planKey === 'cluster' || (string) ($expectedPayment['public_plan_key'] ?? '') === 'cluster';
}

function cs_site_zelle_enterprise_with_node_catalog_row(string $billing): array
{
    $billing = in_array($billing, ['monthly', 'yearly'], true) ? $billing : 'monthly';

    foreach (cs_site_zelle_expected_amount_catalog() as $row) {
        if (
            (string) ($row['public_plan_key'] ?? '') === 'enterprise+cluster'
            && (string) ($row['billing'] ?? '') === $billing
        ) {
            return $row;
        }
    }

    $enterprise = cs_site_zelle_default_payment_plan();
    $addons = function_exists('creditsoft_site_checkout_addons') ? creditsoft_site_checkout_addons() : [];
    $cluster = is_array($addons['cluster'] ?? null) ? $addons['cluster'] : [];
    $enterpriseBase = is_numeric($enterprise['base_amount'] ?? null) ? round((float) $enterprise['base_amount'], 2) : null;
    $clusterBase = is_numeric($cluster[$billing] ?? null) ? round((float) $cluster[$billing], 2) : null;
    $base = $enterpriseBase !== null && $clusterBase !== null ? round($enterpriseBase + $clusterBase, 2) : null;
    $discountPercent = cs_site_zelle_manual_discount_percent();

    return [
        'public_plan_key' => 'enterprise+cluster',
        'plan_key' => 'enterprise',
        'plan_name' => 'Enterprise + Cluster',
        'billing' => $billing,
        'addons' => ['cluster'],
        'base_amount' => $base,
        'expected_amount' => $base !== null ? cs_site_zelle_discounted_amount($base, $discountPercent) : null,
        'discount_percent' => $discountPercent,
        'pricing_unavailable' => $base === null,
    ];
}

function cs_site_zelle_enforce_node_license_dependency(PDO $pdo, array $customer, array $expectedPayment, ?float $amount): array
{
    if (! cs_site_zelle_is_node_only_payment($expectedPayment)) {
        return $expectedPayment;
    }

    $email = cs_site_zelle_normalize_email((string) ($customer['email'] ?? ''));
    $mainLicense = $email !== '' ? cs_site_zelle_main_license_by_email($pdo, $email) : null;

    if ($mainLicense) {
        $expectedPayment['node_license_dependency'] = 'main_license_found';
        $expectedPayment['main_license_key'] = (string) ($mainLicense['license_key'] ?? '');

        return $expectedPayment;
    }

    $billing = (string) ($expectedPayment['billing'] ?? 'monthly');
    $required = cs_site_zelle_enterprise_with_node_catalog_row($billing);
    $requiredBase = is_numeric($required['base_amount'] ?? null) ? round((float) $required['base_amount'], 2) : null;
    $delta = $amount !== null && $requiredBase !== null ? round($amount - $requiredBase, 2) : null;

    $expectedPayment['public_plan_key'] = (string) ($required['public_plan_key'] ?? 'enterprise+cluster');
    $expectedPayment['plan_key'] = (string) ($required['plan_key'] ?? 'enterprise');
    $expectedPayment['plan_name'] = (string) ($required['plan_name'] ?? 'Enterprise + Cluster');
    $expectedPayment['billing'] = (string) ($required['billing'] ?? $billing);
    $expectedPayment['addons'] = ['cluster'];
    $expectedPayment['base_amount'] = $requiredBase;
    $expectedPayment['expected_amount'] = $requiredBase;
    $expectedPayment['paid_amount'] = $amount;
    $expectedPayment['balance_due'] = $delta !== null && $delta < -0.02 ? abs($delta) : 0.0;
    $expectedPayment['discount_percent'] = 0;
    $expectedPayment['partial_payment_discount_removed'] = true;
    $expectedPayment['status'] = $delta !== null && abs($delta) <= 0.02 ? 'paid' : 'balance_due';
    $expectedPayment['delta'] = $delta;
    $expectedPayment['requires_main_license'] = true;
    $expectedPayment['node_license_dependency'] = 'main_license_required';
    $expectedPayment['dependency_message'] = 'A node/cluster license can only attach to an active main CreditSoft license. No main license was found for this email, so the payment is treated as a partial payment toward Enterprise + Cluster.';

    return $expectedPayment;
}

function cs_site_zelle_lead_by_email(PDO $pdo, string $email): ?array
{
    if ($email === '' || ! cs_site_zelle_table_exists($pdo, 'leads')) {
        return null;
    }

    $where = 'LOWER(email) = LOWER(?)';
    if (cs_site_zelle_column_exists($pdo, 'leads', 'archived_at')) {
        $where .= ' AND archived_at IS NULL';
    }

    $stmt = $pdo->prepare("SELECT * FROM leads WHERE {$where} LIMIT 1");
    $stmt->execute([$email]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return is_array($row) ? $row : null;
}

function cs_site_zelle_lead_by_name(PDO $pdo, string $name): ?array
{
    $name = cs_site_zelle_clean_text($name, 255);

    if ($name === '' || ! cs_site_zelle_table_exists($pdo, 'leads')) {
        return null;
    }

    $where = 'LOWER(name) LIKE LOWER(?)';
    if (cs_site_zelle_column_exists($pdo, 'leads', 'archived_at')) {
        $where .= ' AND archived_at IS NULL';
    }

    $stmt = $pdo->prepare("SELECT * FROM leads WHERE {$where} ORDER BY created_at DESC LIMIT 1");
    $stmt->execute(['%'.$name.'%']);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return is_array($row) ? $row : null;
}

function cs_site_zelle_license_by_email(PDO $pdo, string $email): ?array
{
    if ($email === '') {
        return null;
    }

    $where = 'LOWER(customer_email) = LOWER(?)';
    if (cs_site_zelle_column_exists($pdo, 'licenses', 'archived_at')) {
        $where .= ' AND archived_at IS NULL';
    }

    $stmt = $pdo->prepare("SELECT * FROM licenses WHERE {$where} ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$email]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return is_array($row) ? $row : null;
}

function cs_site_zelle_license_by_email_and_plan(PDO $pdo, string $email, array $planKeys): ?array
{
    $email = cs_site_zelle_normalize_email($email);
    $planKeys = array_values(array_unique(array_filter(array_map(
        static fn ($planKey): string => function_exists('creditsoft_site_license_plan_key')
            ? creditsoft_site_license_plan_key((string) $planKey)
            : trim((string) $planKey),
        $planKeys,
    ))));

    if ($email === '' || $planKeys === []) {
        return null;
    }

    $placeholders = implode(',', array_fill(0, count($planKeys), '?'));
    $stmt = $pdo->prepare(
        "SELECT *
         FROM licenses
         WHERE LOWER(customer_email) = LOWER(?)
           AND plan IN ({$placeholders})
           " . (cs_site_zelle_column_exists($pdo, 'licenses', 'archived_at') ? "AND archived_at IS NULL" : "") . "
         ORDER BY CASE WHEN status = 'active' THEN 0 ELSE 1 END, created_at DESC
         LIMIT 1"
    );
    $stmt->execute(array_merge([$email], $planKeys));
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return is_array($row) ? $row : null;
}

function cs_site_zelle_main_license_by_email(PDO $pdo, string $email): ?array
{
    return cs_site_zelle_license_by_email_and_plan($pdo, $email, ['enterprise', 'enterprise_pro']);
}

function cs_site_zelle_cluster_license_by_email(PDO $pdo, string $email): ?array
{
    return cs_site_zelle_license_by_email_and_plan($pdo, $email, ['cluster']);
}

function cs_site_zelle_license_by_key(PDO $pdo, string $licenseKey): ?array
{
    $normalized = preg_replace('/[^A-Z0-9]/', '', strtoupper($licenseKey)) ?: '';

    if ($normalized === '') {
        return null;
    }

    $where = "REPLACE(REPLACE(UPPER(license_key), '-', ''), ' ', '') = ?";
    if (cs_site_zelle_column_exists($pdo, 'licenses', 'archived_at')) {
        $where .= ' AND archived_at IS NULL';
    }

    $stmt = $pdo->prepare("SELECT * FROM licenses WHERE {$where} LIMIT 1");
    $stmt->execute([$normalized]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return is_array($row) ? $row : null;
}

function cs_site_zelle_resolve_customer(PDO $pdo, array $payment, ?array $notice): array
{
    $emails = array_values(array_unique(array_filter(array_merge(
        array_map('cs_site_zelle_normalize_email', (array) ($payment['emails'] ?? [])),
        $notice ? cs_site_zelle_notice_email_candidates($notice) : [],
    ))));
    $senderName = cs_site_zelle_clean_text((string) ($payment['sender_name'] ?? ''), 255);
    $noticeName = $notice ? cs_site_zelle_clean_text((string) ($notice['office_name'] ?? $notice['customer_name'] ?? ''), 255) : '';
    $name = $noticeName !== '' ? $noticeName : $senderName;
    $phone = $notice
        ? cs_site_zelle_clean_text((string) ($notice['customer_phone'] ?? $notice['payer_phone'] ?? ''), 64)
        : cs_site_zelle_clean_text((string) ($payment['sender_phone'] ?? ''), 64);

    foreach ($emails as $email) {
        $license = cs_site_zelle_license_by_email($pdo, $email);
        if ($license) {
            return [
                'email' => $email,
                'name' => $name !== '' ? $name : (string) ($license['customer_name'] ?? ''),
                'phone' => $phone,
                'lead' => cs_site_zelle_lead_by_email($pdo, $email),
                'license' => $license,
                'match_type' => 'license_email',
            ];
        }

        $lead = cs_site_zelle_lead_by_email($pdo, $email);
        if ($lead) {
            return [
                'email' => $email,
                'name' => $name !== '' ? $name : (string) ($lead['name'] ?? ''),
                'phone' => $phone !== '' ? $phone : (string) ($lead['phone'] ?? ''),
                'lead' => $lead,
                'license' => null,
                'match_type' => 'lead_email',
            ];
        }
    }

    if ($notice) {
        $license = cs_site_zelle_license_by_key($pdo, (string) ($notice['license_key'] ?? ''));
        if ($license) {
            $email = cs_site_zelle_normalize_email((string) ($license['customer_email'] ?? ''));

            return [
                'email' => $email,
                'name' => $name !== '' ? $name : (string) ($license['customer_name'] ?? ''),
                'phone' => $phone,
                'lead' => $email !== '' ? cs_site_zelle_lead_by_email($pdo, $email) : null,
                'license' => $license,
                'match_type' => 'license_key',
            ];
        }
    }

    $leadByName = cs_site_zelle_lead_by_name($pdo, $name);
    if ($leadByName) {
        $email = cs_site_zelle_normalize_email((string) ($leadByName['email'] ?? ''));

        return [
            'email' => $email,
            'name' => $name !== '' ? $name : (string) ($leadByName['name'] ?? ''),
            'phone' => $phone !== '' ? $phone : (string) ($leadByName['phone'] ?? ''),
            'lead' => $leadByName,
            'license' => null,
            'match_type' => 'lead_name',
        ];
    }

    $email = $emails[0] ?? '';

    if ($email === '') {
        return [
            'email' => '',
            'name' => $name,
            'phone' => $phone,
            'lead' => null,
            'license' => null,
            'match_type' => 'needs_review',
        ];
    }

    $lead = function_exists('creditsoft_lead_upsert_basic')
        ? creditsoft_lead_upsert_basic([
            'name' => $name !== '' ? $name : $email,
            'email' => $email,
            'phone' => $phone,
            'company' => $noticeName,
            'source' => 'zelle_payment',
            'plan_interest' => (string) ($notice['plan_name'] ?? $notice['plan'] ?? ''),
        ])
        : null;

    return [
        'email' => $email,
        'name' => $name !== '' ? $name : $email,
        'phone' => $phone,
        'lead' => is_array($lead) ? $lead : null,
        'license' => null,
        'match_type' => is_array($lead) && ! empty($lead['is_new']) ? 'created_customer' : 'email_only',
    ];
}

function cs_site_zelle_expiration_for_billing(string $billing, ?string $currentExpiration = null): string
{
    $base = time();
    if ($currentExpiration) {
        $current = strtotime($currentExpiration);
        if ($current && $current > $base) {
            $base = $current;
        }
    }

    return match ($billing) {
        'yearly', 'annual' => date('Y-m-d H:i:s', strtotime('+365 days', $base)),
        'lifetime' => date('Y-m-d H:i:s', strtotime('+3650 days', $base)),
        default => date('Y-m-d H:i:s', strtotime('+30 days', $base)),
    };
}

function cs_site_zelle_issue_or_extend_license(PDO $pdo, array $customer, string $planKey, string $billing, ?float $amount, array $paymentContext = []): array
{
    $email = cs_site_zelle_normalize_email((string) ($customer['email'] ?? ''));
    $name = cs_site_zelle_clean_text((string) ($customer['name'] ?? ''), 255);
    $planKey = function_exists('creditsoft_site_license_plan_key') ? creditsoft_site_license_plan_key($planKey) : $planKey;
    $existing = is_array($customer['license'] ?? null) ? $customer['license'] : null;

    if ($email !== '') {
        if ($planKey === 'cluster') {
            $existing = cs_site_zelle_cluster_license_by_email($pdo, $email);
        } elseif (! $existing || (string) ($existing['plan'] ?? '') === 'cluster') {
            $existing = cs_site_zelle_main_license_by_email($pdo, $email) ?? cs_site_zelle_license_by_email($pdo, $email);
        }
    }

    if ($email === '') {
        return [
            'success' => false,
            'message' => 'No usable customer email was found, so CreditSoft could not issue the license automatically.',
        ];
    }

    if ($planKey === 'cluster' && ! cs_site_zelle_main_license_by_email($pdo, $email)) {
        return [
            'success' => false,
            'message' => 'A node or cluster license requires an active main CreditSoft license first.',
        ];
    }

    $expiresAt = cs_site_zelle_expiration_for_billing($billing, (string) ($existing['expires_at'] ?? ''));
    $subscriptionAmount = is_numeric($paymentContext['subscription_amount'] ?? null)
        ? round((float) $paymentContext['subscription_amount'], 2)
        : $amount;
    $paidAmount = is_numeric($paymentContext['paid_amount'] ?? null)
        ? round((float) $paymentContext['paid_amount'], 2)
        : $amount;

    if ($existing) {
        $licenseId = (int) $existing['id'];
        $licenseKey = (string) $existing['license_key'];
        $stmt = $pdo->prepare(
            "UPDATE licenses
             SET customer_email = ?, customer_name = ?, plan = ?, status = 'active', expires_at = ?
             WHERE id = ?"
        );
        $stmt->execute([$email, $name !== '' ? $name : null, $planKey, $expiresAt, $licenseId]);
        $action = 'renewed';
    } else {
        $licenseKey = function_exists('cs_site_admin_generate_license_key')
            ? cs_site_admin_generate_license_key()
            : rtrim(chunk_split(strtoupper(bin2hex(random_bytes(16))), 4, '-'), '-');
        $stmt = $pdo->prepare(
            "INSERT INTO licenses (license_key, customer_email, customer_name, plan, status, created_at, expires_at, ip_address)
             VALUES (?, ?, ?, ?, 'active', NOW(), ?, ?)"
        );
        $stmt->execute([
            $licenseKey,
            $email,
            $name !== '' ? $name : null,
            $planKey,
            $expiresAt,
            $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
        $licenseId = (int) $pdo->lastInsertId();
        $action = 'created';
    }

    $existingSubscription = $pdo->prepare('SELECT id FROM license_subscriptions WHERE license_id = ? ORDER BY id ASC LIMIT 1');
    $existingSubscription->execute([$licenseId]);
    $subscriptionId = (int) $existingSubscription->fetchColumn();

    if ($subscriptionId > 0) {
        $subscription = $pdo->prepare(
            "UPDATE license_subscriptions
             SET billing_cycle = ?, amount = ?, next_billing = ?, auto_renew = 0,
                 last_payment_at = NOW(), last_payment_status = 'paid',
                 failed_attempts = 0, updated_at = NOW()
             WHERE id = ?"
        );
        $subscription->execute([$billing, $subscriptionAmount, $expiresAt, $subscriptionId]);
    } else {
        $subscription = $pdo->prepare(
            "INSERT INTO license_subscriptions (license_id, billing_cycle, amount, next_billing, auto_renew, last_payment_at, last_payment_status, created_at, updated_at)
             VALUES (?, ?, ?, ?, 0, NOW(), 'paid', NOW(), NOW())"
        );
        $subscription->execute([$licenseId, $billing, $subscriptionAmount, $expiresAt]);
    }

    $transactionId = cs_site_zelle_clean_text((string) ($paymentContext['transaction_id'] ?? ''), 255);
    $paymentMethod = cs_site_zelle_clean_text((string) ($paymentContext['payment_method'] ?? 'Zelle'), 80) ?: 'Zelle';
    $paymentProvider = cs_site_zelle_clean_text((string) ($paymentContext['payment_provider'] ?? strtolower(str_replace(' ', '_', $paymentMethod))), 32) ?: 'zelle';
    $logAction = preg_replace('/[^a-z0-9_]+/', '_', strtolower($paymentProvider)) ?: 'payment';
    $details = $paymentMethod.' payment matched and license '.$action.'.';
    if ($transactionId !== '') {
        $details .= ' Transaction ID: '.$transactionId.'.';
    }
    if (isset($paymentContext['expected_amount'])) {
        $details .= ' Expected: '.cs_site_zelle_money((float) $paymentContext['expected_amount']).'.';
    }
    if ($paidAmount !== null) {
        $details .= ' Paid: '.cs_site_zelle_money($paidAmount).'.';
    }
    if ($paidAmount !== null && isset($paymentContext['expected_amount'])) {
        $creditForward = round($paidAmount - (float) $paymentContext['expected_amount'], 2);
        if ($creditForward > 0.02) {
            $details .= ' Credit next month: '.cs_site_zelle_money($creditForward).'.';
        }
    }

    $log = $pdo->prepare('INSERT INTO license_logs (license_id, action, ip_address, details) VALUES (?, ?, ?, ?)');
    $log->execute([
        $licenseId,
        mb_substr($logAction.'_'.$action, 0, 80),
        $_SERVER['REMOTE_ADDR'] ?? null,
        $details,
    ]);

    if (is_array($customer['lead'] ?? null) && ! empty($customer['lead']['id'])) {
        $leadUpdate = $pdo->prepare("UPDATE leads SET status = 'converted', updated_at = NOW() WHERE id = ?");
        $leadUpdate->execute([(int) $customer['lead']['id']]);
    }

    return [
        'success' => true,
        'license_id' => $licenseId,
        'license_key' => $licenseKey,
        'expires_at' => $expiresAt,
        'action' => $action,
    ];
}

function cs_site_zelle_onboarding_link(PDO $pdo, ?int $leadId, ?int $licenseId, string $email): ?string
{
    $email = cs_site_zelle_normalize_email($email);

    if ($email === '') {
        return null;
    }

    $token = bin2hex(random_bytes(24));
    $hash = hash('sha256', $token);
    $expiresAt = date('Y-m-d H:i:s', strtotime('+14 days'));
    $stmt = $pdo->prepare(
        "INSERT INTO customer_onboarding_tokens (lead_id, license_id, email, token_hash, purpose, expires_at, created_at)
         VALUES (?, ?, ?, ?, 'finish_onboarding', ?, NOW())"
    );
    $stmt->execute([$leadId ?: null, $licenseId ?: null, $email, $hash, $expiresAt]);

    return rtrim(cs_site_admin_base_url(), '/').'/onboarding?token='.$token;
}

function cs_site_zelle_absolute_public_url(string $path = '/', array $query = []): string
{
    $path = '/' . ltrim($path, '/');
    $base = defined('SITE_URL') && trim((string) SITE_URL) !== ''
        ? rtrim((string) SITE_URL, '/')
        : 'https://www.creditsoft.app';

    if (function_exists('cs_site_build_url')) {
        return cs_site_build_url($base . $path, $query);
    }

    $queryString = $query === [] ? '' : http_build_query($query);

    return $base . $path . ($queryString !== '' ? '?' . $queryString : '');
}

function cs_site_zelle_email_tracking_url(string $token): string
{
    $token = cs_site_zelle_clean_text($token, 80);

    return $token !== '' ? cs_site_zelle_absolute_public_url('/email-open.php', ['t' => $token]) : '';
}

function cs_site_zelle_contact_card_url(): string
{
    return cs_site_zelle_absolute_public_url('/assets/creditsoft-contact.vcf');
}

function cs_site_zelle_license_receipt_context(array $expectedPayment = [], ?float $amount = null, string $transactionId = ''): array
{
    $paidAmount = $amount;

    if ($paidAmount === null && is_numeric($expectedPayment['paid_amount'] ?? null)) {
        $paidAmount = (float) $expectedPayment['paid_amount'];
    }

    if ($paidAmount === null && is_numeric($expectedPayment['expected_amount'] ?? null)) {
        $paidAmount = (float) $expectedPayment['expected_amount'];
    }

    return [
        'payment_method' => cs_site_zelle_clean_text((string) ($expectedPayment['payment_method'] ?? 'Zelle'), 80) ?: 'Zelle',
        'transaction_status' => 'Transaction Complete',
        'amount_paid' => $paidAmount,
        'plan_name' => cs_site_zelle_clean_text((string) ($expectedPayment['plan_name'] ?? ''), 120),
        'plan_key' => cs_site_zelle_clean_text((string) ($expectedPayment['plan_key'] ?? ''), 80),
        'billing' => cs_site_zelle_clean_text((string) ($expectedPayment['billing'] ?? ''), 60),
        'transaction_id' => cs_site_zelle_clean_text($transactionId, 255),
    ];
}

function cs_site_zelle_record_email_open(string $token): bool
{
    $token = cs_site_zelle_clean_text($token, 80);

    if ($token === '') {
        return false;
    }

    $pdo = cs_site_admin_db();

    if (! $pdo instanceof PDO) {
        return false;
    }

    try {
        cs_site_zelle_ensure_tables($pdo);
        $stmt = $pdo->prepare(
            "UPDATE zelle_payment_messages
             SET email_opened_at = COALESCE(email_opened_at, NOW()),
                 email_last_opened_at = NOW(),
                 email_open_count = email_open_count + 1,
                 updated_at = NOW()
             WHERE email_tracking_token = ?
             LIMIT 1"
        );
        $stmt->execute([$token]);

        return $stmt->rowCount() > 0;
    } catch (Throwable) {
        return false;
    }
}

function cs_site_zelle_email_escape(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function cs_site_zelle_branded_email(array $content): string
{
    $tone = (string) ($content['tone'] ?? 'standard');
    $accent = $tone === 'warning' ? '#f59e0b' : '#facc15';
    $accentSoft = $tone === 'warning' ? '#fff7ed' : '#fffbeb';
    $accentText = $tone === 'warning' ? '#9a3412' : '#713f12';
    $logoUrl = cs_site_zelle_absolute_public_url('/assets/images/creditsoft-wordmark.png');
    $preheader = cs_site_zelle_email_escape((string) ($content['preheader'] ?? 'CreditSoft update'));
    $eyebrow = cs_site_zelle_email_escape((string) ($content['eyebrow'] ?? 'CreditSoft'));
    $title = cs_site_zelle_email_escape((string) ($content['title'] ?? 'CreditSoft update'));
    $intro = cs_site_zelle_email_escape((string) ($content['intro'] ?? ''));
    $actionUrl = trim((string) ($content['action_url'] ?? ''));
    $actionLabel = cs_site_zelle_email_escape((string) ($content['action_label'] ?? 'Open CreditSoft'));
    $secondaryUrl = trim((string) ($content['secondary_url'] ?? ''));
    $secondaryLabel = cs_site_zelle_email_escape((string) ($content['secondary_label'] ?? 'View billing history'));
    $footerNote = cs_site_zelle_email_escape((string) ($content['footer_note'] ?? 'If anything looks off, reply to this email and we will help.'));
    $trackingUrl = trim((string) ($content['tracking_url'] ?? ''));
    $paragraphHtml = '';

    foreach ((array) ($content['paragraphs'] ?? []) as $paragraph) {
        $paragraph = trim((string) $paragraph);
        if ($paragraph !== '') {
            $paragraphHtml .= '<p style="margin:0 0 16px;color:#475569;font-size:15px;line-height:1.65;">' . cs_site_zelle_email_escape($paragraph) . '</p>';
        }
    }

    $summaryRows = '';
    foreach ((array) ($content['summary'] ?? []) as $label => $value) {
        $label = trim((string) $label);
        $value = trim((string) $value);
        if ($label === '' || $value === '') {
            continue;
        }

        $summaryRows .= '<tr>'
            . '<td style="padding:12px 0;border-bottom:1px solid #e5e7eb;color:#64748b;font-size:12px;letter-spacing:.12em;text-transform:uppercase;font-weight:700;">' . cs_site_zelle_email_escape($label) . '</td>'
            . '<td style="padding:12px 0;border-bottom:1px solid #e5e7eb;text-align:right;color:#111827;font-size:15px;font-weight:800;">' . cs_site_zelle_email_escape($value) . '</td>'
            . '</tr>';
    }

    $summaryHtml = $summaryRows !== ''
        ? '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;margin:22px 0 24px;">' . $summaryRows . '</table>'
        : '';
    $buttonHtml = $actionUrl !== ''
        ? '<div style="margin:28px 0 10px;"><a href="' . cs_site_zelle_email_escape($actionUrl) . '" style="display:inline-block;background:#111827;color:#ffffff;text-decoration:none;border-radius:14px;padding:14px 20px;font-size:14px;font-weight:800;letter-spacing:.02em;">' . $actionLabel . '</a></div>'
        : '';
    $secondaryHtml = $secondaryUrl !== ''
        ? '<p style="margin:12px 0 0;color:#64748b;font-size:13px;line-height:1.6;">You can also <a href="' . cs_site_zelle_email_escape($secondaryUrl) . '" style="color:#111827;font-weight:800;">' . $secondaryLabel . '</a>.</p>'
        : '';
    $trackingHtml = $trackingUrl !== ''
        ? '<img src="' . cs_site_zelle_email_escape($trackingUrl) . '" width="1" height="1" alt="" style="display:block;width:1px;height:1px;max-width:1px;max-height:1px;opacity:0;overflow:hidden;border:0;">'
        : '';

    return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . $title . '</title></head>'
        . '<body style="margin:0;padding:0;background:#f5f2e8;font-family:Arial,Helvetica,sans-serif;color:#111827;">'
        . '<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">' . $preheader . '</div>'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f5f2e8;margin:0;padding:34px 14px;">'
        . '<tr><td align="center">'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%;max-width:640px;border-collapse:separate;border-spacing:0;background:#ffffff;border:1px solid #e5e0d0;border-radius:24px;overflow:hidden;box-shadow:0 22px 50px rgba(15,23,42,.10);">'
        . '<tr><td style="background:#f5f2e8;padding:24px 30px 18px;border-bottom:6px solid ' . $accent . ';">'
        . '<img src="' . cs_site_zelle_email_escape($logoUrl) . '" width="190" alt="CreditSoft" style="display:block;width:190px;max-width:190px;height:auto;border:0;outline:none;text-decoration:none;">'
        . '</td></tr>'
        . '<tr><td style="padding:30px 30px 8px;">'
        . '<div style="display:inline-block;background:' . $accentSoft . ';color:' . $accentText . ';border:1px solid ' . $accent . ';border-radius:999px;padding:8px 12px;font-size:11px;letter-spacing:.16em;text-transform:uppercase;font-weight:800;">' . $eyebrow . '</div>'
        . '<h1 style="margin:18px 0 12px;font-size:30px;line-height:1.08;color:#111827;">' . $title . '</h1>'
        . ($intro !== '' ? '<p style="margin:0 0 18px;color:#475569;font-size:16px;line-height:1.65;">' . $intro . '</p>' : '')
        . $paragraphHtml
        . $summaryHtml
        . $buttonHtml
        . $secondaryHtml
        . '</td></tr>'
        . '<tr><td style="padding:12px 30px 30px;">'
        . '<div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:18px;padding:16px 18px;color:#64748b;font-size:13px;line-height:1.6;">' . $footerNote . '</div>'
        . '<p style="margin:18px 0 0;color:#94a3b8;font-size:12px;line-height:1.6;">CreditSoft App<br>payments@creditsoft.app for Zelle/Cash App payments | hello@creditsoft.app for support</p>'
        . '</td></tr>'
        . '</table>'
        . $trackingHtml
        . '</td></tr></table>'
        . '</body></html>';
}

function cs_site_zelle_smtp_read($socket): string
{
    $response = '';

    while (is_resource($socket) && ! feof($socket)) {
        $line = fgets($socket, 1024);

        if ($line === false) {
            break;
        }

        $response .= $line;

        if (preg_match('/^\d{3}\s/', $line)) {
            break;
        }
    }

    return $response;
}

function cs_site_zelle_smtp_command($socket, string $command, array $acceptedCodes): bool
{
    fwrite($socket, $command . "\r\n");
    $response = cs_site_zelle_smtp_read($socket);
    $GLOBALS['cs_site_zelle_smtp_last_response'] = $response;
    $code = (int) substr($response, 0, 3);

    return in_array($code, $acceptedCodes, true);
}

function cs_site_zelle_smtp_data(string $value): string
{
    $value = str_replace(["\r\n", "\r"], "\n", $value);
    $lines = explode("\n", $value);

    foreach ($lines as &$line) {
        if (str_starts_with($line, '.')) {
            $line = '.' . $line;
        }
    }
    unset($line);

    return implode("\r\n", $lines);
}

function cs_site_zelle_send_mail_via_smtp(string $to, string $subject, array $headers, string $message, string $fromEmail): bool
{
    $smtp = cs_site_zelle_smtp_config();

    if (
        (string) ($smtp['host'] ?? '') === ''
        || (string) ($smtp['username'] ?? '') === ''
        || (string) ($smtp['password'] ?? '') === ''
    ) {
        cs_site_zelle_mail_error('SMTP is not configured for license email delivery.');

        return false;
    }

    $host = (string) $smtp['host'];
    $port = (int) ($smtp['port'] ?? 587);
    $security = (string) ($smtp['security'] ?? 'tls');
    $envelopeFrom = cs_site_zelle_normalize_email(
        cs_site_zelle_config_value('CREDITSOFT_SMTP_ENVELOPE_FROM', 'CREDITSOFT_SMTP_ENVELOPE_FROM')
        ?: cs_site_zelle_config_value('CREDITSOFT_ZELLE_SMTP_ENVELOPE_FROM', 'CREDITSOFT_ZELLE_SMTP_ENVELOPE_FROM')
        ?: (string) ($smtp['username'] ?? '')
    ) ?: $fromEmail;
    $target = ($security === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
    $errno = 0;
    $errstr = '';
    $socket = @stream_socket_client($target, $errno, $errstr, 20, STREAM_CLIENT_CONNECT);

    if (! is_resource($socket)) {
        cs_site_zelle_mail_error("SMTP connection failed for {$host}:{$port}. {$errstr}");

        return false;
    }

    stream_set_timeout($socket, 20);

    try {
        $banner = cs_site_zelle_smtp_read($socket);
        if (! str_starts_with($banner, '220')) {
            fclose($socket);
            cs_site_zelle_mail_error('SMTP banner was not accepted. Code '.substr($banner, 0, 3));

            return false;
        }

        $helloHost = parse_url(cs_site_zelle_absolute_public_url('/'), PHP_URL_HOST) ?: 'creditsoft.app';
        if (! cs_site_zelle_smtp_command($socket, 'EHLO ' . $helloHost, [250])) {
            fclose($socket);
            cs_site_zelle_mail_error('SMTP EHLO failed. Code '.substr((string) ($GLOBALS['cs_site_zelle_smtp_last_response'] ?? ''), 0, 3));

            return false;
        }

        if ($security === 'tls') {
            if (! cs_site_zelle_smtp_command($socket, 'STARTTLS', [220])) {
                fclose($socket);
                cs_site_zelle_mail_error('SMTP STARTTLS was rejected. Code '.substr((string) ($GLOBALS['cs_site_zelle_smtp_last_response'] ?? ''), 0, 3));

                return false;
            }

            if (! @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                fclose($socket);
                cs_site_zelle_mail_error('SMTP TLS negotiation failed.');

                return false;
            }

            if (! cs_site_zelle_smtp_command($socket, 'EHLO ' . $helloHost, [250])) {
                fclose($socket);
                cs_site_zelle_mail_error('SMTP EHLO after TLS failed. Code '.substr((string) ($GLOBALS['cs_site_zelle_smtp_last_response'] ?? ''), 0, 3));

                return false;
            }
        }

        if (
            ! cs_site_zelle_smtp_command($socket, 'AUTH LOGIN', [334])
            || ! cs_site_zelle_smtp_command($socket, base64_encode((string) $smtp['username']), [334])
            || ! cs_site_zelle_smtp_command($socket, base64_encode((string) $smtp['password']), [235])
        ) {
            fclose($socket);
            cs_site_zelle_mail_error('SMTP authentication failed. Code '.substr((string) ($GLOBALS['cs_site_zelle_smtp_last_response'] ?? ''), 0, 3));

            return false;
        }

        if (! cs_site_zelle_smtp_command($socket, 'MAIL FROM:<' . $envelopeFrom . '>', [250])) {
            fclose($socket);
            cs_site_zelle_mail_error('SMTP sender envelope was rejected. Code '.substr((string) ($GLOBALS['cs_site_zelle_smtp_last_response'] ?? ''), 0, 3));

            return false;
        }

        if (! cs_site_zelle_smtp_command($socket, 'RCPT TO:<' . $to . '>', [250, 251])) {
            fclose($socket);
            cs_site_zelle_mail_error('SMTP recipient envelope was rejected. Code '.substr((string) ($GLOBALS['cs_site_zelle_smtp_last_response'] ?? ''), 0, 3));

            return false;
        }

        if (! cs_site_zelle_smtp_command($socket, 'DATA', [354])) {
            fclose($socket);
            cs_site_zelle_mail_error('SMTP DATA command was rejected. Code '.substr((string) ($GLOBALS['cs_site_zelle_smtp_last_response'] ?? ''), 0, 3));

            return false;
        }

        $date = date('r');
        $payload = implode("\r\n", array_merge([
            'Date: ' . $date,
            'To: <' . $to . '>',
            'Subject: ' . $subject,
        ], $headers)) . "\r\n\r\n" . $message;

        fwrite($socket, cs_site_zelle_smtp_data($payload) . "\r\n.\r\n");
        $finalResponse = cs_site_zelle_smtp_read($socket);
        $sent = str_starts_with($finalResponse, '250');
        if (! $sent) {
            cs_site_zelle_mail_error('SMTP accepted DATA but did not queue the message. Code '.substr($finalResponse, 0, 3));
        }
        cs_site_zelle_smtp_command($socket, 'QUIT', [221, 250]);
        fclose($socket);

        return $sent;
    } catch (Throwable $exception) {
        if (is_resource($socket)) {
            fclose($socket);
        }

        cs_site_zelle_mail_error('SMTP send failed: '.$exception->getMessage());

        return false;
    }
}

function cs_site_zelle_send_mail(string $to, string $subject, string $body, ?string $htmlBody = null): bool
{
    cs_site_zelle_mail_error('');
    $to = cs_site_zelle_normalize_email($to);

    if ($to === '') {
        cs_site_zelle_mail_error('Recipient email address is invalid.');

        return false;
    }

    $config = cs_site_zelle_mailbox_config();
    $fromEmail = cs_site_zelle_normalize_email((string) ($config['from_email'] ?? 'hello@creditsoft.app')) ?: 'hello@creditsoft.app';
    $fromName = cs_site_zelle_clean_text((string) ($config['from_name'] ?? 'CreditSoft'), 120) ?: 'CreditSoft';
    $headers = [
        'From: '.$fromName.' <'.$fromEmail.'>',
        'Reply-To: '.$fromEmail,
        'MIME-Version: 1.0',
    ];

    if ($htmlBody !== null && trim($htmlBody) !== '') {
        $boundary = 'creditsoft_' . bin2hex(random_bytes(12));
        $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';
        $message = '--' . $boundary . "\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: 8bit\r\n\r\n"
            . $body . "\r\n\r\n"
            . '--' . $boundary . "\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: 8bit\r\n\r\n"
            . $htmlBody . "\r\n\r\n"
            . '--' . $boundary . '--';

        if (cs_site_zelle_send_mail_via_smtp($to, $subject, $headers, $message, $fromEmail)) {
            return true;
        }

        $smtpError = cs_site_zelle_mail_error();
        if (mail($to, $subject, $message, implode("\r\n", $headers))) {
            return true;
        }

        cs_site_zelle_mail_error(trim($smtpError.' Server mail returned false while sending the message.'));

        return false;
    }

    $headers[] = 'Content-Type: text/plain; charset=UTF-8';

    if (cs_site_zelle_send_mail_via_smtp($to, $subject, $headers, $body, $fromEmail)) {
        return true;
    }

    $smtpError = cs_site_zelle_mail_error();
    if (mail($to, $subject, $body, implode("\r\n", $headers))) {
        return true;
    }

    cs_site_zelle_mail_error(trim($smtpError.' Server mail returned false while sending the message.'));

    return false;
}

function cs_site_zelle_send_license_email(array $customer, array $license, ?string $onboardingUrl, string $trackingToken = '', array $receipt = []): bool
{
    $email = cs_site_zelle_normalize_email((string) ($customer['email'] ?? ''));

    if ($email === '') {
        return false;
    }

    $name = cs_site_zelle_clean_sender_display_name((string) ($customer['name'] ?? ''));
    $greeting = $name !== '' ? "Hi {$name}," : 'Hi,';
    $portalUrl = cs_site_zelle_absolute_public_url('/client-portal', ['email' => $email]);
    $contactCardUrl = cs_site_zelle_contact_card_url();
    $amountPaid = is_numeric($receipt['amount_paid'] ?? null) ? cs_site_zelle_money((float) $receipt['amount_paid']) : '';
    $billing = cs_site_zelle_label_from_key((string) ($receipt['billing'] ?? ''));
    $planName = cs_site_zelle_clean_text((string) ($receipt['plan_name'] ?? ''), 120);

    if ($planName === '') {
        $planName = cs_site_zelle_label_from_key((string) ($receipt['plan_key'] ?? 'CreditSoft'));
    }

    $planDisplay = trim($planName . ($billing !== '' ? ' / ' . strtolower($billing) : ''));
    $transactionId = cs_site_zelle_clean_text((string) ($receipt['transaction_id'] ?? ''), 255);
    $expires = date('M j, Y', strtotime((string) ($license['expires_at'] ?? 'now')));
    $summary = [
        'Payment method' => cs_site_zelle_clean_text((string) ($receipt['payment_method'] ?? 'Zelle'), 80) ?: 'Zelle',
        'Transaction status' => cs_site_zelle_clean_text((string) ($receipt['transaction_status'] ?? 'Transaction Complete'), 80) ?: 'Transaction Complete',
        'Amount paid' => $amountPaid,
        'Plan' => $planDisplay,
        'Transaction number' => $transactionId,
        'License key' => (string) ($license['license_key'] ?? ''),
        'Expires' => $expires,
        'Email delivery' => 'Sent',
    ];
    $lines = [
        'Transaction Complete',
        '',
        $greeting,
        '',
        'CreditSoft received and matched your payment. This receipt confirms that your license is active.',
        '',
        'Receipt',
    ];

    foreach ($summary as $label => $value) {
        $value = trim((string) $value);
        if ($value !== '') {
            $lines[] = $label . ': ' . $value;
        }
    }

    if ($onboardingUrl) {
        $lines[] = '';
        $lines[] = 'Set your password and finish setup here: '.$onboardingUrl;
    }

    $lines[] = '';
    $lines[] = 'To help future CreditSoft emails avoid spam, add hello@creditsoft.app to your contacts: '.$contactCardUrl;
    $lines[] = '';
    $lines[] = 'If anything looks off, reply to this email and we will help.';
    $lines[] = '';
    $lines[] = 'CreditSoft';

    $html = cs_site_zelle_branded_email([
        'tone' => 'standard',
        'preheader' => 'Transaction complete. Your CreditSoft receipt and license are ready.',
        'eyebrow' => 'Transaction Complete',
        'title' => 'Your CreditSoft receipt and license.',
        'intro' => $greeting . ' CreditSoft received and matched your payment. This receipt confirms that your license is active.',
        'paragraphs' => [
            'Keep this receipt for your records. It includes the payment status, transaction number, license key, expiration date, and setup link.',
            'To help future receipts stay out of spam, add hello@creditsoft.app to your contacts. You can download the CreditSoft contact card below.',
        ],
        'summary' => $summary,
        'action_url' => $onboardingUrl ?: $portalUrl,
        'action_label' => $onboardingUrl ? 'Set your password' : 'Open billing history',
        'secondary_url' => $contactCardUrl,
        'secondary_label' => 'download the CreditSoft contact card',
        'footer_note' => 'If anything looks off, reply to this email and we will help.',
        'tracking_url' => cs_site_zelle_email_tracking_url($trackingToken),
    ]);

    return cs_site_zelle_send_mail($email, 'CreditSoft Transaction Complete - receipt and license', implode("\n", $lines), $html);
}

function cs_site_zelle_send_balance_due_email(array $customer, array $expectedPayment, string $transactionId = '', string $trackingToken = ''): bool
{
    $email = cs_site_zelle_normalize_email((string) ($customer['email'] ?? ''));

    if ($email === '') {
        return false;
    }

    $name = cs_site_zelle_clean_text((string) ($customer['name'] ?? ''), 120);
    $greeting = $name !== '' ? "Hi {$name}," : 'Hi,';
    $paid = isset($expectedPayment['paid_amount']) ? (float) $expectedPayment['paid_amount'] : null;
    $expected = isset($expectedPayment['expected_amount']) ? (float) $expectedPayment['expected_amount'] : null;
    $balance = isset($expectedPayment['balance_due']) ? (float) $expectedPayment['balance_due'] : null;
    $planName = cs_site_zelle_clean_text((string) ($expectedPayment['plan_name'] ?? 'CreditSoft'), 120) ?: 'CreditSoft';
    $billing = cs_site_zelle_clean_text((string) ($expectedPayment['billing'] ?? 'monthly'), 60);
    $paymentMethod = cs_site_zelle_clean_text((string) ($expectedPayment['payment_method'] ?? 'Zelle'), 80) ?: 'Zelle';
    $partialNoDiscount = ! empty($expectedPayment['partial_payment_discount_removed']);
    $requiresMainLicense = ! empty($expectedPayment['requires_main_license']);
    $lines = [
        $greeting,
        '',
        'Thank you. CreditSoft received your '.$paymentMethod.' payment and matched it to your account email.',
        '',
        'Plan: '.$planName.($billing !== '' ? ' / '.$billing : ''),
        'Payment received: '.cs_site_zelle_money($paid),
        'Amount due for this order: '.cs_site_zelle_money($expected),
        'Remaining balance: '.cs_site_zelle_money($balance),
    ];

    if ($transactionId !== '') {
        $lines[] = 'Transaction ID: '.$transactionId;
    }

    $lines[] = '';
    if ($requiresMainLicense) {
        $lines[] = 'A node or cluster license has to attach to an active main CreditSoft license. We could not find a main license for this email, so this payment is being treated as a partial payment toward Enterprise plus the node license.';
    }
    if ($partialNoDiscount) {
        $lines[] = 'Because this was a partial payment, the Zelle/Cash App pay-in-full discount was removed. Discounts only apply when the full discounted total is paid in one payment.';
    }
    $lines[] = 'Please send the remaining balance by Zelle or Cash App and use this email address as the memo/note: '.$email;
    $lines[] = 'Reply to this email with when you expect to send the remaining balance so we can keep the order moving.';
    $lines[] = '';
    $lines[] = 'Once the balance is paid, CreditSoft will finish the license activation or renewal.';
    $lines[] = '';
    $lines[] = 'CreditSoft';

    $replyUrl = 'mailto:hello@creditsoft.app?subject=' . rawurlencode('CreditSoft balance due timing') . '&body=' . rawurlencode("Hi CreditSoft,\n\nI expect to send the remaining balance on:\n\n");
    $htmlSummary = [
        'Plan' => $planName.($billing !== '' ? ' / '.$billing : ''),
        'Payment received' => cs_site_zelle_money($paid),
        'Amount due' => cs_site_zelle_money($expected),
        'Remaining balance' => cs_site_zelle_money($balance),
    ];

    if ($transactionId !== '') {
        $htmlSummary['Transaction ID'] = $transactionId;
    }

    $html = cs_site_zelle_branded_email([
        'tone' => 'warning',
        'preheader' => 'CreditSoft received your '.$paymentMethod.' payment. A remaining balance is still needed.',
        'eyebrow' => 'Balance due',
        'title' => 'Thank you - we received your payment.',
        'intro' => $greeting . ' CreditSoft matched your '.$paymentMethod.' payment to your account email, but the amount came in below the total for this order.',
        'paragraphs' => array_values(array_filter([
            $requiresMainLicense ? 'A node or cluster license has to attach to an active main CreditSoft license. We could not find a main license for this email, so this payment is being treated as a partial payment toward Enterprise plus the node license.' : '',
            $partialNoDiscount ? 'Because this was a partial payment, the Zelle/Cash App pay-in-full discount was removed. Discounts only apply when the full discounted total is paid in one payment.' : '',
            'Please send the remaining balance by Zelle or Cash App and use this email address as the memo/note: '.$email,
            'Reply with when you expect to send the remaining balance so we can keep the order moving.',
            'Once the balance is paid, CreditSoft will finish the license activation or renewal.',
        ])),
        'summary' => $htmlSummary,
        'action_url' => $replyUrl,
        'action_label' => 'Reply with payment timing',
        'secondary_url' => cs_site_zelle_absolute_public_url('/client-portal', ['email' => $email]),
        'secondary_label' => 'open the billing history',
        'footer_note' => 'No license is activated from a short payment. This protects the customer record and keeps the billing history clean.',
        'tracking_url' => cs_site_zelle_email_tracking_url($trackingToken),
    ]);

    return cs_site_zelle_send_mail($email, 'CreditSoft payment received - remaining balance needed', implode("\n", $lines), $html);
}

function cs_site_zelle_process_message(PDO $pdo, array $message, bool $force = false): array
{
    $mailbox = (string) ($message['mailbox'] ?? 'z@creditsoft.app');
    $uid = (string) ($message['uid'] ?? '');

    if ($uid === '' || (! $force && cs_site_zelle_message_seen($pdo, $mailbox, $uid))) {
        return ['status' => 'skipped', 'message' => 'Message already processed.'];
    }

    $subject = cs_site_zelle_clean_text((string) ($message['subject'] ?? ''), 500);
    $body = (string) ($message['body'] ?? '');
    $headerTrust = is_array($message['header_trust'] ?? null)
        ? $message['header_trust']
        : cs_site_zelle_validate_headers('', (string) ($message['from_email'] ?? ''));
    $headerTrust = cs_site_zelle_apply_forwarded_payment_trust($headerTrust, $subject, $body, (string) ($message['from_email'] ?? ''));

    if (
        ! cs_site_zelle_header_is_trusted_payment_sender($headerTrust)
        && ! cs_site_zelle_header_has_trusted_payment_domain($headerTrust)
    ) {
        return [
            'status' => 'ignored',
            'message' => 'Ignored non-payment sender. Only trusted Zelle/Cash App sender domains are processed.',
            'from_email' => cs_site_zelle_normalize_email((string) ($message['from_email'] ?? '')),
        ];
    }

    if (! cs_site_zelle_message_has_payment_signal($subject, $body)) {
        return [
            'status' => 'ignored',
            'message' => 'Ignored trusted sender message without payment language.',
            'from_email' => cs_site_zelle_normalize_email((string) ($message['from_email'] ?? '')),
        ];
    }

    $provider = cs_site_zelle_detect_payment_provider($subject, $body, (string) ($message['from_email'] ?? ''), $headerTrust);
    if ((string) ($provider['key'] ?? '') === '') {
        return [
            'status' => 'ignored',
            'message' => 'Ignored trusted sender message because it did not match a supported Zelle or Cash App payment notice.',
            'from_email' => cs_site_zelle_normalize_email((string) ($message['from_email'] ?? '')),
            'subject' => $subject,
        ];
    }

    $amount = cs_site_zelle_extract_amount($subject, $body);
    $transactionId = cs_site_zelle_extract_transaction_id($subject, $body, (string) ($message['message_id'] ?? ''));
    $senderName = cs_site_zelle_extract_sender_name($subject, $body, (string) ($message['from_name'] ?? ''));
    $memoText = cs_site_zelle_extract_memo_text($subject, $body);
    $memoEmails = $memoText !== '' ? cs_site_zelle_extract_emails('', $memoText, (string) ($message['from_email'] ?? '')) : [];
    $bodyEmails = cs_site_zelle_extract_emails($subject, $body, (string) ($message['from_email'] ?? ''));
    $aliasEmail = $memoEmails === [] ? cs_site_zelle_alias_email_for_name($senderName) : '';
    $emails = $memoEmails !== []
        ? $memoEmails
        : array_values(array_unique(array_filter(array_merge(
            $aliasEmail !== '' ? [$aliasEmail] : [],
            $bodyEmails,
        ))));
    $senderPhone = cs_site_zelle_extract_phone($subject, $body);
    $payment = [
        'amount' => $amount,
        'emails' => $emails,
        'sender_name' => $senderName,
        'sender_phone' => $senderPhone,
        'transaction_id' => $transactionId,
        'memo_text' => $memoText,
        'match_text' => $subject."\n".$body."\n".$memoText,
        'payment_provider' => (string) ($provider['key'] ?? 'zelle'),
    ];
    $notice = cs_site_zelle_match_notice($payment);
    $plan = $notice
        ? [
            'plan_key' => function_exists('creditsoft_site_license_plan_key')
                ? creditsoft_site_license_plan_key((string) ($notice['plan'] ?? 'enterprise'))
                : (string) ($notice['plan'] ?? 'enterprise'),
            'billing' => (string) ($notice['billing'] ?? 'monthly'),
        ]
        : cs_site_zelle_detect_plan_from_amount($amount);
    $expectedPayment = cs_site_zelle_expected_payment($plan, $notice, $amount);
    $expectedPayment['payment_method'] = (string) ($provider['label'] ?? 'Zelle');
    $expectedPayment['payment_provider'] = (string) ($provider['key'] ?? 'zelle');
    $plan = [
        'plan_key' => (string) ($expectedPayment['plan_key'] ?? ($plan['plan_key'] ?? 'enterprise')),
        'billing' => (string) ($expectedPayment['billing'] ?? ($plan['billing'] ?? 'monthly')),
    ];
    $customer = cs_site_zelle_resolve_customer($pdo, $payment, $notice);
    $expectedPayment = cs_site_zelle_enforce_node_license_dependency($pdo, $customer, $expectedPayment, $amount);
    $expectedPayment = cs_site_zelle_promote_matched_customer_overpay($expectedPayment, $customer, $amount);
    $license = null;
    $onboardingUrl = null;
    $emailSent = false;
    $balanceEmailSent = false;
    $emailTrackingToken = bin2hex(random_bytes(16));
    $status = 'needs_review';
    $paymentStatus = (string) ($expectedPayment['status'] ?? 'unknown');
    $plan = [
        'plan_key' => (string) ($expectedPayment['plan_key'] ?? ($plan['plan_key'] ?? 'enterprise')),
        'billing' => (string) ($expectedPayment['billing'] ?? ($plan['billing'] ?? 'monthly')),
    ];
    $canAutoProcess = $amount !== null
        && $customer['email'] !== ''
        && ! empty($headerTrust['trusted'])
        && in_array($paymentStatus, ['paid', 'overpaid'], true);

    if ($canAutoProcess) {
        $license = cs_site_zelle_issue_or_extend_license(
            $pdo,
            $customer,
            (string) ($plan['plan_key'] ?? 'enterprise'),
            (string) ($plan['billing'] ?? 'monthly'),
            $amount,
            [
                'transaction_id' => $transactionId,
                'expected_amount' => $expectedPayment['expected_amount'] ?? null,
                'paid_amount' => $amount,
                'subscription_amount' => $expectedPayment['expected_amount'] ?? $amount,
                'payment_status' => $paymentStatus,
                'payment_method' => (string) ($provider['label'] ?? 'Zelle'),
                'payment_provider' => (string) ($provider['key'] ?? 'zelle'),
            ],
        );

        if (! empty($license['success'])) {
            $leadId = is_array($customer['lead'] ?? null) ? (int) ($customer['lead']['id'] ?? 0) : null;
            $onboardingUrl = cs_site_zelle_onboarding_link($pdo, $leadId, (int) $license['license_id'], (string) $customer['email']);
            $emailSent = cs_site_zelle_send_license_email(
                $customer,
                $license,
                $onboardingUrl,
                $emailTrackingToken,
                cs_site_zelle_license_receipt_context($expectedPayment, $amount, $transactionId),
            );
            $status = 'processed';
        }
    } elseif (
        $amount !== null
        && $customer['email'] !== ''
        && ! empty($headerTrust['trusted'])
        && $paymentStatus === 'balance_due'
    ) {
        $balanceEmailSent = cs_site_zelle_send_balance_due_email($customer, $expectedPayment, $transactionId, $emailTrackingToken);
        $emailSent = $balanceEmailSent;
        $status = 'balance_due';
    }

    $matchType = (string) ($customer['match_type'] ?? 'needs_review');
    if (empty($headerTrust['trusted'])) {
        $matchType = 'untrusted_sender';
    } elseif ($paymentStatus === 'balance_due') {
        $matchType = 'balance_due';
    } elseif (! in_array($paymentStatus, ['paid', 'overpaid'], true)) {
        $matchType = $matchType !== '' && $matchType !== 'needs_review'
            ? $matchType . '_' . $paymentStatus
            : $paymentStatus;
    }

    $stmt = $pdo->prepare(
        "INSERT INTO zelle_payment_messages (
            mailbox, payment_provider, message_uid, message_id, transaction_id, received_at, from_name, from_email, subject, body_excerpt,
            amount, expected_amount, balance_due, sender_name, sender_email, sender_phone, plan_key, billing, status, payment_status, match_type,
            lead_id, license_id, license_key, onboarding_url, email_tracking_token, email_sent_at, balance_email_sent_at, metadata_json, processed_at, created_at, updated_at
        ) VALUES (
            :mailbox, :payment_provider, :message_uid, :message_id, :transaction_id, :received_at, :from_name, :from_email, :subject, :body_excerpt,
            :amount, :expected_amount, :balance_due, :sender_name, :sender_email, :sender_phone, :plan_key, :billing, :status, :payment_status, :match_type,
            :lead_id, :license_id, :license_key, :onboarding_url, :email_tracking_token, :email_sent_at, :balance_email_sent_at, :metadata_json, :processed_at, NOW(), NOW()
        )"
    );
    $stmt->execute([
        'mailbox' => $mailbox,
        'payment_provider' => (string) ($provider['key'] ?? 'zelle') ?: 'zelle',
        'message_uid' => $uid,
        'message_id' => cs_site_zelle_clean_text((string) ($message['message_id'] ?? ''), 255) ?: null,
        'transaction_id' => $transactionId ?: null,
        'received_at' => (string) ($message['received_at'] ?? '') ?: gmdate('Y-m-d H:i:s'),
        'from_name' => cs_site_zelle_clean_text((string) ($message['from_name'] ?? ''), 255) ?: null,
        'from_email' => cs_site_zelle_normalize_email((string) ($message['from_email'] ?? '')) ?: null,
        'subject' => $subject ?: null,
        'body_excerpt' => mb_substr(trim(preg_replace('/\s+/', ' ', $body) ?: ''), 0, 2000) ?: null,
        'amount' => $amount,
        'expected_amount' => is_numeric($expectedPayment['expected_amount'] ?? null) ? (float) $expectedPayment['expected_amount'] : null,
        'balance_due' => is_numeric($expectedPayment['balance_due'] ?? null) ? (float) $expectedPayment['balance_due'] : null,
        'sender_name' => $senderName ?: null,
        'sender_email' => cs_site_zelle_normalize_email((string) ($customer['email'] ?? ($emails[0] ?? ''))) ?: null,
        'sender_phone' => $senderPhone ?: null,
        'plan_key' => (string) ($plan['plan_key'] ?? 'enterprise'),
        'billing' => (string) ($plan['billing'] ?? 'monthly'),
        'status' => $status,
        'payment_status' => $paymentStatus,
        'match_type' => $matchType,
        'lead_id' => is_array($customer['lead'] ?? null) ? (int) ($customer['lead']['id'] ?? 0) ?: null : null,
        'license_id' => is_array($license) && ! empty($license['license_id']) ? (int) $license['license_id'] : null,
        'license_key' => is_array($license) && ! empty($license['license_key']) ? (string) $license['license_key'] : null,
        'onboarding_url' => $onboardingUrl,
        'email_tracking_token' => ($emailSent || $balanceEmailSent) ? $emailTrackingToken : null,
        'email_sent_at' => $emailSent ? gmdate('Y-m-d H:i:s') : null,
        'balance_email_sent_at' => $balanceEmailSent ? gmdate('Y-m-d H:i:s') : null,
        'metadata_json' => json_encode([
            'notice' => $notice,
            'payment_provider' => $provider,
            'all_emails' => $emails,
            'transaction_id' => $transactionId,
            'expected_payment' => $expectedPayment,
            'email_detection' => [
                'memo_text' => $memoText,
                'memo_emails' => $memoEmails,
                'body_emails' => $bodyEmails,
                'payer_alias_email' => $aliasEmail,
            ],
            'header_trust' => $headerTrust,
            'license_result' => $license,
        ], JSON_UNESCAPED_SLASHES),
        'processed_at' => $status === 'processed' ? gmdate('Y-m-d H:i:s') : null,
    ]);

    return [
        'status' => $status,
        'amount' => $amount,
        'expected_amount' => $expectedPayment['expected_amount'] ?? null,
        'balance_due' => $expectedPayment['balance_due'] ?? null,
        'payment_status' => $paymentStatus,
        'payment_provider' => (string) ($provider['key'] ?? 'zelle'),
        'email' => (string) ($customer['email'] ?? ''),
        'license_key' => is_array($license) ? (string) ($license['license_key'] ?? '') : '',
        'email_sent' => $emailSent,
    ];
}

function cs_site_zelle_retry_message(PDO $pdo, int $messageId): array
{
    if ($messageId <= 0) {
        return ['success' => false, 'message' => 'No payment message was selected.'];
    }

    cs_site_zelle_ensure_tables($pdo);

    $stmt = $pdo->prepare('SELECT * FROM zelle_payment_messages WHERE id = ? LIMIT 1');
    $stmt->execute([$messageId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (! is_array($row)) {
        return ['success' => false, 'message' => 'That payment message could not be found.'];
    }

    if (! in_array((string) ($row['status'] ?? ''), ['needs_review', 'balance_due'], true)) {
        return ['success' => false, 'message' => 'Only needs-review or balance-due payment messages can be retried from this screen.'];
    }

    $metadata = json_decode((string) ($row['metadata_json'] ?? ''), true);
    $message = [
        'mailbox' => (string) ($row['mailbox'] ?? 'z@creditsoft.app'),
        'uid' => (string) ($row['message_uid'] ?? ''),
        'message_id' => (string) ($row['message_id'] ?? ''),
        'received_at' => (string) ($row['received_at'] ?? $row['created_at'] ?? gmdate('Y-m-d H:i:s')),
        'from_name' => (string) ($row['from_name'] ?? ''),
        'from_email' => (string) ($row['from_email'] ?? ''),
        'subject' => (string) ($row['subject'] ?? ''),
        'body' => (string) ($row['body_excerpt'] ?? ''),
        'header_trust' => is_array($metadata['header_trust'] ?? null) ? $metadata['header_trust'] : [],
    ];

    $pdo->beginTransaction();

    try {
        $delete = $pdo->prepare('DELETE FROM zelle_payment_messages WHERE id = ?');
        $delete->execute([$messageId]);

        $result = cs_site_zelle_process_message($pdo, $message, true);
        $pdo->commit();

        return [
            'success' => true,
            'message' => 'Payment message retried. Result: ' . (string) ($result['status'] ?? 'unknown') . '.',
            'result' => $result,
        ];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        return [
            'success' => false,
            'message' => $exception->getMessage(),
        ];
    }
}

function cs_site_zelle_catalog_row(string $planKey = 'enterprise', string $billing = 'monthly'): array
{
    $planKey = creditsoft_site_license_plan_key($planKey);
    $billing = in_array($billing, ['monthly', 'yearly'], true) ? $billing : 'monthly';

    foreach (cs_site_zelle_expected_amount_catalog() as $row) {
        if (
            (string) ($row['plan_key'] ?? '') === $planKey
            && (string) ($row['billing'] ?? '') === $billing
            && ($row['addons'] ?? []) === []
        ) {
            return $row;
        }
    }

    return cs_site_zelle_default_payment_plan();
}

function cs_site_zelle_force_issue_message(PDO $pdo, int $messageId, string $planKey = 'enterprise', string $billing = 'monthly', ?float $amountOverride = null): array
{
    if ($messageId <= 0) {
        return ['success' => false, 'message' => 'No payment message was selected.'];
    }

    cs_site_zelle_ensure_tables($pdo);

    $stmt = $pdo->prepare('SELECT * FROM zelle_payment_messages WHERE id = ? LIMIT 1');
    $stmt->execute([$messageId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (! is_array($row)) {
        return ['success' => false, 'message' => 'That payment message could not be found.'];
    }

    $metadata = json_decode((string) ($row['metadata_json'] ?? ''), true);
    $metadata = is_array($metadata) ? $metadata : [];
    $headerTrust = is_array($metadata['header_trust'] ?? null) ? $metadata['header_trust'] : [];

    if (empty($headerTrust['trusted']) && ! cs_site_zelle_domain_is_trusted(cs_site_zelle_email_domain((string) ($row['from_email'] ?? '')), cs_site_zelle_trusted_domains())) {
        return ['success' => false, 'message' => 'Only trusted Zelle/Cash App payment messages can be force-issued.'];
    }

    $email = cs_site_zelle_normalize_email((string) ($row['sender_email'] ?? ''));
    if ($email === '' && is_array($metadata['all_emails'] ?? null)) {
        foreach ($metadata['all_emails'] as $candidate) {
            $email = cs_site_zelle_normalize_email((string) $candidate);
            if ($email !== '') {
                break;
            }
        }
    }

    if ($email === '') {
        return ['success' => false, 'message' => 'No customer email is available on this payment message.'];
    }

    $catalog = cs_site_zelle_catalog_row($planKey, $billing);
    $planKey = (string) ($catalog['plan_key'] ?? creditsoft_site_license_plan_key($planKey));
    $billing = (string) ($catalog['billing'] ?? $billing);
    $metadataExpectedPayment = is_array($metadata['expected_payment'] ?? null) ? $metadata['expected_payment'] : [];
    $expectedAmount = is_numeric($metadataExpectedPayment['expected_amount'] ?? null)
        ? round((float) $metadataExpectedPayment['expected_amount'], 2)
        : (is_numeric($row['expected_amount'] ?? null) && (float) $row['expected_amount'] > 0 && abs((float) $row['expected_amount'] - (float) ($row['amount'] ?? 0)) > 0.02
            ? round((float) $row['expected_amount'], 2)
            : null);
    $expectedAmount = $expectedAmount ?? (is_numeric($catalog['expected_amount'] ?? null)
        ? round((float) $catalog['expected_amount'], 2)
        : (is_numeric($catalog['base_amount'] ?? null) ? round((float) $catalog['base_amount'], 2) : null));
    $paidAmount = $amountOverride !== null
        ? round($amountOverride, 2)
        : (is_numeric($row['amount'] ?? null) ? round((float) $row['amount'], 2) : $expectedAmount);

    if ($paidAmount === null || $paidAmount <= 0) {
        return ['success' => false, 'message' => 'No valid license amount was available for the selected plan.'];
    }

    if ($expectedAmount === null || $expectedAmount <= 0) {
        return ['success' => false, 'message' => 'Pricing is unavailable for the selected plan, so CreditSoft cannot issue a license without a database invoice price.'];
    }

    $paymentStatus = $paidAmount > ($expectedAmount + 0.02) ? 'overpaid' : 'paid';
    $matchType = $paymentStatus === 'overpaid' ? 'owner_override_overpaid' : 'owner_override_paid';

    $customer = [
        'email' => $email,
        'name' => cs_site_zelle_clean_sender_display_name((string) ($row['sender_name'] ?? $row['from_name'] ?? $email)),
        'phone' => cs_site_zelle_clean_text((string) ($row['sender_phone'] ?? ''), 64),
        'lead' => cs_site_zelle_lead_by_email($pdo, $email),
        'license' => cs_site_zelle_license_by_email($pdo, $email),
        'match_type' => 'owner_override',
    ];
    $transactionId = cs_site_zelle_clean_text((string) ($row['transaction_id'] ?? ''), 255);
    $paymentProvider = cs_site_zelle_clean_text((string) ($row['payment_provider'] ?? ($metadata['payment_provider']['key'] ?? 'zelle')), 32) ?: 'zelle';
    $paymentMethod = $paymentProvider === 'cash_app' ? 'Cash App' : 'Zelle';
    $license = cs_site_zelle_issue_or_extend_license($pdo, $customer, $planKey, $billing, $paidAmount, [
        'transaction_id' => $transactionId,
        'expected_amount' => $expectedAmount,
        'paid_amount' => $paidAmount,
        'subscription_amount' => $expectedAmount,
        'payment_status' => $paymentStatus,
        'payment_method' => $paymentMethod,
        'payment_provider' => $paymentProvider,
    ]);

    if (empty($license['success'])) {
        return [
            'success' => false,
            'message' => (string) ($license['message'] ?? 'CreditSoft could not issue the license.'),
        ];
    }

    $leadId = is_array($customer['lead'] ?? null) ? (int) ($customer['lead']['id'] ?? 0) : null;
    $onboardingUrl = cs_site_zelle_onboarding_link($pdo, $leadId, (int) $license['license_id'], $email);
    $emailTrackingToken = bin2hex(random_bytes(16));
    $emailSent = cs_site_zelle_send_license_email(
        $customer,
        $license,
        $onboardingUrl,
        $emailTrackingToken,
        cs_site_zelle_license_receipt_context([
            'plan_key' => $planKey,
            'billing' => $billing,
            'paid_amount' => $paidAmount,
            'expected_amount' => $expectedAmount,
            'payment_status' => $paymentStatus,
            'payment_method' => $paymentMethod,
        ], $paidAmount, $transactionId),
    );
    $metadata['owner_override'] = [
        'at' => gmdate('c'),
        'reason' => 'Trusted payment manually assigned to the customer license.',
        'original_amount' => is_numeric($row['amount'] ?? null) ? (float) $row['amount'] : null,
        'treated_amount' => $paidAmount,
        'expected_amount' => $expectedAmount,
        'payment_status' => $paymentStatus,
        'credit_forward' => max(0, round($paidAmount - $expectedAmount, 2)),
        'plan_key' => $planKey,
        'billing' => $billing,
    ];
    $metadata['expected_payment'] = array_merge(
        is_array($metadata['expected_payment'] ?? null) ? $metadata['expected_payment'] : [],
        [
            'plan_key' => $planKey,
            'billing' => $billing,
            'expected_amount' => $expectedAmount,
            'paid_amount' => $paidAmount,
            'balance_due' => 0.0,
            'credit_forward' => max(0, round($paidAmount - $expectedAmount, 2)),
            'status' => $paymentStatus,
            'delta' => round($paidAmount - $expectedAmount, 2),
        ],
    );
    $metadata['license_result'] = $license;

    $update = $pdo->prepare(
        "UPDATE zelle_payment_messages
             SET amount = ?,
                 expected_amount = ?,
                 balance_due = 0,
                 payment_provider = ?,
                 plan_key = ?,
             billing = ?,
             status = 'processed',
             payment_status = ?,
             match_type = ?,
             sender_email = ?,
             license_id = ?,
             license_key = ?,
             onboarding_url = ?,
             email_tracking_token = ?,
             email_sent_at = ?,
             metadata_json = ?,
             processed_at = NOW(),
             updated_at = NOW()
         WHERE id = ?"
    );
    $update->execute([
        $paidAmount,
        $expectedAmount,
        $paymentProvider,
        $planKey,
        $billing,
        $paymentStatus,
        $matchType,
        $email,
        (int) $license['license_id'],
        (string) $license['license_key'],
        $onboardingUrl,
        $emailSent ? $emailTrackingToken : null,
        $emailSent ? gmdate('Y-m-d H:i:s') : null,
        json_encode($metadata, JSON_UNESCAPED_SLASHES),
        $messageId,
    ]);

    return [
        'success' => true,
        'message' => 'Trusted payment marked paid and license issued.',
        'message_id' => $messageId,
        'license_key' => (string) $license['license_key'],
        'expires_at' => (string) ($license['expires_at'] ?? ''),
        'amount' => $paidAmount,
        'email_sent' => $emailSent,
        'email_preview_url' => cs_site_admin_url('/email-preview.php', ['type' => 'license', 'message_id' => $messageId]),
    ];
}

function cs_site_zelle_resend_license_email(PDO $pdo, int $messageId): array
{
    if ($messageId <= 0) {
        return ['success' => false, 'message' => 'No payment message was selected.'];
    }

    cs_site_zelle_ensure_tables($pdo);

    $stmt = $pdo->prepare('SELECT * FROM zelle_payment_messages WHERE id = ? LIMIT 1');
    $stmt->execute([$messageId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (! is_array($row) || trim((string) ($row['license_key'] ?? '')) === '') {
        return ['success' => false, 'message' => 'That payment does not have a license key to email yet.'];
    }

    $email = cs_site_zelle_normalize_email((string) ($row['sender_email'] ?? ''));
    if ($email === '') {
        return ['success' => false, 'message' => 'That payment does not have a customer email to send to.'];
    }

    $expiresAt = '';
    if (! empty($row['license_id'])) {
        $licenseStmt = $pdo->prepare('SELECT expires_at FROM licenses WHERE id = ? LIMIT 1');
        $licenseStmt->execute([(int) $row['license_id']]);
        $expiresAt = (string) ($licenseStmt->fetchColumn() ?: '');
    }
    $metadata = json_decode((string) ($row['metadata_json'] ?? ''), true);
    $metadata = is_array($metadata) ? $metadata : [];
    $paymentProvider = cs_site_zelle_clean_text((string) ($row['payment_provider'] ?? ($metadata['payment_provider']['key'] ?? 'zelle')), 32) ?: 'zelle';
    $paymentMethod = $paymentProvider === 'cash_app' ? 'Cash App' : 'Zelle';

    $customer = [
        'email' => $email,
        'name' => cs_site_zelle_clean_sender_display_name((string) ($row['sender_name'] ?? $email)),
    ];
    $license = [
        'license_id' => (int) ($row['license_id'] ?? 0),
        'license_key' => (string) $row['license_key'],
        'expires_at' => $expiresAt !== '' ? $expiresAt : date('Y-m-d H:i:s', strtotime('+30 days')),
    ];
    $trackingToken = bin2hex(random_bytes(16));
    $rowAmount = is_numeric($row['amount'] ?? null) ? (float) $row['amount'] : null;
    $sent = cs_site_zelle_send_license_email(
        $customer,
        $license,
        (string) ($row['onboarding_url'] ?? ''),
        $trackingToken,
        cs_site_zelle_license_receipt_context([
            'plan_key' => (string) ($row['plan_key'] ?? ''),
            'billing' => (string) ($row['billing'] ?? ''),
            'paid_amount' => $rowAmount,
            'expected_amount' => is_numeric($row['expected_amount'] ?? null) ? (float) $row['expected_amount'] : null,
            'payment_method' => $paymentMethod,
        ], $rowAmount, (string) ($row['transaction_id'] ?? '')),
    );

    if (! $sent) {
        $mailError = cs_site_zelle_mail_error() ?: 'The server mail path did not report a successful send.';
        $update = $pdo->prepare(
            "UPDATE zelle_payment_messages
             SET email_last_attempt_at = NOW(),
                 email_attempt_count = email_attempt_count + 1,
                 email_last_error = ?,
                 updated_at = NOW()
             WHERE id = ?"
        );
        $update->execute([$mailError, $messageId]);

        return ['success' => false, 'message' => $mailError];
    }

    $update = $pdo->prepare(
        "UPDATE zelle_payment_messages
         SET email_tracking_token = ?,
             email_sent_at = NOW(),
             email_last_attempt_at = NOW(),
             email_attempt_count = email_attempt_count + 1,
             email_last_error = NULL,
             updated_at = NOW()
         WHERE id = ?"
    );
    $update->execute([$trackingToken, $messageId]);

    return ['success' => true, 'message' => 'License email sent.'];
}

function cs_site_zelle_send_pending_license_emails(PDO $pdo, int $limit = 25, bool $ignoreCooldown = false): array
{
    cs_site_zelle_ensure_tables($pdo);

    $cooldownSql = $ignoreCooldown ? '' : "AND (
                email_last_attempt_at IS NULL
                OR email_last_attempt_at < DATE_SUB(NOW(), INTERVAL 10 MINUTE)
           )";
    $stmt = $pdo->prepare(
        "SELECT id
         FROM zelle_payment_messages
         WHERE status = 'processed'
           AND license_key IS NOT NULL
           AND license_key <> ''
           AND sender_email IS NOT NULL
           AND sender_email <> ''
           AND email_sent_at IS NULL
           {$cooldownSql}
         ORDER BY COALESCE(processed_at, created_at) ASC
         LIMIT ?"
    );
    $stmt->bindValue(1, max(1, min(100, $limit)), PDO::PARAM_INT);
    $stmt->execute();
    $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    $summary = [
        'attempted' => 0,
        'sent' => 0,
        'failed' => 0,
        'messages' => [],
    ];

    foreach ($ids as $id) {
        $result = cs_site_zelle_resend_license_email($pdo, $id);
        $summary['attempted']++;

        if (! empty($result['success'])) {
            $summary['sent']++;
        } else {
            $summary['failed']++;
        }

        $summary['messages'][] = [
            'id' => $id,
            'success' => ! empty($result['success']),
            'message' => (string) ($result['message'] ?? ''),
        ];
    }

    return $summary;
}

function cs_site_zelle_process_inbox(int $limit = 25): array
{
    $pdo = cs_site_admin_db();

    if (! $pdo) {
        return [
            'success' => false,
            'error' => 'The admin database is not available.',
            'fetched' => 0,
            'processed' => 0,
            'balance_due' => 0,
            'needs_review' => 0,
            'skipped' => 0,
            'deleted' => 0,
            'messages' => [],
        ];
    }

    cs_site_zelle_ensure_tables($pdo);
    cs_site_zelle_quarantine_untrusted_messages($pdo);

    $fetch = cs_site_zelle_fetch_mailbox_messages($limit);

    if (empty($fetch['success'])) {
        return [
            'success' => false,
            'error' => (string) ($fetch['error'] ?? 'Could not fetch mailbox.'),
            'fetched' => 0,
            'processed' => 0,
            'balance_due' => 0,
            'needs_review' => 0,
            'skipped' => 0,
            'deleted' => 0,
            'messages' => [],
        ];
    }

    $summary = [
        'success' => true,
        'fetched' => count((array) ($fetch['messages'] ?? [])),
        'processed' => 0,
        'balance_due' => 0,
        'needs_review' => 0,
        'skipped' => 0,
        'deleted' => 0,
        'license_email_attempted' => 0,
        'license_email_sent' => 0,
        'license_email_failed' => 0,
        'messages' => [],
    ];

    foreach ((array) ($fetch['messages'] ?? []) as $message) {
        $result = cs_site_zelle_process_message($pdo, $message);
        $summary['messages'][] = $result;
        if (($result['status'] ?? '') === 'processed') {
            $summary['processed']++;
        } elseif (($result['status'] ?? '') === 'balance_due') {
            $summary['balance_due']++;
        } elseif (($result['status'] ?? '') === 'needs_review') {
            $summary['needs_review']++;
        } else {
            $summary['skipped']++;
        }

        if (
            in_array((string) ($result['status'] ?? ''), ['processed', 'balance_due', 'needs_review', 'skipped'], true)
            && cs_site_zelle_delete_mailbox_uid((string) ($message['uid'] ?? ''))
        ) {
            $summary['deleted']++;
        }
    }

    $pendingEmail = cs_site_zelle_send_pending_license_emails($pdo, 25);
    $summary['license_email_attempted'] = (int) ($pendingEmail['attempted'] ?? 0);
    $summary['license_email_sent'] = (int) ($pendingEmail['sent'] ?? 0);
    $summary['license_email_failed'] = (int) ($pendingEmail['failed'] ?? 0);

    return $summary;
}

function cs_site_zelle_public_payment_lookup(array $input): array
{
    $pdo = cs_site_admin_db();

    if (! $pdo instanceof PDO) {
        return ['success' => false, 'status' => 'unavailable', 'message' => 'The payment lookup database is not available right now.'];
    }

    $email = cs_site_zelle_normalize_email((string) ($input['customer_email'] ?? ''));
    $customerName = cs_site_zelle_clean_text((string) ($input['customer_name'] ?? ''), 255);
    $payerName = cs_site_zelle_clean_text((string) ($input['payer_name'] ?? ''), 255);
    $paymentSource = cs_site_zelle_clean_text((string) ($input['payment_source'] ?? ''), 255);
    $memoUsed = cs_site_zelle_clean_text((string) ($input['memo_used'] ?? ''), 500);
    $transactionId = cs_site_zelle_clean_text((string) ($input['transaction_id'] ?? ''), 255);
    $normalizedTransactionId = cs_site_zelle_normalize_transaction_id($transactionId);
    $amount = is_numeric($input['amount'] ?? null) ? round((float) $input['amount'], 2) : null;
    $paymentDate = trim((string) ($input['payment_date'] ?? ''));

    if ($email === '') {
        return ['success' => false, 'status' => 'invalid', 'message' => 'Enter the email you expected the license to use.'];
    }

    if ($normalizedTransactionId === '' && ($amount === null || $amount <= 0)) {
        return ['success' => false, 'status' => 'invalid', 'message' => 'Enter either a transaction/confirmation number or the amount you paid.'];
    }

    if ($normalizedTransactionId === '' && $payerName === '' && $paymentSource === '') {
        return ['success' => false, 'status' => 'invalid', 'message' => 'Enter the name shown on the payment or the Zelle/Cash App source used to send it.'];
    }

    if ($paymentDate !== '' && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $paymentDate)) {
        $paymentDate = '';
    }

    cs_site_zelle_ensure_tables($pdo);

    try {
        cs_site_zelle_process_inbox(25);
    } catch (Throwable) {
        // Lookup can still use already-processed messages if live IMAP is unavailable.
    }

    $stmt = $pdo->query(
        "SELECT *
         FROM zelle_payment_messages
         WHERE created_at >= DATE_SUB(NOW(), INTERVAL 180 DAY)
         ORDER BY created_at DESC
         LIMIT 300"
    );
    $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    $best = null;
    $bestScore = 0;
    $bestReasons = [];
    $inputEmails = array_values(array_unique(array_filter([
        $email,
        cs_site_zelle_normalize_email($paymentSource),
        cs_site_zelle_normalize_email($memoUsed),
    ])));
    $inputPhone = cs_site_zelle_normalize_phone($paymentSource);
    $inputNames = array_values(array_unique(array_filter([
        cs_site_zelle_normalize_name($customerName),
        cs_site_zelle_normalize_name($payerName),
        cs_site_zelle_normalize_name($paymentSource),
    ])));

    foreach ($rows as $row) {
        $score = 0;
        $reasons = [];
        $metadata = json_decode((string) ($row['metadata_json'] ?? ''), true);
        $metadata = is_array($metadata) ? $metadata : [];
        $notice = is_array($metadata['notice'] ?? null) ? $metadata['notice'] : [];
        $rowTransactionId = cs_site_zelle_normalize_transaction_id((string) ($row['transaction_id'] ?? ''));
        $noticeTransactionId = cs_site_zelle_normalize_transaction_id((string) ($notice['payment_transaction_id'] ?? $notice['transaction_id'] ?? ''));

        if ($normalizedTransactionId !== '' && $rowTransactionId !== '' && $normalizedTransactionId === $rowTransactionId) {
            $score += 120;
            $reasons[] = 'bank_transaction';
        } elseif ($normalizedTransactionId !== '' && $noticeTransactionId !== '' && $normalizedTransactionId === $noticeTransactionId) {
            $score += 95;
            $reasons[] = 'customer_confirmation';
        }

        $rowAmount = is_numeric($row['amount'] ?? null) ? round((float) $row['amount'], 2) : null;
        if ($amount !== null && $rowAmount !== null && abs($amount - $rowAmount) <= 0.02) {
            $score += 45;
            $reasons[] = 'amount';
        }

        if ($paymentDate !== '') {
            $receivedAt = substr((string) ($row['received_at'] ?? $row['created_at'] ?? ''), 0, 10);
            if ($receivedAt === $paymentDate) {
                $score += 25;
                $reasons[] = 'date';
            }
        }

        $rowEmails = array_values(array_unique(array_filter([
            cs_site_zelle_normalize_email((string) ($row['sender_email'] ?? '')),
            cs_site_zelle_normalize_email((string) ($row['from_email'] ?? '')),
            cs_site_zelle_normalize_email((string) ($notice['customer_email'] ?? '')),
            cs_site_zelle_normalize_email((string) ($notice['payment_memo_email'] ?? '')),
            cs_site_zelle_normalize_email((string) ($notice['payment_source'] ?? '')),
        ])));

        if ($inputEmails !== [] && array_intersect($inputEmails, $rowEmails) !== []) {
            $score += 55;
            $reasons[] = 'email';
        }

        $rowPhone = cs_site_zelle_normalize_phone((string) ($row['sender_phone'] ?? $notice['customer_phone'] ?? $notice['payment_source'] ?? ''));
        if ($inputPhone !== '' && $rowPhone !== '' && str_ends_with($rowPhone, substr($inputPhone, -7))) {
            $score += 30;
            $reasons[] = 'phone';
        }

        $rowNames = array_values(array_unique(array_filter([
            cs_site_zelle_normalize_name((string) ($row['sender_name'] ?? '')),
            cs_site_zelle_normalize_name((string) ($row['from_name'] ?? '')),
            cs_site_zelle_normalize_name((string) ($notice['office_name'] ?? '')),
            cs_site_zelle_normalize_name((string) ($notice['customer_name'] ?? '')),
        ])));

        foreach ($inputNames as $inputName) {
            foreach ($rowNames as $rowName) {
                if ($inputName !== '' && $rowName !== '' && (str_contains($rowName, $inputName) || str_contains($inputName, $rowName))) {
                    $score += 35;
                    $reasons[] = 'payer_name';
                    break 2;
                }
            }
        }

        if ($score > $bestScore) {
            $best = $row;
            $bestScore = $score;
            $bestReasons = array_values(array_unique($reasons));
        }
    }

    if (! is_array($best) || $bestScore < 75) {
        return [
            'success' => true,
            'status' => 'needs_ticket',
            'message' => 'CreditSoft could not safely match that payment yet. Open a ticket with the same details and attach a screenshot so support can compare it against the bank email.',
            'can_open_ticket' => true,
        ];
    }

    $metadata = json_decode((string) ($best['metadata_json'] ?? ''), true);
    $metadata = is_array($metadata) ? $metadata : [];
    $headerTrust = is_array($metadata['header_trust'] ?? null) ? $metadata['header_trust'] : [];
    $trusted = ! empty($headerTrust['trusted']) || cs_site_zelle_domain_is_trusted(cs_site_zelle_email_domain((string) ($best['from_email'] ?? '')), cs_site_zelle_trusted_domains());
    $status = (string) ($best['status'] ?? '');
    $paymentStatus = (string) ($best['payment_status'] ?? '');
    $licenseKey = cs_site_zelle_clean_text((string) ($best['license_key'] ?? ''), 255);
    $onboardingUrl = trim((string) ($best['onboarding_url'] ?? ''));
    $expectedAmount = is_numeric($best['expected_amount'] ?? null) ? (float) $best['expected_amount'] : null;
    $paidAmount = is_numeric($best['amount'] ?? null) ? (float) $best['amount'] : null;
    $balanceDue = is_numeric($best['balance_due'] ?? null) ? (float) $best['balance_due'] : null;
    $matchedExpectedPayment = is_array($metadata['expected_payment'] ?? null) ? $metadata['expected_payment'] : [];
    $requiresMainLicense = ! empty($matchedExpectedPayment['requires_main_license']);
    $licenseResult = is_array($metadata['license_result'] ?? null) ? $metadata['license_result'] : [];
    $strongProof = in_array('bank_transaction', $bestReasons, true)
        || in_array('customer_confirmation', $bestReasons, true)
        || ($bestScore >= 125 && in_array('amount', $bestReasons, true));

    if ($licenseKey !== '' && $strongProof) {
        return [
            'success' => true,
            'status' => 'license_ready',
            'message' => 'CreditSoft found your matched payment and license.',
            'license_key' => $licenseKey,
            'expires_at' => (string) ($licenseResult['expires_at'] ?? ''),
            'onboarding_url' => $onboardingUrl ?: cs_site_zelle_absolute_public_url('/client-portal', ['email' => $email]),
            'client_portal_url' => cs_site_zelle_absolute_public_url('/client-portal', ['email' => $email]),
            'match_score' => $bestScore,
        ];
    }

    if ($balanceDue !== null && $balanceDue > 0) {
        return [
            'success' => true,
            'status' => 'balance_due',
            'message' => $requiresMainLicense
                ? 'CreditSoft found the node payment, but a node license requires an active main CreditSoft license. Pay the remaining balance for Enterprise plus the node, then submit the new confirmation if it does not finish automatically.'
                : 'CreditSoft found the payment, but it was short. Pay the remaining balance, then submit the new confirmation if it does not finish automatically.',
            'paid_amount' => $paidAmount,
            'expected_amount' => $expectedAmount,
            'balance_due' => $balanceDue,
            'requires_main_license' => $requiresMainLicense,
            'can_open_ticket' => true,
            'match_score' => $bestScore,
        ];
    }

    if ($trusted && $strongProof && in_array($paymentStatus, ['paid', 'overpaid'], true) && in_array($status, ['needs_review', 'balance_due'], true)) {
        $customer = [
            'email' => $email,
            'name' => $customerName !== '' ? $customerName : ($payerName !== '' ? $payerName : $email),
            'phone' => cs_site_zelle_clean_text((string) ($input['customer_phone'] ?? ''), 80),
            'lead' => cs_site_zelle_lead_by_email($pdo, $email),
            'license' => cs_site_zelle_license_by_email($pdo, $email),
            'match_type' => 'self_service_lookup',
        ];
        $planKey = (string) ($best['plan_key'] ?? 'enterprise');
        $billing = (string) ($best['billing'] ?? 'monthly');
        $paymentProvider = cs_site_zelle_clean_text((string) ($best['payment_provider'] ?? ($metadata['payment_provider']['key'] ?? 'zelle')), 32) ?: 'zelle';
        $paymentMethod = $paymentProvider === 'cash_app' ? 'Cash App' : 'Zelle';
        $license = cs_site_zelle_issue_or_extend_license($pdo, $customer, $planKey, $billing, $paidAmount, [
            'transaction_id' => (string) ($best['transaction_id'] ?? $transactionId),
            'expected_amount' => $expectedAmount,
            'payment_status' => 'paid',
            'payment_method' => $paymentMethod,
            'payment_provider' => $paymentProvider,
        ]);

        if (! empty($license['success'])) {
            $leadId = is_array($customer['lead'] ?? null) ? (int) ($customer['lead']['id'] ?? 0) : null;
            $onboardingUrl = cs_site_zelle_onboarding_link($pdo, $leadId, (int) $license['license_id'], $email);
            $emailTrackingToken = bin2hex(random_bytes(16));
            $emailSent = cs_site_zelle_send_license_email(
                $customer,
                $license,
                $onboardingUrl,
                $emailTrackingToken,
                cs_site_zelle_license_receipt_context([
                    'plan_key' => $planKey,
                    'billing' => $billing,
                    'paid_amount' => $paidAmount,
                    'expected_amount' => $expectedAmount,
                    'payment_method' => $paymentMethod,
                ], $paidAmount, (string) ($best['transaction_id'] ?? $transactionId)),
            );
            $metadata['self_service_lookup'] = [
                'at' => gmdate('c'),
                'score' => $bestScore,
                'reasons' => $bestReasons,
                'customer_email' => $email,
            ];
            $metadata['license_result'] = $license;

            $update = $pdo->prepare(
                "UPDATE zelle_payment_messages
                 SET sender_email = ?,
                     status = 'processed',
                     payment_status = 'paid',
                     match_type = 'self_service_lookup',
                     license_id = ?,
                     license_key = ?,
                     onboarding_url = ?,
                     email_tracking_token = ?,
                     email_sent_at = ?,
                     metadata_json = ?,
                     processed_at = NOW(),
                     updated_at = NOW()
                 WHERE id = ?"
            );
            $update->execute([
                $email,
                (int) $license['license_id'],
                (string) $license['license_key'],
                $onboardingUrl,
                $emailSent ? $emailTrackingToken : null,
                $emailSent ? gmdate('Y-m-d H:i:s') : null,
                json_encode($metadata, JSON_UNESCAPED_SLASHES),
                (int) $best['id'],
            ]);

            return [
                'success' => true,
                'status' => 'license_issued',
                'message' => 'CreditSoft matched your payment and issued the license.',
                'license_key' => (string) $license['license_key'],
                'expires_at' => (string) ($license['expires_at'] ?? ''),
                'onboarding_url' => $onboardingUrl,
                'client_portal_url' => cs_site_zelle_absolute_public_url('/client-portal', ['email' => $email]),
                'email_sent' => $emailSent,
                'match_score' => $bestScore,
            ];
        }
    }

    return [
        'success' => true,
        'status' => 'needs_ticket',
        'message' => 'CreditSoft found a possible payment, but it still needs support review before a license can be issued.',
        'can_open_ticket' => true,
        'match_score' => $bestScore,
    ];
}

function cs_site_zelle_payment_ticket_upload_dir(): string
{
    return cs_site_zelle_meta_path('payment-support-uploads');
}

function cs_site_zelle_store_payment_ticket_attachment(?array $file): array
{
    if (! is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['success' => true, 'path' => null, 'name' => null, 'mime' => null, 'size' => null];
    }

    if ((int) ($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'CreditSoft could not read the screenshot upload.'];
    }

    $tmpName = (string) ($file['tmp_name'] ?? '');
    if ($tmpName === '' || ! is_uploaded_file($tmpName)) {
        return ['success' => false, 'message' => 'CreditSoft could not verify the uploaded screenshot.'];
    }

    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0 || $size > 10 * 1024 * 1024) {
        return ['success' => false, 'message' => 'Please upload a screenshot or PDF under 10 MB.'];
    }

    $originalName = cs_site_zelle_clean_text((string) ($file['name'] ?? 'payment-proof'), 255);
    $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
    $allowedExtensions = ['png', 'jpg', 'jpeg', 'webp', 'gif', 'pdf', 'heic', 'heif'];

    if (! in_array($extension, $allowedExtensions, true)) {
        return ['success' => false, 'message' => 'Please upload a PNG, JPG, WebP, HEIC, GIF, or PDF screenshot.'];
    }

    $mime = '';
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mime = (string) finfo_file($finfo, $tmpName);
            finfo_close($finfo);
        }
    }

    $allowedMimes = ['image/png', 'image/jpeg', 'image/webp', 'image/gif', 'image/heic', 'image/heif', 'application/pdf', 'application/octet-stream'];
    if ($mime !== '' && ! in_array($mime, $allowedMimes, true)) {
        return ['success' => false, 'message' => 'That attachment type is not supported for payment proof.'];
    }

    $directory = cs_site_zelle_payment_ticket_upload_dir();
    if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
        return ['success' => false, 'message' => 'CreditSoft could not prepare the secure upload folder.'];
    }

    $storedName = gmdate('Ymd-His') . '-' . bin2hex(random_bytes(10)) . '.' . $extension;
    $target = $directory . '/' . $storedName;

    if (! move_uploaded_file($tmpName, $target)) {
        return ['success' => false, 'message' => 'CreditSoft could not save the screenshot upload.'];
    }

    @chmod($target, 0640);

    return [
        'success' => true,
        'path' => $storedName,
        'name' => $originalName,
        'mime' => $mime ?: null,
        'size' => $size,
    ];
}

function cs_site_zelle_payment_ticket_number(): string
{
    return 'CSP-' . gmdate('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
}

function cs_site_zelle_send_payment_ticket_confirmation(array $ticket): void
{
    $email = cs_site_zelle_normalize_email((string) ($ticket['customer_email'] ?? ''));

    if ($email === '') {
        return;
    }

    $ticketNumber = cs_site_zelle_clean_text((string) ($ticket['ticket_number'] ?? ''), 64);
    $name = cs_site_zelle_clean_text((string) ($ticket['customer_name'] ?? ''), 120);
    $greeting = $name !== '' ? "Hi {$name}," : 'Hi,';
    $plain = [
        $greeting,
        '',
        'CreditSoft received your payment help ticket.',
        '',
        'Ticket: '.$ticketNumber,
        'Payment source: '.cs_site_zelle_clean_text((string) ($ticket['payment_source'] ?? ''), 255),
        'Payer name: '.cs_site_zelle_clean_text((string) ($ticket['payer_name'] ?? ''), 255),
        'Amount: '.cs_site_zelle_money(isset($ticket['amount']) && is_numeric($ticket['amount']) ? (float) $ticket['amount'] : null),
        '',
        'Please remember to always include your CreditSoft account email in the Zelle memo or Cash App note in the future so your license can match automatically.',
        '',
        'CreditSoft',
    ];
    $html = cs_site_zelle_branded_email([
        'tone' => 'standard',
        'preheader' => 'CreditSoft received your payment help ticket.',
        'eyebrow' => 'Payment help ticket',
        'title' => 'We received your payment ticket.',
        'intro' => $greeting . ' CreditSoft has your payment help request. This gives support the details needed to match a Zelle or Cash App payment that did not automatically connect to a license.',
        'paragraphs' => [
            'If your payment screenshot was attached, it is saved privately for support review.',
            'Please remember to always include your CreditSoft account email in the Zelle memo or Cash App note in the future so your license can match automatically.',
        ],
        'summary' => [
            'Ticket' => $ticketNumber,
            'Payment source' => cs_site_zelle_clean_text((string) ($ticket['payment_source'] ?? ''), 255) ?: 'Not provided',
            'Payer name' => cs_site_zelle_clean_text((string) ($ticket['payer_name'] ?? ''), 255) ?: 'Not provided',
            'Amount' => cs_site_zelle_money(isset($ticket['amount']) && is_numeric($ticket['amount']) ? (float) $ticket['amount'] : null),
        ],
        'secondary_url' => cs_site_zelle_absolute_public_url('/client-portal', ['email' => $email]),
        'secondary_label' => 'open the billing history',
        'footer_note' => 'Support will compare the ticket details against Zelle/Cash App mail, checkout notices, and license records.',
    ]);

    cs_site_zelle_send_mail($email, 'CreditSoft payment help ticket received', implode("\n", $plain), $html);
}

function cs_site_zelle_send_payment_ticket_notice(array $ticket): void
{
    $ticketNumber = cs_site_zelle_clean_text((string) ($ticket['ticket_number'] ?? ''), 64);
    $email = cs_site_zelle_normalize_email((string) ($ticket['customer_email'] ?? ''));
    $lines = [
        'A CreditSoft payment help ticket was opened.',
        '',
        'Ticket: '.$ticketNumber,
        'Customer: '.cs_site_zelle_clean_text((string) ($ticket['customer_name'] ?? ''), 255),
        'Email: '.$email,
        'Phone: '.cs_site_zelle_clean_text((string) ($ticket['customer_phone'] ?? ''), 80),
        'Amount: '.cs_site_zelle_money(isset($ticket['amount']) && is_numeric($ticket['amount']) ? (float) $ticket['amount'] : null),
        'Payment source: '.cs_site_zelle_clean_text((string) ($ticket['payment_source'] ?? ''), 255),
        'Payer name: '.cs_site_zelle_clean_text((string) ($ticket['payer_name'] ?? ''), 255),
        'Memo used: '.cs_site_zelle_clean_text((string) ($ticket['memo_used'] ?? ''), 500),
        'Transaction ID: '.cs_site_zelle_clean_text((string) ($ticket['transaction_id'] ?? ''), 255),
        'Attachment: '.(! empty($ticket['attachment_path']) ? 'yes' : 'no'),
        '',
        'Open the Payments panel to review it.',
    ];

    cs_site_zelle_send_mail('hello@creditsoft.app', 'New CreditSoft payment help ticket: '.$ticketNumber, implode("\n", $lines));
}

function cs_site_zelle_create_payment_support_ticket(array $input, ?array $file = null): array
{
    $pdo = cs_site_admin_db();

    if (! $pdo instanceof PDO) {
        return ['success' => false, 'message' => 'The payment support database is not available.'];
    }

    if (trim((string) ($input['website'] ?? '')) !== '') {
        return ['success' => false, 'message' => 'That payment ticket could not be accepted.'];
    }

    $email = cs_site_zelle_normalize_email((string) ($input['customer_email'] ?? ''));
    $customerName = cs_site_zelle_clean_text((string) ($input['customer_name'] ?? ''), 255);
    $phone = cs_site_zelle_clean_text((string) ($input['customer_phone'] ?? ''), 80);
    $paymentSource = cs_site_zelle_clean_text((string) ($input['payment_source'] ?? ''), 255);
    $payerName = cs_site_zelle_clean_text((string) ($input['payer_name'] ?? ''), 255);
    $memoUsed = cs_site_zelle_clean_text((string) ($input['memo_used'] ?? ''), 500);
    $transactionId = cs_site_zelle_clean_text((string) ($input['transaction_id'] ?? ''), 255);
    $notes = trim((string) ($input['notes'] ?? ''));
    $amount = is_numeric($input['amount'] ?? null) ? round((float) $input['amount'], 2) : null;
    $paymentDate = trim((string) ($input['payment_date'] ?? ''));

    if ($email === '') {
        return ['success' => false, 'message' => 'Enter the email you expected the license to use.'];
    }

    if ($paymentSource === '' && $payerName === '') {
        return ['success' => false, 'message' => 'Tell us who the payment came from, or which Zelle email/phone sent it.'];
    }

    if ($paymentDate !== '' && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $paymentDate)) {
        $paymentDate = '';
    }

    $attachment = cs_site_zelle_store_payment_ticket_attachment($file);
    if (empty($attachment['success'])) {
        return ['success' => false, 'message' => (string) ($attachment['message'] ?? 'The attachment could not be saved.')];
    }

    cs_site_zelle_ensure_tables($pdo);
    $ticketNumber = cs_site_zelle_payment_ticket_number();
    $attachmentToken = ! empty($attachment['path']) ? bin2hex(random_bytes(24)) : null;
    $stmt = $pdo->prepare(
        "INSERT INTO payment_support_tickets (
            ticket_number, customer_name, customer_email, customer_phone, amount, payment_date, payment_source,
            payer_name, memo_used, transaction_id, notes, attachment_path, attachment_original_name, attachment_mime,
            attachment_size, attachment_download_token, status, ip_address, user_agent, created_at, updated_at
        ) VALUES (
            :ticket_number, :customer_name, :customer_email, :customer_phone, :amount, :payment_date, :payment_source,
            :payer_name, :memo_used, :transaction_id, :notes, :attachment_path, :attachment_original_name, :attachment_mime,
            :attachment_size, :attachment_download_token, 'new', :ip_address, :user_agent, NOW(), NOW()
        )"
    );
    $stmt->execute([
        'ticket_number' => $ticketNumber,
        'customer_name' => $customerName ?: null,
        'customer_email' => $email,
        'customer_phone' => $phone ?: null,
        'amount' => $amount,
        'payment_date' => $paymentDate !== '' ? $paymentDate : null,
        'payment_source' => $paymentSource ?: null,
        'payer_name' => $payerName ?: null,
        'memo_used' => $memoUsed ?: null,
        'transaction_id' => $transactionId ?: null,
        'notes' => $notes !== '' ? mb_substr($notes, 0, 4000) : null,
        'attachment_path' => $attachment['path'] ?? null,
        'attachment_original_name' => $attachment['name'] ?? null,
        'attachment_mime' => $attachment['mime'] ?? null,
        'attachment_size' => $attachment['size'] ?? null,
        'attachment_download_token' => $attachmentToken,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        'user_agent' => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
    ]);

    $ticket = [
        'ticket_number' => $ticketNumber,
        'customer_name' => $customerName,
        'customer_email' => $email,
        'customer_phone' => $phone,
        'amount' => $amount,
        'payment_source' => $paymentSource,
        'payer_name' => $payerName,
        'memo_used' => $memoUsed,
        'transaction_id' => $transactionId,
        'attachment_path' => $attachment['path'] ?? null,
    ];

    cs_site_zelle_send_payment_ticket_confirmation($ticket);
    cs_site_zelle_send_payment_ticket_notice($ticket);

    return ['success' => true, 'ticket_number' => $ticketNumber];
}

function cs_site_zelle_payment_support_tickets(PDO $pdo, int $limit = 20): array
{
    cs_site_zelle_ensure_tables($pdo);
    $stmt = $pdo->prepare(
        "SELECT *
         FROM payment_support_tickets
         ORDER BY created_at DESC
         LIMIT ?"
    );
    $stmt->bindValue(1, max(1, min(80, $limit)), PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function cs_site_zelle_sales_summary(PDO $pdo, int $days = 30): array
{
    $days = max(7, min(90, $days));
    $start = strtotime('-'.($days - 1).' days');
    $daily = [];
    $providerKeys = ['zelle', 'cash_app'];
    $feePercent = cs_site_zelle_cash_app_fee_percent();

    for ($index = 0; $index < $days; $index++) {
        $time = strtotime("+{$index} days", $start);
        $date = date('Y-m-d', $time ?: time());
        $daily[$date] = [
            'date' => $date,
            'label' => date('M j', $time ?: time()),
            'amount' => 0.0,
            'net' => 0.0,
            'fees' => 0.0,
            'count' => 0,
            'zelle' => 0.0,
            'cash_app' => 0.0,
            'other' => 0.0,
        ];
    }

    $summary = [
        'window_days' => $days,
        'total' => 0.0,
        'net_total' => 0.0,
        'fee_total' => 0.0,
        'cash_app_fee_percent' => $feePercent,
        'count' => 0,
        'average' => 0.0,
        'max_daily' => 0.0,
        'by_provider' => [
            'zelle' => 0.0,
            'cash_app' => 0.0,
        ],
        'by_provider_count' => [
            'zelle' => 0,
            'cash_app' => 0,
        ],
        'by_provider_fees' => [
            'zelle' => 0.0,
            'cash_app' => 0.0,
        ],
        'provider_rows' => [],
        'by_plan' => [],
        'daily' => array_values($daily),
    ];

    if (! cs_site_zelle_table_exists($pdo, 'zelle_payment_messages')) {
        return $summary;
    }

    $since = date('Y-m-d 00:00:00', $start ?: time());
    $stmt = $pdo->prepare(
        "SELECT
            DATE(COALESCE(received_at, processed_at, created_at)) AS sales_date,
            payment_provider,
            COUNT(*) AS payment_count,
            SUM(amount) AS sales_total
         FROM zelle_payment_messages
         WHERE status = 'processed'
           AND payment_status IN ('paid', 'overpaid')
           AND archived_at IS NULL
           AND amount IS NOT NULL
           AND amount > 0
           AND COALESCE(received_at, processed_at, created_at) >= ?
         GROUP BY DATE(COALESCE(received_at, processed_at, created_at)), payment_provider
         ORDER BY sales_date ASC"
    );
    $stmt->execute([$since]);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $date = (string) ($row['sales_date'] ?? '');

        if ($date === '' || ! isset($daily[$date])) {
            continue;
        }

        $provider = (string) ($row['payment_provider'] ?? 'zelle');
        $providerKey = cs_site_zelle_payment_provider_key($provider);
        $bucketKey = in_array($providerKey, $providerKeys, true) ? $providerKey : 'other';
        $amount = round((float) ($row['sales_total'] ?? 0), 2);
        $count = (int) ($row['payment_count'] ?? 0);
        $fees = cs_site_zelle_payment_fee_amount($providerKey, $amount);
        $netAmount = round($amount - $fees, 2);

        $daily[$date]['amount'] = round((float) $daily[$date]['amount'] + $amount, 2);
        $daily[$date]['net'] = round((float) $daily[$date]['net'] + $netAmount, 2);
        $daily[$date]['fees'] = round((float) $daily[$date]['fees'] + $fees, 2);
        $daily[$date]['count'] += $count;
        $daily[$date][$bucketKey] = round((float) ($daily[$date][$bucketKey] ?? 0) + $amount, 2);
        $summary['total'] = round((float) $summary['total'] + $amount, 2);
        $summary['net_total'] = round((float) $summary['net_total'] + $netAmount, 2);
        $summary['fee_total'] = round((float) $summary['fee_total'] + $fees, 2);
        $summary['count'] += $count;

        if (! array_key_exists($providerKey, $summary['by_provider'])) {
            $summary['by_provider'][$providerKey] = 0.0;
            $summary['by_provider_count'][$providerKey] = 0;
            $summary['by_provider_fees'][$providerKey] = 0.0;
        }

        $summary['by_provider'][$providerKey] = round((float) $summary['by_provider'][$providerKey] + $amount, 2);
        $summary['by_provider_count'][$providerKey] = (int) ($summary['by_provider_count'][$providerKey] ?? 0) + $count;
        $summary['by_provider_fees'][$providerKey] = round((float) ($summary['by_provider_fees'][$providerKey] ?? 0) + $fees, 2);
    }

    foreach ($daily as $day) {
        $summary['max_daily'] = max((float) $summary['max_daily'], (float) $day['amount']);
    }

    $summary['average'] = $summary['count'] > 0 ? round((float) $summary['total'] / $summary['count'], 2) : 0.0;
    $summary['daily'] = array_values($daily);
    $summary['provider_rows'] = array_values(array_map(
        static fn (string $providerKey): array => [
            'key' => $providerKey,
            'label' => cs_site_zelle_payment_provider_label($providerKey),
            'amount' => round((float) ($summary['by_provider'][$providerKey] ?? 0), 2),
            'count' => (int) ($summary['by_provider_count'][$providerKey] ?? 0),
            'fees' => round((float) ($summary['by_provider_fees'][$providerKey] ?? 0), 2),
            'net' => round((float) ($summary['by_provider'][$providerKey] ?? 0) - (float) ($summary['by_provider_fees'][$providerKey] ?? 0), 2),
        ],
        array_keys($summary['by_provider'])
    ));

    $planRows = [];
    $planStmt = $pdo->prepare(
        "SELECT
            COALESCE(NULLIF(plan_key, ''), 'unassigned') AS plan_key,
            COALESCE(NULLIF(billing, ''), 'unknown') AS billing,
            payment_provider,
            COUNT(*) AS payment_count,
            SUM(amount) AS sales_total
         FROM zelle_payment_messages
         WHERE status = 'processed'
           AND payment_status IN ('paid', 'overpaid')
           AND archived_at IS NULL
           AND amount IS NOT NULL
           AND amount > 0
           AND COALESCE(received_at, processed_at, created_at) >= ?
         GROUP BY COALESCE(NULLIF(plan_key, ''), 'unassigned'), COALESCE(NULLIF(billing, ''), 'unknown'), payment_provider"
    );
    $planStmt->execute([$since]);

    while ($row = $planStmt->fetch(PDO::FETCH_ASSOC)) {
        $planKey = (string) ($row['plan_key'] ?? 'unassigned');
        $billing = (string) ($row['billing'] ?? 'unknown');
        $rowKey = $planKey . '|' . $billing;
        $amount = round((float) ($row['sales_total'] ?? 0), 2);
        $count = (int) ($row['payment_count'] ?? 0);
        $providerKey = cs_site_zelle_payment_provider_key((string) ($row['payment_provider'] ?? 'zelle'));
        $fees = cs_site_zelle_payment_fee_amount($providerKey, $amount);

        if (! isset($planRows[$rowKey])) {
            $planRows[$rowKey] = [
                'plan_key' => $planKey,
                'billing' => $billing,
                'label' => cs_site_zelle_label_from_key($planKey) . ($billing !== 'unknown' ? ' · ' . cs_site_zelle_label_from_key($billing) : ''),
                'amount' => 0.0,
                'net' => 0.0,
                'fees' => 0.0,
                'count' => 0,
            ];
        }

        $planRows[$rowKey]['amount'] = round((float) $planRows[$rowKey]['amount'] + $amount, 2);
        $planRows[$rowKey]['net'] = round((float) $planRows[$rowKey]['net'] + ($amount - $fees), 2);
        $planRows[$rowKey]['fees'] = round((float) $planRows[$rowKey]['fees'] + $fees, 2);
        $planRows[$rowKey]['count'] += $count;
    }

    usort($planRows, static fn (array $left, array $right): int => ((float) $right['amount']) <=> ((float) $left['amount']));
    $summary['by_plan'] = array_slice(array_values($planRows), 0, 6);

    return $summary;
}

function cs_site_zelle_payment_data(): array
{
    $pdo = cs_site_admin_db();
    $config = cs_site_zelle_mailbox_config();
    $data = [
        'available' => $pdo instanceof PDO,
        'configured' => ! empty($config['password']),
        'imap_enabled' => function_exists('imap_open'),
        'mailbox' => (string) ($config['username'] ?? 'z@creditsoft.app'),
        'from_email' => (string) ($config['from_email'] ?? 'hello@creditsoft.app'),
        'stats' => [
            'total' => 0,
            'processed' => 0,
            'balance_due' => 0,
            'overpaid' => 0,
            'needs_review' => 0,
            'email_sent' => 0,
            'open_tickets' => 0,
        ],
        'sales' => [
            'window_days' => 90,
            'total' => 0.0,
            'count' => 0,
            'average' => 0.0,
            'max_daily' => 0.0,
            'by_provider' => [
                'zelle' => 0.0,
                'cash_app' => 0.0,
            ],
            'daily' => [],
        ],
        'messages' => [],
        'tickets' => [],
        'notices' => array_slice(cs_site_zelle_payment_notices(), 0, 12),
        'error' => null,
    ];

    if (! $pdo) {
        return $data;
    }

    try {
        cs_site_zelle_ensure_tables($pdo);
        cs_site_zelle_quarantine_untrusted_messages($pdo);
        $stats = $pdo->query(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status = 'processed' THEN 1 ELSE 0 END) AS processed,
                SUM(CASE WHEN status = 'balance_due' THEN 1 ELSE 0 END) AS balance_due,
                SUM(CASE WHEN payment_status = 'overpaid' THEN 1 ELSE 0 END) AS overpaid,
                SUM(CASE WHEN status = 'needs_review' THEN 1 ELSE 0 END) AS needs_review,
                SUM(CASE WHEN email_sent_at IS NOT NULL THEN 1 ELSE 0 END) AS email_sent
             FROM zelle_payment_messages
	             WHERE status NOT IN ('ignored', 'archived')
	               AND archived_at IS NULL"
        )->fetch(PDO::FETCH_ASSOC) ?: [];

        foreach ($data['stats'] as $key => $value) {
            if ($key !== 'open_tickets') {
                $data['stats'][$key] = (int) ($stats[$key] ?? 0);
            }
        }

        $stmt = $pdo->query(
            "SELECT *
             FROM zelle_payment_messages
	             WHERE status NOT IN ('ignored', 'archived')
	               AND archived_at IS NULL
	             ORDER BY COALESCE(received_at, created_at) DESC
             LIMIT 80"
        );

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $data['messages'][] = $row;
        }

        $data['sales'] = cs_site_zelle_sales_summary($pdo, 90);

        if (cs_site_zelle_table_exists($pdo, 'payment_support_tickets')) {
            $data['stats']['open_tickets'] = (int) ($pdo->query("SELECT COUNT(*) FROM payment_support_tickets WHERE status IN ('new', 'open', 'needs_review')")->fetchColumn() ?: 0);
            $data['tickets'] = cs_site_zelle_payment_support_tickets($pdo, 20);
        }
    } catch (Throwable $exception) {
        $data['error'] = $exception->getMessage();
    }

    return $data;
}
