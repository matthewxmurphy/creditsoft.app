<?php
declare(strict_types=1);

require_once __DIR__ . '/meta-conversions-api.php';

function creditsoft_lead_storage_dir(): string
{
    return dirname(__DIR__) . '/web/data';
}

function creditsoft_lead_fallback_leads_path(): string
{
    return creditsoft_lead_storage_dir() . '/leads.json';
}

function creditsoft_lead_fallback_qualification_path(): string
{
    return creditsoft_lead_storage_dir() . '/lead_qualification_responses.json';
}

/**
 * @return array<int, array<string, mixed>>
 */
function creditsoft_lead_fallback_read(string $path): array
{
    if (!is_file($path)) {
        return [];
    }

    $decoded = json_decode((string) file_get_contents($path), true);

    return is_array($decoded) ? array_values(array_filter($decoded, 'is_array')) : [];
}

/**
 * @param array<int, array<string, mixed>> $rows
 */
function creditsoft_lead_fallback_write(string $path, array $rows): bool
{
    $directory = dirname($path);

    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        return false;
    }

    $encoded = json_encode(array_values($rows), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    return is_string($encoded) && file_put_contents($path, $encoded . PHP_EOL, LOCK_EX) !== false;
}

function creditsoft_lead_db(): ?PDO
{
    static $pdo = false;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    if (
        ! defined('DB_HOST')
        || ! defined('DB_NAME')
        || ! defined('DB_USER')
        || ! defined('DB_PASS')
    ) {
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

function creditsoft_lead_email_is_valid(string $email): bool
{
    $email = trim($email);

    if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $domain = substr((string) strrchr($email, '@'), 1);

    if ($domain === '') {
        return false;
    }

    return function_exists('checkdnsrr') ? checkdnsrr($domain, 'MX') : true;
}

function creditsoft_lead_turnstile_secret(): string
{
    foreach ([
        'CREDITSOFT_TURNSTILE_SECRET_KEY',
        'TURNSTILE_SECRET_KEY',
        'CLOUDFLARE_TURNSTILE_SECRET_KEY',
    ] as $key) {
        if (defined($key) && trim((string) constant($key)) !== '') {
            return trim((string) constant($key));
        }

        $value = getenv($key);
        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }
    }

    return '';
}

function creditsoft_lead_turnstile_is_valid(?string $token): bool
{
    $secret = creditsoft_lead_turnstile_secret();

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

function creditsoft_lead_clean_text(?string $value, int $maxLength = 255): string
{
    $value = trim((string) $value);

    if ($value === '') {
        return '';
    }

    return mb_substr($value, 0, $maxLength);
}

function creditsoft_lead_clean_list(array $values): array
{
    $clean = [];

    foreach ($values as $value) {
        $item = creditsoft_lead_clean_text((string) $value, 120);

        if ($item !== '') {
            $clean[] = $item;
        }
    }

    return array_values(array_unique($clean));
}

function creditsoft_lead_email_domain(string $email): string
{
    $domain = substr((string) strrchr(strtolower(trim($email)), '@'), 1);

    return is_string($domain) ? trim($domain) : '';
}

function creditsoft_lead_blocked_domains(): array
{
    $domains = ['immenseignite.info'];
    $configured = getenv('CREDITSOFT_BLOCKED_LEAD_DOMAINS');

    if (is_string($configured) && trim($configured) !== '') {
        $domains = array_merge($domains, preg_split('/[\s,]+/', strtolower($configured)) ?: []);
    }

    return array_values(array_unique(array_filter(array_map('trim', $domains))));
}

function creditsoft_lead_email_domain_is_blocked(string $email): bool
{
    $domain = creditsoft_lead_email_domain($email);

    return $domain !== '' && in_array($domain, creditsoft_lead_blocked_domains(), true);
}

function creditsoft_lead_source_is_trusted_server(string $source): bool
{
    $source = strtolower(trim($source));

    return $source === 'admin'
        || $source === 'system'
        || $source === 'zelle_payment'
        || $source === 'cash_app_payment'
        || str_starts_with($source, 'meta_');
}

function creditsoft_lead_text_looks_generated(string $value): bool
{
    $value = trim($value);

    if (! preg_match('/^[a-z]{8,}$/', $value)) {
        return false;
    }

    $rareLetters = preg_match_all('/[jqxz]/', $value);
    $hasLongConsonantRun = preg_match('/[bcdfghjklmnpqrstvwxyz]{5,}/', $value) === 1;

    return $rareLetters >= 2 || $hasLongConsonantRun;
}

function creditsoft_lead_public_rejection(array $payload, string $email, string $name, string $company, string $source): ?array
{
    if (creditsoft_lead_source_is_trusted_server($source)) {
        return null;
    }

    if (! creditsoft_lead_email_is_valid($email) || creditsoft_lead_email_domain_is_blocked($email)) {
        return [
            'success' => false,
            'status' => 422,
            'error' => 'Use a real email address that can receive mail.',
        ];
    }

    $turnstileToken = creditsoft_lead_clean_text($payload['turnstile_token'] ?? $payload['cf-turnstile-response'] ?? '', 2048);
    $honeypot = creditsoft_lead_clean_text($payload['website'] ?? '', 255);

    if ($honeypot !== '' || creditsoft_lead_turnstile_secret() === '' || ! creditsoft_lead_turnstile_is_valid($turnstileToken)) {
        return [
            'success' => false,
            'status' => 422,
            'error' => 'The browser check did not pass. Please refresh and try again.',
        ];
    }

    if ($name === '' || $company === '' || creditsoft_lead_text_looks_generated($name) || creditsoft_lead_text_looks_generated($company)) {
        return [
            'success' => false,
            'status' => 422,
            'error' => 'Use a real contact name and office or company name.',
        ];
    }

    return null;
}

function creditsoft_lead_ensure_qualification_table(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `lead_qualification_responses` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `lead_id` INT NOT NULL,
            `plan_interest` VARCHAR(80) DEFAULT NULL,
            `client_count` VARCHAR(80) DEFAULT NULL,
            `monitoring_systems` TEXT DEFAULT NULL,
            `current_workflow` VARCHAR(255) DEFAULT NULL,
            `merchant_status` VARCHAR(80) DEFAULT NULL,
            `merchant_provider` VARCHAR(255) DEFAULT NULL,
            `payment_methods` VARCHAR(255) DEFAULT NULL,
            `website_status` VARCHAR(80) DEFAULT NULL,
            `website_sentiment` VARCHAR(80) DEFAULT NULL,
            `outsourcing_status` VARCHAR(80) DEFAULT NULL,
            `outsourcing_notes` TEXT DEFAULT NULL,
            `roi_visibility` VARCHAR(80) DEFAULT NULL,
            `team_size` VARCHAR(80) DEFAULT NULL,
            `switch_timeline` VARCHAR(80) DEFAULT NULL,
            `biggest_pain` TEXT DEFAULT NULL,
            `primary_goal` TEXT DEFAULT NULL,
            `additional_notes` TEXT DEFAULT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `uniq_lead_id` (`lead_id`),
            INDEX `idx_plan_interest` (`plan_interest`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

function creditsoft_lead_upsert_basic(array $payload): array
{
    $email = strtolower(creditsoft_lead_clean_text($payload['email'] ?? '', 255));
    $name = creditsoft_lead_clean_text($payload['name'] ?? '', 255);
    $phone = creditsoft_lead_clean_text($payload['phone'] ?? '', 50);
    $company = creditsoft_lead_clean_text($payload['company'] ?? '', 255);
    $source = creditsoft_lead_clean_text($payload['source'] ?? 'early_access', 50) ?: 'early_access';
    $plan = creditsoft_lead_clean_text($payload['plan_interest'] ?? '', 80);

    $draft = [
        'id' => 0,
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
        'company' => $company,
        'plan_interest' => $plan,
        'is_new' => false,
    ];

    $publicRejection = creditsoft_lead_public_rejection($payload, $email, $name, $company, $source);
    if ($publicRejection !== null) {
        return array_replace($draft, $publicRejection);
    }

    $pdo = creditsoft_lead_db();

    if (! $pdo) {
        $path = creditsoft_lead_fallback_leads_path();
        $rows = creditsoft_lead_fallback_read($path);
        $existingIndex = null;
        $nextId = 1;

        foreach ($rows as $index => $row) {
            $rowId = (int) ($row['id'] ?? 0);
            $nextId = max($nextId, $rowId + 1);

            if (strtolower((string) ($row['email'] ?? '')) === $email) {
                $existingIndex = $index;
            }
        }

        $record = $existingIndex !== null ? $rows[$existingIndex] : [];
        $record['id'] = (int) ($record['id'] ?? $nextId);
        $record['name'] = $name !== '' ? $name : (string) ($record['name'] ?? '');
        $record['email'] = $email;
        $record['phone'] = $phone !== '' ? $phone : (string) ($record['phone'] ?? '');
        $record['company'] = $company !== '' ? $company : (string) ($record['company'] ?? '');
        $record['source'] = $source;
        $record['status'] = (string) ($record['status'] ?? 'new');
        $record['notes'] = $plan !== '' ? 'Plan interest: ' . $plan : (string) ($record['notes'] ?? '');
        $record['created_at'] = (string) ($record['created_at'] ?? gmdate('c'));
        $record['updated_at'] = gmdate('c');

        if ($existingIndex !== null) {
            $rows[$existingIndex] = $record;
        } else {
            $rows[] = $record;
        }

        creditsoft_lead_fallback_write($path, $rows);

        return array_replace($draft, [
            'id' => (int) $record['id'],
            'name' => (string) $record['name'],
            'email' => (string) $record['email'],
            'phone' => (string) $record['phone'],
            'company' => (string) $record['company'],
            'is_new' => $existingIndex === null,
        ]);
    }

    $note = $plan !== '' ? 'Plan interest: '.$plan : null;

    $stmt = $pdo->prepare(
        "INSERT INTO `leads` (`name`, `email`, `phone`, `company`, `source`, `status`, `notes`, `created_at`, `updated_at`)
         VALUES (:name, :email, :phone, :company, :source, 'new', :notes, NOW(), NOW())
         ON DUPLICATE KEY UPDATE
            `name` = VALUES(`name`),
            `phone` = IF(VALUES(`phone`) <> '', VALUES(`phone`), `phone`),
            `company` = IF(VALUES(`company`) <> '', VALUES(`company`), `company`),
            `source` = VALUES(`source`),
            `notes` = IF(VALUES(`notes`) IS NOT NULL AND VALUES(`notes`) <> '', VALUES(`notes`), `notes`),
            `updated_at` = NOW()"
    );

    $stmt->execute([
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
        'company' => $company,
        'source' => $source,
        'notes' => $note,
    ]);

    $select = $pdo->prepare("SELECT `id`, `name`, `email`, `phone`, `company`, `source`, `status`, `notes` FROM `leads` WHERE `email` = ? LIMIT 1");
    $select->execute([$email]);
    $lead = $select->fetch(PDO::FETCH_ASSOC) ?: [];

    return array_replace($draft, [
        'id' => (int) ($lead['id'] ?? 0),
        'name' => (string) ($lead['name'] ?? $name),
        'email' => (string) ($lead['email'] ?? $email),
        'phone' => (string) ($lead['phone'] ?? $phone),
        'company' => (string) ($lead['company'] ?? $company),
        'is_new' => ((int) ($lead['id'] ?? 0)) === (int) $pdo->lastInsertId() && (int) $pdo->lastInsertId() > 0,
    ]);
}

function creditsoft_lead_fetch(int $leadId): ?array
{
    $pdo = creditsoft_lead_db();

    if ($leadId <= 0) {
        return null;
    }

    if (! $pdo) {
        foreach (creditsoft_lead_fallback_read(creditsoft_lead_fallback_leads_path()) as $row) {
            if ((int) ($row['id'] ?? 0) === $leadId) {
                return $row;
            }
        }

        return null;
    }

    $stmt = $pdo->prepare("SELECT `id`, `name`, `email`, `phone`, `company`, `source`, `status`, `notes` FROM `leads` WHERE `id` = ? LIMIT 1");
    $stmt->execute([$leadId]);
    $lead = $stmt->fetch(PDO::FETCH_ASSOC);

    return is_array($lead) ? $lead : null;
}

function creditsoft_lead_build_summary(array $payload): string
{
    $monitoringSystems = creditsoft_lead_clean_list($payload['monitoring_systems'] ?? []);

    $lines = array_filter([
        'Client count: '.creditsoft_lead_clean_text($payload['client_count'] ?? '', 80),
        'Monitoring: '.implode(', ', $monitoringSystems),
        'Current workflow: '.creditsoft_lead_clean_text($payload['current_workflow'] ?? '', 255),
        'Merchant status: '.creditsoft_lead_clean_text($payload['merchant_status'] ?? '', 80),
        'Merchant provider: '.creditsoft_lead_clean_text($payload['merchant_provider'] ?? '', 255),
        'Payment methods: '.creditsoft_lead_clean_text($payload['payment_methods'] ?? '', 255),
        'Website status: '.creditsoft_lead_clean_text($payload['website_status'] ?? '', 80),
        'Website sentiment: '.creditsoft_lead_clean_text($payload['website_sentiment'] ?? '', 80),
        'Outsourcing: '.creditsoft_lead_clean_text($payload['outsourcing_status'] ?? '', 80),
        'Outsourcing notes: '.creditsoft_lead_clean_text($payload['outsourcing_notes'] ?? '', 1000),
        'ROI visibility: '.creditsoft_lead_clean_text($payload['roi_visibility'] ?? '', 80),
        'Team size: '.creditsoft_lead_clean_text($payload['team_size'] ?? '', 80),
        'Switch timeline: '.creditsoft_lead_clean_text($payload['switch_timeline'] ?? '', 80),
        'Biggest pain: '.creditsoft_lead_clean_text($payload['biggest_pain'] ?? '', 2000),
        'Primary goal: '.creditsoft_lead_clean_text($payload['primary_goal'] ?? '', 2000),
        'Additional notes: '.creditsoft_lead_clean_text($payload['additional_notes'] ?? '', 2000),
    ]);

    return implode("\n", $lines);
}

function creditsoft_lead_save_qualification(int $leadId, array $payload): bool
{
    $pdo = creditsoft_lead_db();

    if ($leadId <= 0) {
        return false;
    }

    $monitoringSystems = creditsoft_lead_clean_list($payload['monitoring_systems'] ?? []);
    $summary = creditsoft_lead_build_summary($payload);

    if (! $pdo) {
        $qualificationPath = creditsoft_lead_fallback_qualification_path();
        $qualifications = creditsoft_lead_fallback_read($qualificationPath);
        $existingIndex = null;

        foreach ($qualifications as $index => $row) {
            if ((int) ($row['lead_id'] ?? 0) === $leadId) {
                $existingIndex = $index;
                break;
            }
        }

        $record = [
            'lead_id' => $leadId,
            'plan_interest' => creditsoft_lead_clean_text($payload['plan_interest'] ?? '', 80),
            'client_count' => creditsoft_lead_clean_text($payload['client_count'] ?? '', 80),
            'monitoring_systems' => implode(', ', $monitoringSystems),
            'current_workflow' => creditsoft_lead_clean_text($payload['current_workflow'] ?? '', 255),
            'merchant_status' => creditsoft_lead_clean_text($payload['merchant_status'] ?? '', 80),
            'merchant_provider' => creditsoft_lead_clean_text($payload['merchant_provider'] ?? '', 255),
            'payment_methods' => creditsoft_lead_clean_text($payload['payment_methods'] ?? '', 255),
            'website_status' => creditsoft_lead_clean_text($payload['website_status'] ?? '', 80),
            'website_sentiment' => creditsoft_lead_clean_text($payload['website_sentiment'] ?? '', 80),
            'outsourcing_status' => creditsoft_lead_clean_text($payload['outsourcing_status'] ?? '', 80),
            'outsourcing_notes' => creditsoft_lead_clean_text($payload['outsourcing_notes'] ?? '', 4000),
            'roi_visibility' => creditsoft_lead_clean_text($payload['roi_visibility'] ?? '', 80),
            'team_size' => creditsoft_lead_clean_text($payload['team_size'] ?? '', 80),
            'switch_timeline' => creditsoft_lead_clean_text($payload['switch_timeline'] ?? '', 80),
            'biggest_pain' => creditsoft_lead_clean_text($payload['biggest_pain'] ?? '', 4000),
            'primary_goal' => creditsoft_lead_clean_text($payload['primary_goal'] ?? '', 4000),
            'additional_notes' => creditsoft_lead_clean_text($payload['additional_notes'] ?? '', 4000),
            'updated_at' => gmdate('c'),
            'created_at' => gmdate('c'),
        ];

        if ($existingIndex !== null) {
            $record['created_at'] = (string) ($qualifications[$existingIndex]['created_at'] ?? gmdate('c'));
            $qualifications[$existingIndex] = $record;
        } else {
            $qualifications[] = $record;
        }

        if (!creditsoft_lead_fallback_write($qualificationPath, $qualifications)) {
            return false;
        }

        $leadPath = creditsoft_lead_fallback_leads_path();
        $leads = creditsoft_lead_fallback_read($leadPath);

        foreach ($leads as $index => $lead) {
            if ((int) ($lead['id'] ?? 0) !== $leadId) {
                continue;
            }

            $lead['company'] = creditsoft_lead_clean_text($payload['company'] ?? '', 255) ?: (string) ($lead['company'] ?? '');
            $lead['phone'] = creditsoft_lead_clean_text($payload['phone'] ?? '', 50) ?: (string) ($lead['phone'] ?? '');
            $lead['source'] = creditsoft_lead_clean_text($payload['source'] ?? 'early_access_qualified', 50) ?: 'early_access_qualified';
            $lead['status'] = 'qualified';
            $lead['notes'] = $summary;
            $lead['updated_at'] = gmdate('c');
            $leads[$index] = $lead;
            break;
        }

        return creditsoft_lead_fallback_write($leadPath, $leads);
    }

    creditsoft_lead_ensure_qualification_table($pdo);

    $stmt = $pdo->prepare(
        "INSERT INTO `lead_qualification_responses` (
            `lead_id`,
            `plan_interest`,
            `client_count`,
            `monitoring_systems`,
            `current_workflow`,
            `merchant_status`,
            `merchant_provider`,
            `payment_methods`,
            `website_status`,
            `website_sentiment`,
            `outsourcing_status`,
            `outsourcing_notes`,
            `roi_visibility`,
            `team_size`,
            `switch_timeline`,
            `biggest_pain`,
            `primary_goal`,
            `additional_notes`
        ) VALUES (
            :lead_id,
            :plan_interest,
            :client_count,
            :monitoring_systems,
            :current_workflow,
            :merchant_status,
            :merchant_provider,
            :payment_methods,
            :website_status,
            :website_sentiment,
            :outsourcing_status,
            :outsourcing_notes,
            :roi_visibility,
            :team_size,
            :switch_timeline,
            :biggest_pain,
            :primary_goal,
            :additional_notes
        )
        ON DUPLICATE KEY UPDATE
            `plan_interest` = VALUES(`plan_interest`),
            `client_count` = VALUES(`client_count`),
            `monitoring_systems` = VALUES(`monitoring_systems`),
            `current_workflow` = VALUES(`current_workflow`),
            `merchant_status` = VALUES(`merchant_status`),
            `merchant_provider` = VALUES(`merchant_provider`),
            `payment_methods` = VALUES(`payment_methods`),
            `website_status` = VALUES(`website_status`),
            `website_sentiment` = VALUES(`website_sentiment`),
            `outsourcing_status` = VALUES(`outsourcing_status`),
            `outsourcing_notes` = VALUES(`outsourcing_notes`),
            `roi_visibility` = VALUES(`roi_visibility`),
            `team_size` = VALUES(`team_size`),
            `switch_timeline` = VALUES(`switch_timeline`),
            `biggest_pain` = VALUES(`biggest_pain`),
            `primary_goal` = VALUES(`primary_goal`),
            `additional_notes` = VALUES(`additional_notes`),
            `updated_at` = NOW()"
    );

    $saved = $stmt->execute([
        'lead_id' => $leadId,
        'plan_interest' => creditsoft_lead_clean_text($payload['plan_interest'] ?? '', 80),
        'client_count' => creditsoft_lead_clean_text($payload['client_count'] ?? '', 80),
        'monitoring_systems' => implode(', ', $monitoringSystems),
        'current_workflow' => creditsoft_lead_clean_text($payload['current_workflow'] ?? '', 255),
        'merchant_status' => creditsoft_lead_clean_text($payload['merchant_status'] ?? '', 80),
        'merchant_provider' => creditsoft_lead_clean_text($payload['merchant_provider'] ?? '', 255),
        'payment_methods' => creditsoft_lead_clean_text($payload['payment_methods'] ?? '', 255),
        'website_status' => creditsoft_lead_clean_text($payload['website_status'] ?? '', 80),
        'website_sentiment' => creditsoft_lead_clean_text($payload['website_sentiment'] ?? '', 80),
        'outsourcing_status' => creditsoft_lead_clean_text($payload['outsourcing_status'] ?? '', 80),
        'outsourcing_notes' => creditsoft_lead_clean_text($payload['outsourcing_notes'] ?? '', 4000),
        'roi_visibility' => creditsoft_lead_clean_text($payload['roi_visibility'] ?? '', 80),
        'team_size' => creditsoft_lead_clean_text($payload['team_size'] ?? '', 80),
        'switch_timeline' => creditsoft_lead_clean_text($payload['switch_timeline'] ?? '', 80),
        'biggest_pain' => creditsoft_lead_clean_text($payload['biggest_pain'] ?? '', 4000),
        'primary_goal' => creditsoft_lead_clean_text($payload['primary_goal'] ?? '', 4000),
        'additional_notes' => creditsoft_lead_clean_text($payload['additional_notes'] ?? '', 4000),
    ]);

    if (! $saved) {
        return false;
    }

    $updateLead = $pdo->prepare(
        "UPDATE `leads`
         SET `company` = IF(:company <> '', :company, `company`),
             `phone` = IF(:phone <> '', :phone, `phone`),
             `source` = :source,
             `status` = 'qualified',
             `notes` = :notes,
             `updated_at` = NOW()
         WHERE `id` = :lead_id"
    );

    return $updateLead->execute([
        'company' => creditsoft_lead_clean_text($payload['company'] ?? '', 255),
        'phone' => creditsoft_lead_clean_text($payload['phone'] ?? '', 50),
        'source' => creditsoft_lead_clean_text($payload['source'] ?? 'early_access_qualified', 50) ?: 'early_access_qualified',
        'notes' => $summary,
        'lead_id' => $leadId,
    ]);
}

/**
 * @return array{
 *   stats: array<string,int>,
 *   leads: array<int,array<string,mixed>>,
 *   sources: array<string,int>,
 *   workflow_counts: array<string,int>,
 *   monitoring_counts: array<string,int>,
 *   merchant_counts: array<string,int>
 * }
 */
function creditsoft_lead_dashboard_fallback(): array
{
    $leads = creditsoft_lead_fallback_read(creditsoft_lead_fallback_leads_path());
    $qualifications = creditsoft_lead_fallback_read(creditsoft_lead_fallback_qualification_path());
    $qualificationByLeadId = [];

    foreach ($qualifications as $qualification) {
        $qualificationByLeadId[(int) ($qualification['lead_id'] ?? 0)] = $qualification;
    }

    $data = [
        'stats' => [
            'total_leads' => count($leads),
            'new_leads' => 0,
            'qualified_leads' => 0,
            'assessment_results' => 0,
            'converted_leads' => 0,
        ],
        'leads' => [],
        'sources' => [],
        'workflow_counts' => [],
        'monitoring_counts' => [],
        'merchant_counts' => [],
    ];

    foreach ($leads as $lead) {
        $leadId = (int) ($lead['id'] ?? 0);
        $qualification = $qualificationByLeadId[$leadId] ?? [];
        $row = array_merge($lead, $qualification);
        $data['leads'][] = $row;

        $status = (string) ($lead['status'] ?? 'new');
        if ($status === 'new') {
            $data['stats']['new_leads']++;
        } elseif ($status === 'qualified') {
            $data['stats']['qualified_leads']++;
        } elseif ($status === 'converted') {
            $data['stats']['converted_leads']++;
        }

        $source = trim((string) ($lead['source'] ?? ''));
        if ($source !== '') {
            $data['sources'][$source] = ($data['sources'][$source] ?? 0) + 1;
        }

        $workflow = trim((string) ($qualification['current_workflow'] ?? ''));
        if ($workflow !== '') {
            $data['workflow_counts'][$workflow] = ($data['workflow_counts'][$workflow] ?? 0) + 1;
        }

        $merchant = trim((string) ($qualification['merchant_status'] ?? ''));
        if ($merchant !== '') {
            $data['merchant_counts'][$merchant] = ($data['merchant_counts'][$merchant] ?? 0) + 1;
        }

        foreach (array_filter(array_map('trim', explode(',', (string) ($qualification['monitoring_systems'] ?? '')))) as $provider) {
            $data['monitoring_counts'][$provider] = ($data['monitoring_counts'][$provider] ?? 0) + 1;
        }
    }

    arsort($data['sources']);
    arsort($data['workflow_counts']);
    arsort($data['monitoring_counts']);
    arsort($data['merchant_counts']);

    return $data;
}
