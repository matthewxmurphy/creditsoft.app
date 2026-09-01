<?php
declare(strict_types=1);

if (! defined('CREDITSOFT_SITE_ADMIN_BOOTSTRAPPED')) {
    define('CREDITSOFT_SITE_ADMIN_BOOTSTRAPPED', true);

    foreach ([
        dirname(__DIR__, 2) . '/credit_config.php',
        dirname(__DIR__, 2) . '/web-meta/credit_config.php',
        dirname(__DIR__, 3) . '/credit_config.php',
        ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/../credit_config.php',
        ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/credit_config.php',
    ] as $configPath) {
        if (is_string($configPath) && $configPath !== '' && file_exists($configPath)) {
            require_once $configPath;
            break;
        }
    }

    $overlayConfigPath = dirname(__DIR__, 2) . '/web-meta/credit_config.php';
    if (file_exists($overlayConfigPath)) {
        require_once $overlayConfigPath;
    }

    require_once dirname(__DIR__) . '/lead-intake.php';
    require_once dirname(__DIR__) . '/pricing-config.php';

    $autoloadPath = dirname(__DIR__, 2) . '/vendor/autoload.php';
    if (file_exists($autoloadPath)) {
        require_once $autoloadPath;
    }

    if (session_status() !== PHP_SESSION_ACTIVE) {
        $isSecure = (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);
        $rememberDays = (int) (getenv('CREDITSOFT_SITE_ADMIN_REMEMBER_DAYS') ?: 90);
        $rememberLifetime = max(1, $rememberDays) * 86400;

        session_name('creditsoft_site_admin');
        ini_set('session.gc_maxlifetime', (string) $rememberLifetime);
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $isSecure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

function cs_site_admin_base_url(): string
{
    if (defined('SITE_URL') && trim((string) SITE_URL) !== '') {
        return rtrim((string) SITE_URL, '/');
    }

    $scheme = (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    return $scheme . '://' . $host;
}

function cs_site_current_scheme(): string
{
    $forwarded = strtolower(trim((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));

    if ($forwarded === 'https' || $forwarded === 'http') {
        return $forwarded;
    }

    return (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
}

function cs_site_current_host(): string
{
    return strtolower(trim((string) ($_SERVER['HTTP_HOST'] ?? 'localhost')));
}

function cs_site_host_without_port(string $host): string
{
    $host = trim($host);

    if ($host === '') {
        return '';
    }

    $parsed = parse_url('http://' . $host, PHP_URL_HOST);

    return strtolower(trim((string) ($parsed ?: $host)));
}

function cs_site_is_local_host(string $host): bool
{
    $host = cs_site_host_without_port($host);

    if ($host === '' || $host === 'localhost' || $host === '127.0.0.1' || $host === '::1') {
        return true;
    }

    return str_ends_with($host, '.local') || str_ends_with($host, '.test');
}

function cs_site_is_tailscale_host(string $host): bool
{
    $host = cs_site_host_without_port($host);

    return $host !== '' && str_ends_with($host, '.ts.net');
}

function cs_site_ip_is_tailscale(string $ip): bool
{
    $ip = trim($ip);

    if ($ip === '') {
        return false;
    }

    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $long = ip2long($ip);
        $start = ip2long('100.64.0.0');
        $end = ip2long('100.127.255.255');

        return $long !== false && $start !== false && $end !== false && $long >= $start && $long <= $end;
    }

    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        return str_starts_with(strtolower($ip), 'fd7a:115c:a1e0:');
    }

    return false;
}

function cs_site_request_ip_candidates(): array
{
    $values = [];

    foreach ([
        $_SERVER['HTTP_CF_CONNECTING_IP'] ?? null,
        $_SERVER['HTTP_X_REAL_IP'] ?? null,
        $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null,
        $_SERVER['REMOTE_ADDR'] ?? null,
    ] as $candidate) {
        if (! is_string($candidate) || trim($candidate) === '') {
            continue;
        }

        foreach (explode(',', $candidate) as $part) {
            $ip = trim($part);

            if ($ip !== '') {
                $values[] = $ip;
            }
        }
    }

    return array_values(array_unique($values));
}

function cs_site_admin_request_is_trusted(): bool
{
    $host = cs_site_current_host();

    if (cs_site_is_local_host($host) || cs_site_is_tailscale_host($host)) {
        return true;
    }

    foreach (cs_site_request_ip_candidates() as $ip) {
        if (cs_site_ip_is_tailscale($ip)) {
            return true;
        }
    }

    return false;
}

function cs_site_public_host(): string
{
    if (defined('SITE_URL') && trim((string) SITE_URL) !== '') {
        $siteHost = parse_url((string) SITE_URL, PHP_URL_HOST);

        if (is_string($siteHost) && trim($siteHost) !== '') {
            return strtolower(trim($siteHost));
        }
    }

    $host = cs_site_host_without_port(cs_site_current_host());

    if (str_starts_with($host, 'admin.')) {
        return substr($host, 6);
    }

    return $host;
}

function cs_site_admin_host(): string
{
    $configured = trim((string) (
        getenv('CREDITSOFT_SITE_ADMIN_HOST')
        ?: getenv('SITE_ADMIN_HOST')
        ?: (defined('SITE_ADMIN_HOST') ? (string) SITE_ADMIN_HOST : '')
    ));

    if ($configured !== '') {
        return strtolower(cs_site_host_without_port($configured));
    }

    $publicHost = cs_site_public_host();

    if (str_starts_with($publicHost, 'www.')) {
        $publicHost = substr($publicHost, 4);
    }

    if ($publicHost === '' || cs_site_is_local_host($publicHost)) {
        return '';
    }

    return 'admin.' . $publicHost;
}

function cs_site_has_admin_subdomain(): bool
{
    $adminHost = cs_site_admin_host();

    return $adminHost !== '' && ! cs_site_is_local_host($adminHost);
}

function cs_site_is_admin_host_request(): bool
{
    $adminHost = cs_site_admin_host();

    return $adminHost !== '' && cs_site_host_without_port(cs_site_current_host()) === $adminHost;
}

function cs_site_build_url(string $base, array $query = []): string
{
    if ($query === []) {
        return $base;
    }

    $queryString = http_build_query($query);

    if ($queryString === '') {
        return $base;
    }

    return $base . (str_contains($base, '?') ? '&' : '?') . $queryString;
}

function cs_site_public_url(string $path = '/', array $query = []): string
{
    $path = '/' . ltrim($path, '/');

    if (cs_site_is_admin_host_request()) {
        $target = cs_site_current_scheme() . '://' . cs_site_public_host() . $path;

        return cs_site_build_url($target, $query);
    }

    return cs_site_build_url($path, $query);
}

function cs_site_admin_url(string $path = '/', array $query = []): string
{
    $path = '/' . ltrim($path, '/');

    if (cs_site_has_admin_subdomain()) {
        if (cs_site_is_admin_host_request()) {
            return cs_site_build_url($path, $query);
        }

        $target = cs_site_current_scheme() . '://' . cs_site_admin_host() . $path;

        return cs_site_build_url($target, $query);
    }

    $legacyPath = $path === '/' ? '/admin' : '/admin' . $path;

    return cs_site_build_url($legacyPath, $query);
}

function cs_site_admin_flash(string $key, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['creditsoft_site_admin_flash'][$key] = $message;
        return null;
    }

    $value = $_SESSION['creditsoft_site_admin_flash'][$key] ?? null;
    unset($_SESSION['creditsoft_site_admin_flash'][$key]);

    return is_string($value) ? $value : null;
}

function cs_site_admin_prefill_email(): string
{
    $queryEmail = strtolower(trim((string) ($_GET['email'] ?? '')));
    $cookieEmail = strtolower(trim((string) ($_COOKIE['creditsoft_site_admin_email'] ?? '')));
    $candidate = $queryEmail !== '' ? $queryEmail : $cookieEmail;

    return filter_var($candidate, FILTER_VALIDATE_EMAIL) ? $candidate : '';
}

function cs_site_admin_remember_email(string $email): void
{
    $email = strtolower(trim($email));

    if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return;
    }

    $isSecure = (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);

    setcookie('creditsoft_site_admin_email', $email, [
        'expires' => time() + (86400 * 180),
        'path' => '/',
        'domain' => '',
        'secure' => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function cs_site_admin_is_secure_request(): bool
{
    return (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https'
        || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);
}

function cs_site_admin_remember_lifetime(): int
{
    $days = (int) (getenv('CREDITSOFT_SITE_ADMIN_REMEMBER_DAYS') ?: 90);

    return max(1, $days) * 86400;
}

function cs_site_admin_remember_cookie_name(): string
{
    return 'creditsoft_site_admin_remember';
}

function cs_site_admin_remember_tokens_path(): string
{
    $preferredDirectory = dirname(__DIR__, 2) . '/web-meta';

    if (is_dir($preferredDirectory)) {
        return $preferredDirectory . '/creditsoft_admin_remember_tokens.php';
    }

    return dirname(__DIR__, 2) . '/creditsoft_admin_remember_tokens.php';
}

function cs_site_admin_remember_tokens(): array
{
    $path = cs_site_admin_remember_tokens_path();

    if (! is_file($path)) {
        return [];
    }

    $tokens = require $path;

    if (! is_array($tokens)) {
        return [];
    }

    $now = time();
    $clean = [];

    foreach ($tokens as $selector => $token) {
        if (! is_string($selector) || ! is_array($token)) {
            continue;
        }

        $expiresAt = (int) ($token['expires_at'] ?? 0);

        if ($expiresAt > $now) {
            $clean[$selector] = $token;
        }
    }

    return $clean;
}

function cs_site_admin_write_remember_tokens(array $tokens): bool
{
    $path = cs_site_admin_remember_tokens_path();
    $directory = dirname($path);

    if (! is_dir($directory) && ! @mkdir($directory, 0755, true) && ! is_dir($directory)) {
        return false;
    }

    $payload = "<?php\nreturn " . var_export($tokens, true) . ";\n";

    return @file_put_contents($path, $payload, LOCK_EX) !== false;
}

function cs_site_admin_set_remember_cookie(string $selector, string $validator, int $expiresAt): void
{
    setcookie(cs_site_admin_remember_cookie_name(), $selector . ':' . $validator, [
        'expires' => $expiresAt,
        'path' => '/',
        'domain' => '',
        'secure' => cs_site_admin_is_secure_request(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function cs_site_admin_clear_remember_cookie(): void
{
    setcookie(cs_site_admin_remember_cookie_name(), '', [
        'expires' => time() - 3600,
        'path' => '/',
        'domain' => '',
        'secure' => cs_site_admin_is_secure_request(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function cs_site_admin_issue_remember_token(array $user): void
{
    $email = strtolower(trim((string) ($user['email'] ?? '')));

    if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return;
    }

    $selector = bin2hex(random_bytes(12));
    $validator = bin2hex(random_bytes(32));
    $expiresAt = time() + cs_site_admin_remember_lifetime();
    $tokens = cs_site_admin_remember_tokens();
    $tokens[$selector] = [
        'email' => $email,
        'validator_hash' => hash('sha256', $validator),
        'created_at' => gmdate('c'),
        'last_used_at' => null,
        'expires_at' => $expiresAt,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        'user_agent_hash' => hash('sha256', (string) ($_SERVER['HTTP_USER_AGENT'] ?? '')),
    ];

    if (cs_site_admin_write_remember_tokens($tokens)) {
        cs_site_admin_set_remember_cookie($selector, $validator, $expiresAt);
    }
}

function cs_site_admin_forget_remember_token(): void
{
    $cookie = (string) ($_COOKIE[cs_site_admin_remember_cookie_name()] ?? '');
    [$selector] = array_pad(explode(':', $cookie, 2), 2, '');

    if ($selector !== '') {
        $tokens = cs_site_admin_remember_tokens();
        unset($tokens[$selector]);
        cs_site_admin_write_remember_tokens($tokens);
    }

    cs_site_admin_clear_remember_cookie();
}

function cs_site_admin_restore_remembered_login(): bool
{
    if (cs_site_admin_is_authenticated()) {
        return true;
    }

    $cookie = (string) ($_COOKIE[cs_site_admin_remember_cookie_name()] ?? '');
    [$selector, $validator] = array_pad(explode(':', $cookie, 2), 2, '');

    if ($selector === '' || $validator === '') {
        return false;
    }

    $tokens = cs_site_admin_remember_tokens();
    $token = $tokens[$selector] ?? null;

    if (! is_array($token)) {
        cs_site_admin_clear_remember_cookie();
        return false;
    }

    $expectedHash = (string) ($token['validator_hash'] ?? '');

    if ($expectedHash === '' || ! hash_equals($expectedHash, hash('sha256', $validator))) {
        unset($tokens[$selector]);
        cs_site_admin_write_remember_tokens($tokens);
        cs_site_admin_clear_remember_cookie();
        return false;
    }

    $user = cs_site_admin_find_user((string) ($token['email'] ?? ''));

    if (! $user) {
        unset($tokens[$selector]);
        cs_site_admin_write_remember_tokens($tokens);
        cs_site_admin_clear_remember_cookie();
        return false;
    }

    session_regenerate_id(true);

    $_SESSION['creditsoft_site_admin_user'] = [
        'email' => strtolower((string) ($user['email'] ?? '')),
        'name' => (string) ($user['name'] ?? 'CreditSoft Admin'),
        'role' => (string) ($user['role'] ?? 'Site admin'),
        'logged_in_at' => gmdate('c'),
        'remembered_at' => gmdate('c'),
    ];

    $expiresAt = time() + cs_site_admin_remember_lifetime();
    $tokens[$selector]['last_used_at'] = gmdate('c');
    $tokens[$selector]['expires_at'] = $expiresAt;
    cs_site_admin_write_remember_tokens($tokens);
    cs_site_admin_apply_session_cookie(true);
    cs_site_admin_set_remember_cookie($selector, $validator, $expiresAt);

    return true;
}

function cs_site_admin_csrf_token(): string
{
    if (empty($_SESSION['creditsoft_site_admin_csrf'])) {
        $_SESSION['creditsoft_site_admin_csrf'] = bin2hex(random_bytes(24));
    }

    return (string) $_SESSION['creditsoft_site_admin_csrf'];
}

function cs_site_admin_verify_csrf(?string $token): bool
{
    $sessionToken = (string) ($_SESSION['creditsoft_site_admin_csrf'] ?? '');

    return $sessionToken !== '' && is_string($token) && hash_equals($sessionToken, $token);
}

function cs_site_admin_default_users(): array
{
    return [
        [
            'email' => 'mmurphy@creditsoft.app',
            'name' => 'Matthew Murphy',
            'password_hash' => '$2y$12$KQZqnOSnUzcREJFp5ae.e.PAOPDwCdBP8c9uv8Jn9r69XoIfhZwa6',
            'role' => 'Owner admin',
            'two_factor_secret' => '',
            'two_factor_confirmed_at' => null,
            'two_factor_recovery_codes' => [],
        ],
        [
            'email' => 'matthew@creditsoft.app',
            'name' => 'Matthew Murphy',
            'password_hash' => '$2y$12$KQZqnOSnUzcREJFp5ae.e.PAOPDwCdBP8c9uv8Jn9r69XoIfhZwa6',
            'role' => 'Owner admin',
            'two_factor_secret' => '',
            'two_factor_confirmed_at' => null,
            'two_factor_recovery_codes' => [],
        ],
        [
            'email' => 'hello@creditsoft.app',
            'name' => 'CreditSoft Admin',
            'password_hash' => '$2y$12$KQZqnOSnUzcREJFp5ae.e.PAOPDwCdBP8c9uv8Jn9r69XoIfhZwa6',
            'role' => 'Site admin',
            'two_factor_secret' => '',
            'two_factor_confirmed_at' => null,
            'two_factor_recovery_codes' => [],
        ],
    ];
}

function cs_site_admin_users_storage_path(): string
{
    $preferredDirectory = dirname(__DIR__, 2) . '/web-meta';

    if (is_dir($preferredDirectory)) {
        return $preferredDirectory . '/creditsoft_admin_users.php';
    }

    return dirname(__DIR__, 2) . '/creditsoft_admin_users.php';
}

function cs_site_admin_users(): array
{
    $users = [];

    foreach ([
        dirname(__DIR__, 2) . '/creditsoft_admin_users.php',
        dirname(__DIR__, 2) . '/web-meta/creditsoft_admin_users.php',
        dirname(__DIR__, 3) . '/creditsoft_admin_users.php',
    ] as $usersPath) {
        if (file_exists($usersPath)) {
            $loaded = require $usersPath;
            if (is_array($loaded)) {
                $users = $loaded;
                break;
            }
        }
    }

    $envEmail = trim((string) (getenv('CREDITSOFT_SITE_ADMIN_EMAIL') ?: getenv('SITE_ADMIN_EMAIL') ?: ''));
    $envPasswordHash = trim((string) (getenv('CREDITSOFT_SITE_ADMIN_PASSWORD_HASH') ?: getenv('SITE_ADMIN_PASSWORD_HASH') ?: ''));
    $envPassword = trim((string) (getenv('CREDITSOFT_SITE_ADMIN_PASSWORD') ?: getenv('SITE_ADMIN_PASSWORD') ?: ''));

    if ($envEmail !== '') {
        $users[] = [
            'email' => strtolower($envEmail),
            'name' => trim((string) (getenv('CREDITSOFT_SITE_ADMIN_NAME') ?: 'Site admin')),
            'password_hash' => $envPasswordHash,
            'password_plain' => $envPassword,
            'role' => trim((string) (getenv('CREDITSOFT_SITE_ADMIN_ROLE') ?: 'Site admin')),
            'two_factor_secret' => trim((string) (getenv('CREDITSOFT_SITE_ADMIN_TOTP_SECRET') ?: '')),
            'two_factor_confirmed_at' => trim((string) (getenv('CREDITSOFT_SITE_ADMIN_TOTP_CONFIRMED_AT') ?: '')) ?: null,
            'two_factor_recovery_codes' => [],
        ];
    }

    if ($users === []) {
        $users = cs_site_admin_default_users();
    }

    $normalized = [];

    foreach ($users as $user) {
        $email = strtolower(trim((string) ($user['email'] ?? '')));

        if ($email === '') {
            continue;
        }

        $normalized[] = [
            'email' => $email,
            'name' => trim((string) ($user['name'] ?? 'CreditSoft Admin')) ?: 'CreditSoft Admin',
            'password_hash' => trim((string) ($user['password_hash'] ?? '')),
            'password_plain' => trim((string) ($user['password_plain'] ?? '')),
            'role' => trim((string) ($user['role'] ?? 'Site admin')) ?: 'Site admin',
            'two_factor_secret' => trim((string) ($user['two_factor_secret'] ?? '')),
            'two_factor_confirmed_at' => trim((string) ($user['two_factor_confirmed_at'] ?? '')) ?: null,
            'two_factor_recovery_codes' => array_values(array_filter(
                is_array($user['two_factor_recovery_codes'] ?? null) ? $user['two_factor_recovery_codes'] : [],
                fn ($code) => is_string($code) && trim($code) !== ''
            )),
        ];
    }

    return $normalized;
}

function cs_site_admin_find_user(string $email): ?array
{
    $needle = strtolower(trim($email));

    foreach (cs_site_admin_users() as $user) {
        if (($user['email'] ?? '') === $needle) {
            return $user;
        }
    }

    return null;
}

function cs_site_admin_write_users(array $users): bool
{
    $path = cs_site_admin_users_storage_path();
    $directory = dirname($path);

    if (! is_dir($directory) && ! @mkdir($directory, 0755, true) && ! is_dir($directory)) {
        return false;
    }

    $payload = "<?php\nreturn " . var_export(array_values($users), true) . ";\n";

    $written = @file_put_contents($path, $payload, LOCK_EX);

    return $written !== false;
}

function cs_site_admin_update_user(string $email, array $changes): bool
{
    $users = cs_site_admin_users();
    $email = strtolower(trim($email));
    $updated = false;

    foreach ($users as $index => $user) {
        if (($user['email'] ?? '') !== $email) {
            continue;
        }

        $users[$index] = array_merge($user, $changes, ['email' => $email]);
        $updated = true;
        break;
    }

    if (! $updated) {
        return false;
    }

    return cs_site_admin_write_users($users);
}

function cs_site_admin_issuer_label(): string
{
    return 'CreditSoft Admin';
}

function cs_site_admin_google2fa(): ?\PragmaRX\Google2FAQRCode\Google2FA
{
    static $service = false;

    if ($service !== false) {
        return $service instanceof \PragmaRX\Google2FAQRCode\Google2FA ? $service : null;
    }

    if (! class_exists(\PragmaRX\Google2FAQRCode\Google2FA::class)) {
        $service = null;

        return null;
    }

    $service = new \PragmaRX\Google2FAQRCode\Google2FA();

    return $service;
}

function cs_site_admin_generate_recovery_codes(int $count = 8): array
{
    $codes = [];

    while (count($codes) < $count) {
        $codes[] = strtoupper(substr(bin2hex(random_bytes(4)), 0, 4) . '-' . substr(bin2hex(random_bytes(4)), 0, 4));
    }

    return array_values(array_unique($codes));
}

function cs_site_admin_hash_recovery_codes(array $codes): array
{
    return array_map(
        static fn (string $code): string => password_hash($code, PASSWORD_DEFAULT),
        $codes
    );
}

function cs_site_admin_user_has_two_factor(array $user): bool
{
    return trim((string) ($user['two_factor_secret'] ?? '')) !== ''
        && trim((string) ($user['two_factor_confirmed_at'] ?? '')) !== '';
}

function cs_site_admin_two_factor_setup(array $user, bool $refresh = false): array
{
    $email = strtolower(trim((string) ($user['email'] ?? '')));
    $current = $_SESSION['creditsoft_site_admin_two_factor_setup'] ?? null;

    if (! $refresh && is_array($current) && ($current['email'] ?? '') === $email) {
        return $current;
    }

    $service = cs_site_admin_google2fa();

    if (! $service) {
        return [];
    }

    $secret = $service->generateSecretKey();
    $recoveryCodes = cs_site_admin_generate_recovery_codes();

    $setup = [
        'email' => $email,
        'secret' => $secret,
        'recovery_codes' => $recoveryCodes,
        'created_at' => gmdate('c'),
    ];

    $_SESSION['creditsoft_site_admin_two_factor_setup'] = $setup;

    return $setup;
}

function cs_site_admin_clear_two_factor_setup(): void
{
    unset($_SESSION['creditsoft_site_admin_two_factor_setup']);
}

function cs_site_admin_two_factor_qr_svg(string $email, string $secret): string
{
    $service = cs_site_admin_google2fa();

    if (! $service || $secret === '') {
        return '';
    }

    return (string) $service->getQRCodeInline(
        cs_site_admin_issuer_label(),
        $email,
        $secret
    );
}

function cs_site_admin_verify_two_factor_code(string $secret, string $code): bool
{
    $service = cs_site_admin_google2fa();
    $code = preg_replace('/\s+/', '', trim($code));

    if (! $service || $secret === '' || ! is_string($code) || $code === '') {
        return false;
    }

    return $service->verifyKey($secret, $code);
}

function cs_site_admin_verify_recovery_code(array $user, string $code): bool
{
    $code = strtoupper(trim($code));
    $stored = is_array($user['two_factor_recovery_codes'] ?? null) ? $user['two_factor_recovery_codes'] : [];

    foreach ($stored as $index => $hash) {
        if (! is_string($hash) || trim($hash) === '') {
            continue;
        }

        if (! password_verify($code, $hash)) {
            continue;
        }

        unset($stored[$index]);
        cs_site_admin_update_user((string) ($user['email'] ?? ''), [
            'two_factor_recovery_codes' => array_values($stored),
        ]);

        return true;
    }

    return false;
}

function cs_site_admin_begin_pending_login(array $user, string $redirect = '/'): void
{
    session_regenerate_id(true);

    $_SESSION['creditsoft_site_admin_pending_login'] = [
        'email' => strtolower((string) ($user['email'] ?? '')),
        'redirect' => $redirect !== '' && str_starts_with($redirect, '/') ? $redirect : '/',
        'remember' => ! empty($user['_remember']),
        'started_at' => gmdate('c'),
    ];
}

function cs_site_admin_pending_login(): ?array
{
    $pending = $_SESSION['creditsoft_site_admin_pending_login'] ?? null;

    return is_array($pending) && ! empty($pending['email']) ? $pending : null;
}

function cs_site_admin_clear_pending_login(): void
{
    unset($_SESSION['creditsoft_site_admin_pending_login']);
}

function cs_site_admin_pending_login_user(): ?array
{
    $pending = cs_site_admin_pending_login();

    if (! $pending) {
        return null;
    }

    return cs_site_admin_find_user((string) ($pending['email'] ?? ''));
}

function cs_site_admin_attempt_login(string $email, string $password): ?array
{
    $user = cs_site_admin_find_user($email);

    if (! $user) {
        return null;
    }

    $passwordHash = (string) ($user['password_hash'] ?? '');
    $passwordPlain = (string) ($user['password_plain'] ?? '');

    if ($passwordHash !== '' && password_verify($password, $passwordHash)) {
        return $user;
    }

    if ($passwordPlain !== '' && hash_equals($passwordPlain, $password)) {
        return $user;
    }

    return null;
}

function cs_site_admin_apply_session_cookie(bool $remember): void
{
    setcookie(session_name(), session_id(), [
        'expires' => $remember ? time() + cs_site_admin_remember_lifetime() : 0,
        'path' => '/',
        'domain' => '',
        'secure' => cs_site_admin_is_secure_request(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function cs_site_admin_login(array $user, bool $remember = false): void
{
    session_regenerate_id(true);

    $_SESSION['creditsoft_site_admin_user'] = [
        'email' => strtolower((string) ($user['email'] ?? '')),
        'name' => (string) ($user['name'] ?? 'CreditSoft Admin'),
        'role' => (string) ($user['role'] ?? 'Site admin'),
        'logged_in_at' => gmdate('c'),
    ];

    cs_site_admin_apply_session_cookie($remember);
    if ($remember) {
        cs_site_admin_issue_remember_token($user);
    } else {
        cs_site_admin_forget_remember_token();
    }
    cs_site_admin_remember_email((string) ($user['email'] ?? ''));
    cs_site_admin_clear_pending_login();
}

function cs_site_admin_logout(): void
{
    unset($_SESSION['creditsoft_site_admin_user']);
    cs_site_admin_clear_pending_login();
    cs_site_admin_clear_two_factor_setup();
    cs_site_admin_forget_remember_token();

    setcookie(session_name(), '', [
        'expires' => time() - 3600,
        'path' => '/',
        'domain' => '',
        'secure' => cs_site_admin_is_secure_request(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function cs_site_admin_user(): ?array
{
    $user = $_SESSION['creditsoft_site_admin_user'] ?? null;

    return is_array($user) ? $user : null;
}

function cs_site_admin_is_authenticated(): bool
{
    $user = cs_site_admin_user();

    return is_array($user) && ! empty($user['email']);
}

function cs_site_admin_redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function cs_site_admin_require_login(): array
{
    $user = cs_site_admin_user();

    if ($user) {
        return $user;
    }

    $target = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    cs_site_admin_redirect(cs_site_admin_url('/login', ['redirect' => $target]));
}

function cs_site_admin_turnstile_site_key(): string
{
    return defined('TURNSTILE_SITE_KEY') ? trim((string) TURNSTILE_SITE_KEY) : '';
}

function cs_site_admin_turnstile_secret(): string
{
    return defined('TURNSTILE_SECRET_KEY') ? trim((string) TURNSTILE_SECRET_KEY) : '';
}

function cs_site_admin_verify_turnstile(string $responseToken): bool
{
    if (cs_site_admin_request_is_trusted()) {
        return true;
    }

    $secret = cs_site_admin_turnstile_secret();

    if ($secret === '') {
        return true;
    }

    $responseToken = trim($responseToken);
    if ($responseToken === '') {
        return false;
    }

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'secret' => $secret,
            'response' => $responseToken,
            'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);

    $raw = curl_exec($ch);
    curl_close($ch);

    $decoded = json_decode((string) $raw, true);

    return ! empty($decoded['success']);
}

function cs_site_admin_db(): ?PDO
{
    if (function_exists('creditsoft_lead_db')) {
        return creditsoft_lead_db();
    }

    return null;
}

function cs_site_admin_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
    $stmt->execute([$table]);

    return (bool) $stmt->fetchColumn();
}

function cs_site_admin_column_exists(PDO $pdo, string $table, string $column): bool
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

function cs_site_admin_ensure_archive_columns(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `admin_customer_archives` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `target_email` VARCHAR(255) NOT NULL,
            `display_name` VARCHAR(255) DEFAULT NULL,
            `reason` VARCHAR(255) NOT NULL,
            `archived_by` VARCHAR(255) DEFAULT NULL,
            `snapshot_json` LONGTEXT DEFAULT NULL,
            `row_counts_json` TEXT DEFAULT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_target_email` (`target_email`),
            INDEX `idx_created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    foreach (['licenses', 'leads', 'zelle_payment_messages', 'payment_checkout_requests'] as $table) {
        if (! cs_site_admin_table_exists($pdo, $table)) {
            continue;
        }

        foreach ([
            'archived_at' => "ALTER TABLE `{$table}` ADD COLUMN `archived_at` DATETIME DEFAULT NULL",
            'archive_reason' => "ALTER TABLE `{$table}` ADD COLUMN `archive_reason` VARCHAR(255) DEFAULT NULL",
            'archived_by' => "ALTER TABLE `{$table}` ADD COLUMN `archived_by` VARCHAR(255) DEFAULT NULL",
        ] as $column => $sql) {
            if (! cs_site_admin_column_exists($pdo, $table, $column)) {
                $pdo->exec($sql);
            }
        }
    }
}

function cs_site_admin_placeholders(array $values): string
{
    return implode(', ', array_fill(0, count($values), '?'));
}

function cs_site_admin_fetch_all(PDO $pdo, string $sql, array $params = []): array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function cs_site_admin_delete_or_update_count(PDO $pdo, string $sql, array $params = []): int
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->rowCount();
}

function cs_site_admin_archive_customer(PDO $pdo, string $email, string $reason, string $archivedBy = ''): array
{
    $email = strtolower(trim($email));
    $reason = trim($reason);
    $archivedBy = trim($archivedBy);

    if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Choose a valid customer email to archive.'];
    }

    if ($reason === '') {
        return ['success' => false, 'message' => 'Archive reason is required.'];
    }

    cs_site_admin_ensure_archive_columns($pdo);

    $licenseIds = array_map(
        static fn (array $row): int => (int) $row['id'],
        cs_site_admin_fetch_all($pdo, 'SELECT id FROM licenses WHERE LOWER(customer_email) = LOWER(?) AND archived_at IS NULL', [$email])
    );
    $leadIds = cs_site_admin_table_exists($pdo, 'leads')
        ? array_map(
            static fn (array $row): int => (int) $row['id'],
            cs_site_admin_fetch_all($pdo, 'SELECT id FROM leads WHERE LOWER(email) = LOWER(?) AND archived_at IS NULL', [$email])
        )
        : [];

    $paymentWhere = ['LOWER(sender_email) = LOWER(?)'];
    $paymentParams = [$email];

    if ($licenseIds !== []) {
        $paymentWhere[] = 'license_id IN (' . cs_site_admin_placeholders($licenseIds) . ')';
        $paymentParams = array_merge($paymentParams, $licenseIds);
    }

    if ($leadIds !== []) {
        $paymentWhere[] = 'lead_id IN (' . cs_site_admin_placeholders($leadIds) . ')';
        $paymentParams = array_merge($paymentParams, $leadIds);
    }

    $paymentIds = cs_site_admin_table_exists($pdo, 'zelle_payment_messages')
        ? array_map(
            static fn (array $row): int => (int) $row['id'],
            cs_site_admin_fetch_all($pdo, 'SELECT id FROM zelle_payment_messages WHERE archived_at IS NULL AND (' . implode(' OR ', $paymentWhere) . ')', $paymentParams)
        )
        : [];

    $checkoutIds = cs_site_admin_table_exists($pdo, 'payment_checkout_requests')
        ? array_map(
            static fn (array $row): int => (int) $row['id'],
            cs_site_admin_fetch_all(
                $pdo,
                'SELECT id
                 FROM payment_checkout_requests
                 WHERE archived_at IS NULL
                   AND (LOWER(customer_email) = LOWER(?) OR LOWER(payment_memo_email) = LOWER(?))',
                [$email, $email]
            )
        )
        : [];

    if ($licenseIds === [] && $leadIds === [] && $paymentIds === [] && $checkoutIds === []) {
        return ['success' => false, 'message' => 'No active customer, license, checkout, or payment records were found for that email.'];
    }

    $snapshot = [
        'target_email' => $email,
        'reason' => $reason,
        'archived_by' => $archivedBy,
        'created_at' => gmdate('c'),
        'ids' => [
            'license_ids' => $licenseIds,
            'lead_ids' => $leadIds,
            'payment_ids' => $paymentIds,
            'checkout_ids' => $checkoutIds,
        ],
        'rows' => [],
    ];

    if ($licenseIds !== []) {
        $snapshot['rows']['licenses'] = cs_site_admin_fetch_all($pdo, 'SELECT * FROM licenses WHERE id IN (' . cs_site_admin_placeholders($licenseIds) . ')', $licenseIds);
        $snapshot['rows']['license_subscriptions'] = cs_site_admin_table_exists($pdo, 'license_subscriptions')
            ? cs_site_admin_fetch_all($pdo, 'SELECT * FROM license_subscriptions WHERE license_id IN (' . cs_site_admin_placeholders($licenseIds) . ')', $licenseIds)
            : [];
        $snapshot['rows']['license_logs'] = cs_site_admin_table_exists($pdo, 'license_logs')
            ? cs_site_admin_fetch_all($pdo, 'SELECT * FROM license_logs WHERE license_id IN (' . cs_site_admin_placeholders($licenseIds) . ')', $licenseIds)
            : [];
    }

    if ($leadIds !== []) {
        $snapshot['rows']['leads'] = cs_site_admin_fetch_all($pdo, 'SELECT * FROM leads WHERE id IN (' . cs_site_admin_placeholders($leadIds) . ')', $leadIds);
        $snapshot['rows']['lead_qualification_responses'] = cs_site_admin_table_exists($pdo, 'lead_qualification_responses')
            ? cs_site_admin_fetch_all($pdo, 'SELECT * FROM lead_qualification_responses WHERE lead_id IN (' . cs_site_admin_placeholders($leadIds) . ')', $leadIds)
            : [];
    }

    if ($paymentIds !== []) {
        $snapshot['rows']['zelle_payment_messages'] = cs_site_admin_fetch_all($pdo, 'SELECT * FROM zelle_payment_messages WHERE id IN (' . cs_site_admin_placeholders($paymentIds) . ')', $paymentIds);
    }

    if ($checkoutIds !== []) {
        $snapshot['rows']['payment_checkout_requests'] = cs_site_admin_fetch_all($pdo, 'SELECT * FROM payment_checkout_requests WHERE id IN (' . cs_site_admin_placeholders($checkoutIds) . ')', $checkoutIds);
    }

    if (cs_site_admin_table_exists($pdo, 'customer_onboarding_tokens')) {
        $tokenWhere = ['LOWER(email) = LOWER(?)'];
        $tokenParams = [$email];

        if ($licenseIds !== []) {
            $tokenWhere[] = 'license_id IN (' . cs_site_admin_placeholders($licenseIds) . ')';
            $tokenParams = array_merge($tokenParams, $licenseIds);
        }

        if ($leadIds !== []) {
            $tokenWhere[] = 'lead_id IN (' . cs_site_admin_placeholders($leadIds) . ')';
            $tokenParams = array_merge($tokenParams, $leadIds);
        }

        $snapshot['rows']['customer_onboarding_tokens'] = cs_site_admin_fetch_all($pdo, 'SELECT * FROM customer_onboarding_tokens WHERE ' . implode(' OR ', $tokenWhere), $tokenParams);
    }

    if (cs_site_admin_table_exists($pdo, 'payment_support_tickets')) {
        $snapshot['rows']['payment_support_tickets'] = cs_site_admin_fetch_all($pdo, 'SELECT * FROM payment_support_tickets WHERE LOWER(customer_email) = LOWER(?)', [$email]);
    }

    $rowCounts = array_map('count', $snapshot['rows']);
    $displayName = '';

    foreach ([
        $snapshot['rows']['licenses'][0]['customer_name'] ?? '',
        $snapshot['rows']['leads'][0]['name'] ?? '',
        $snapshot['rows']['zelle_payment_messages'][0]['sender_name'] ?? '',
    ] as $candidate) {
        $candidate = trim((string) $candidate);
        if ($candidate !== '') {
            $displayName = $candidate;
            break;
        }
    }

    $pdo->beginTransaction();

    try {
        $archiveStmt = $pdo->prepare(
            'INSERT INTO admin_customer_archives (target_email, display_name, reason, archived_by, snapshot_json, row_counts_json, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())'
        );
        $archiveStmt->execute([
            $email,
            $displayName !== '' ? $displayName : null,
            $reason,
            $archivedBy !== '' ? $archivedBy : null,
            json_encode($snapshot, JSON_UNESCAPED_SLASHES),
            json_encode($rowCounts, JSON_UNESCAPED_SLASHES),
        ]);
        $archiveId = (int) $pdo->lastInsertId();

        $counts = [];

        if ($licenseIds !== []) {
            $params = array_merge([$reason, $archivedBy !== '' ? $archivedBy : null], $licenseIds);
            $counts['licenses'] = cs_site_admin_delete_or_update_count(
                $pdo,
                'UPDATE licenses SET status = \'cancelled\', archived_at = NOW(), archive_reason = ?, archived_by = ? WHERE id IN (' . cs_site_admin_placeholders($licenseIds) . ')',
                $params
            );

            if (cs_site_admin_table_exists($pdo, 'license_subscriptions')) {
                $counts['license_subscriptions'] = cs_site_admin_delete_or_update_count(
                    $pdo,
                    'UPDATE license_subscriptions SET auto_renew = 0, updated_at = NOW() WHERE license_id IN (' . cs_site_admin_placeholders($licenseIds) . ')',
                    $licenseIds
                );
            }

            if (cs_site_admin_table_exists($pdo, 'license_logs')) {
                foreach ($licenseIds as $licenseId) {
                    $log = $pdo->prepare('INSERT INTO license_logs (license_id, action, ip_address, details) VALUES (?, ?, ?, ?)');
                    $log->execute([$licenseId, 'customer_archived', $_SERVER['REMOTE_ADDR'] ?? null, 'Archive #' . $archiveId . ': ' . $reason]);
                }
            }
        }

        if ($leadIds !== []) {
            $params = array_merge([$reason, $archivedBy !== '' ? $archivedBy : null], $leadIds);
            $counts['leads'] = cs_site_admin_delete_or_update_count(
                $pdo,
                'UPDATE leads SET status = \'lost\', archived_at = NOW(), archive_reason = ?, archived_by = ?, updated_at = NOW() WHERE id IN (' . cs_site_admin_placeholders($leadIds) . ')',
                $params
            );
        }

        if ($paymentIds !== []) {
            $params = array_merge([$reason, $archivedBy !== '' ? $archivedBy : null], $paymentIds);
            $counts['zelle_payment_messages'] = cs_site_admin_delete_or_update_count(
                $pdo,
                'UPDATE zelle_payment_messages SET status = \'archived\', payment_status = \'archived\', archived_at = NOW(), archive_reason = ?, archived_by = ?, updated_at = NOW() WHERE id IN (' . cs_site_admin_placeholders($paymentIds) . ')',
                $params
            );
        }

        if ($checkoutIds !== []) {
            $params = array_merge([$reason, $archivedBy !== '' ? $archivedBy : null], $checkoutIds);
            $counts['payment_checkout_requests'] = cs_site_admin_delete_or_update_count(
                $pdo,
                'UPDATE payment_checkout_requests SET archived_at = NOW(), archive_reason = ?, archived_by = ?, updated_at = NOW() WHERE id IN (' . cs_site_admin_placeholders($checkoutIds) . ')',
                $params
            );
        }

        if (isset($snapshot['rows']['customer_onboarding_tokens']) && $snapshot['rows']['customer_onboarding_tokens'] !== []) {
            $tokenIds = array_map(static fn (array $row): int => (int) $row['id'], $snapshot['rows']['customer_onboarding_tokens']);
            $counts['customer_onboarding_tokens'] = cs_site_admin_delete_or_update_count(
                $pdo,
                'UPDATE customer_onboarding_tokens SET used_at = COALESCE(used_at, NOW()), expires_at = LEAST(COALESCE(expires_at, NOW()), NOW()) WHERE id IN (' . cs_site_admin_placeholders($tokenIds) . ')',
                $tokenIds
            );
        }

        if (isset($snapshot['rows']['payment_support_tickets']) && $snapshot['rows']['payment_support_tickets'] !== []) {
            $ticketIds = array_map(static fn (array $row): int => (int) $row['id'], $snapshot['rows']['payment_support_tickets']);
            $counts['payment_support_tickets'] = cs_site_admin_delete_or_update_count(
                $pdo,
                'UPDATE payment_support_tickets SET status = \'archived\', updated_at = NOW() WHERE id IN (' . cs_site_admin_placeholders($ticketIds) . ')',
                $ticketIds
            );
        }

        $pdo->commit();

        return [
            'success' => true,
            'message' => 'Customer archived.',
            'archive_id' => $archiveId,
            'row_counts' => $counts,
            'snapshot_counts' => $rowCounts,
        ];
    } catch (Throwable $exception) {
        $pdo->rollBack();

        return ['success' => false, 'message' => $exception->getMessage()];
    }
}

function cs_site_admin_assessment_table_exists(PDO $pdo): bool
{
    $stmt = $pdo->query("SHOW TABLES LIKE 'assessment_results'");

    return (bool) $stmt->fetchColumn();
}

function cs_site_admin_payment_provider_label(?string $value): string
{
    $key = strtolower(trim((string) $value));
    $key = str_replace(['-', ' '], '_', $key);

    return match ($key) {
        'cashapp', 'cash_app', 'cash', 'square', 'square_cash' => 'Cash App',
        'zelle' => 'Zelle',
        default => $key !== '' ? cs_site_admin_badge_label($key) : 'Payment',
    };
}

function cs_site_admin_money_label(mixed $amount): string
{
    if ($amount === null || trim((string) $amount) === '') {
        return '';
    }

    return '$' . number_format((float) $amount, 2);
}

function cs_site_admin_dashboard_payment_contexts(PDO $pdo, array $leadRows): array
{
    if ($leadRows === [] || ! cs_site_admin_table_exists($pdo, 'zelle_payment_messages')) {
        return ['by_lead_id' => [], 'by_email' => []];
    }

    $leadIds = [];
    $emails = [];

    foreach ($leadRows as $row) {
        $leadId = (int) ($row['id'] ?? 0);
        $email = strtolower(trim((string) ($row['email'] ?? '')));

        if ($leadId > 0) {
            $leadIds[] = $leadId;
        }

        if ($email !== '') {
            $emails[] = $email;
        }
    }

    $leadIds = array_values(array_unique($leadIds));
    $emails = array_values(array_unique($emails));
    $where = [];
    $params = [];

    if ($leadIds !== []) {
        $where[] = 'lead_id IN (' . cs_site_admin_placeholders($leadIds) . ')';
        $params = array_merge($params, $leadIds);
    }

    if ($emails !== []) {
        $where[] = 'LOWER(sender_email) IN (' . cs_site_admin_placeholders($emails) . ')';
        $params = array_merge($params, $emails);

        if (cs_site_admin_table_exists($pdo, 'licenses')) {
            $where[] = 'license_id IN (SELECT id FROM licenses WHERE LOWER(customer_email) IN (' . cs_site_admin_placeholders($emails) . ') AND archived_at IS NULL)';
            $params = array_merge($params, $emails);
        }
    }

    if ($where === []) {
        return ['by_lead_id' => [], 'by_email' => []];
    }

    $rows = cs_site_admin_fetch_all(
        $pdo,
        'SELECT id, payment_provider, amount, expected_amount, balance_due, status, payment_status, match_type, lead_id, license_id, sender_name, sender_email, updated_at, processed_at, created_at
         FROM zelle_payment_messages
         WHERE archived_at IS NULL
           AND (' . implode(' OR ', $where) . ')
         ORDER BY COALESCE(updated_at, processed_at, created_at) DESC, id DESC
         LIMIT 200',
        $params
    );

    $contexts = ['by_lead_id' => [], 'by_email' => []];

    foreach ($rows as $row) {
        $leadId = (int) ($row['lead_id'] ?? 0);
        $email = strtolower(trim((string) ($row['sender_email'] ?? '')));

        if ($leadId > 0 && ! isset($contexts['by_lead_id'][$leadId])) {
            $contexts['by_lead_id'][$leadId] = $row;
        }

        if ($email !== '' && ! isset($contexts['by_email'][$email])) {
            $contexts['by_email'][$email] = $row;
        }
    }

    return $contexts;
}

function cs_site_admin_dashboard_license_contexts(PDO $pdo, array $leadRows): array
{
    if ($leadRows === [] || ! cs_site_admin_table_exists($pdo, 'licenses')) {
        return [];
    }

    $emails = [];
    foreach ($leadRows as $row) {
        $email = strtolower(trim((string) ($row['email'] ?? '')));
        if ($email !== '') {
            $emails[] = $email;
        }
    }

    $emails = array_values(array_unique($emails));
    if ($emails === []) {
        return [];
    }

    $rows = cs_site_admin_fetch_all(
        $pdo,
        'SELECT id, customer_email, customer_name, plan, status, created_at, expires_at
         FROM licenses
         WHERE archived_at IS NULL
           AND LOWER(customer_email) IN (' . cs_site_admin_placeholders($emails) . ')
         ORDER BY FIELD(status, \'active\', \'trial\', \'pending\', \'cancelled\'), created_at DESC, id DESC',
        $emails
    );

    $contexts = [];
    foreach ($rows as $row) {
        $email = strtolower(trim((string) ($row['customer_email'] ?? '')));
        if ($email !== '' && ! isset($contexts[$email])) {
            $contexts[$email] = $row;
        }
    }

    return $contexts;
}

function cs_site_admin_dashboard_payment_is_customer_context(?array $payment): bool
{
    if ($payment === null) {
        return false;
    }

    $paymentStatus = strtolower(trim((string) ($payment['payment_status'] ?? '')));
    $status = strtolower(trim((string) ($payment['status'] ?? '')));

    if (in_array($paymentStatus, ['paid', 'overpaid', 'balance_due'], true)) {
        return true;
    }

    return $paymentStatus === '' && in_array($status, ['paid', 'overpaid', 'balance_due', 'processed'], true);
}

function cs_site_admin_dashboard_enrich_lead(array $row, ?array $payment, ?array $license): array
{
    $status = strtolower(trim((string) ($row['status'] ?? '')));
    $hasPaymentContext = cs_site_admin_dashboard_payment_is_customer_context($payment);
    $hasCustomerContext = $status === 'converted' || $hasPaymentContext || $license !== null;

    if ($hasCustomerContext && trim((string) ($row['client_count'] ?? '')) === '') {
        $row['client_count'] = 'CreditSoft customer';
    }

    if ($hasCustomerContext && trim((string) ($row['current_workflow'] ?? '')) === '') {
        $row['current_workflow'] = 'CreditSoft Intranet';
    }

    if ($payment !== null && $hasPaymentContext) {
        $provider = cs_site_admin_payment_provider_label((string) ($payment['payment_provider'] ?? ''));
        $amount = cs_site_admin_money_label($payment['amount'] ?? null);
        $paymentStatus = cs_site_admin_badge_label((string) (($payment['payment_status'] ?? '') ?: ($payment['status'] ?? '')));
        $details = array_values(array_filter([
            $amount !== '' ? $amount . ' received' : '',
            $paymentStatus !== 'Unknown' ? $paymentStatus : '',
        ]));

        if (trim((string) ($row['merchant_status'] ?? '')) === '') {
            $row['merchant_status'] = 'Paid with ' . $provider;
        }

        if (trim((string) ($row['merchant_provider'] ?? '')) === '') {
            $row['merchant_provider'] = $details !== [] ? implode(' · ', $details) : $provider;
        }

        if (trim((string) ($row['payment_methods'] ?? '')) === '') {
            $row['payment_methods'] = $provider;
        }
    } elseif ($license !== null && trim((string) ($row['merchant_status'] ?? '')) === '') {
        $row['merchant_status'] = 'License ' . cs_site_admin_badge_label((string) ($license['status'] ?? 'active'));
        $row['merchant_provider'] = trim((string) ($row['merchant_provider'] ?? '')) ?: cs_site_admin_badge_label((string) ($license['plan'] ?? 'CreditSoft'));
    }

    return $row;
}

function cs_site_admin_client_count_label(mixed $value, bool $customerContext = false): string
{
    $value = trim((string) $value);

    if ($value !== '') {
        return $value;
    }

    return $customerContext ? 'Client count not captured' : 'Lead did not answer client count';
}

function cs_site_admin_workflow_label(mixed $value, bool $customerContext = false): string
{
    $value = trim((string) $value);

    if ($value !== '') {
        return $value;
    }

    return $customerContext ? 'CreditSoft Intranet' : 'Current software not captured';
}

function cs_site_admin_lead_has_customer_context(array $lead): bool
{
    $status = strtolower(trim((string) ($lead['status'] ?? '')));
    $merchant = trim((string) ($lead['merchant_status'] ?? ''));

    return $status === 'converted'
        || $merchant !== ''
        || strtolower(trim((string) ($lead['client_count'] ?? ''))) === 'creditsoft customer';
}

function cs_site_admin_dashboard_data(): array
{
    $pdo = cs_site_admin_db();

    $data = [
        'database_connected' => $pdo instanceof PDO,
        'turnstile_enabled' => cs_site_admin_turnstile_secret() !== '',
        'site_url' => cs_site_admin_base_url(),
        'database_error' => null,
        'stats' => [
            'total_leads' => 0,
            'new_leads' => 0,
            'qualified_leads' => 0,
            'assessment_results' => 0,
            'converted_leads' => 0,
        ],
        'leads' => [],
        'assessments' => [],
        'sources' => [],
        'workflow_counts' => [],
        'monitoring_counts' => [],
        'merchant_counts' => [],
    ];

    if (! $pdo) {
        return $data;
    }

    try {
        cs_site_admin_ensure_archive_columns($pdo);

        if (function_exists('creditsoft_lead_ensure_qualification_table')) {
            creditsoft_lead_ensure_qualification_table($pdo);
        }

        $statsRow = $pdo->query(
            "SELECT
                COUNT(*) AS total_leads,
                SUM(CASE WHEN status = 'new' THEN 1 ELSE 0 END) AS new_leads,
                SUM(CASE WHEN status = 'qualified' THEN 1 ELSE 0 END) AS qualified_leads,
                SUM(CASE WHEN status = 'converted' THEN 1 ELSE 0 END) AS converted_leads
             FROM leads
             WHERE archived_at IS NULL"
        )->fetch(PDO::FETCH_ASSOC) ?: [];

        $data['stats']['total_leads'] = (int) ($statsRow['total_leads'] ?? 0);
        $data['stats']['new_leads'] = (int) ($statsRow['new_leads'] ?? 0);
        $data['stats']['qualified_leads'] = (int) ($statsRow['qualified_leads'] ?? 0);
        $data['stats']['converted_leads'] = (int) ($statsRow['converted_leads'] ?? 0);

        if (cs_site_admin_assessment_table_exists($pdo)) {
            $data['stats']['assessment_results'] = (int) ($pdo->query("SELECT COUNT(*) FROM assessment_results")->fetchColumn() ?: 0);

            $assessmentStmt = $pdo->query(
                "SELECT name, email, source, assessment_label, score, max_score, coupon_code, created_at
                 FROM assessment_results
                 ORDER BY created_at DESC
                 LIMIT 8"
            );

            while ($row = $assessmentStmt->fetch(PDO::FETCH_ASSOC)) {
                $data['assessments'][] = $row;
            }
        }

        $leadStmt = $pdo->query(
            "SELECT
                l.id,
                l.name,
                l.email,
                l.phone,
                l.company,
                l.source,
                l.status,
                l.score,
                l.notes,
                l.created_at,
                l.updated_at,
                q.plan_interest,
                q.client_count,
                q.monitoring_systems,
                q.current_workflow,
                q.merchant_status,
                q.merchant_provider,
                q.payment_methods,
                q.website_status,
                q.website_sentiment,
                q.outsourcing_status,
                q.outsourcing_notes,
                q.roi_visibility,
                q.team_size,
                q.switch_timeline,
                q.biggest_pain,
                q.primary_goal,
                q.additional_notes
             FROM leads l
             LEFT JOIN lead_qualification_responses q ON q.lead_id = l.id
             WHERE l.archived_at IS NULL
             ORDER BY l.created_at DESC
             LIMIT 60"
        );

        $leadRows = $leadStmt->fetchAll(PDO::FETCH_ASSOC);
        $paymentContexts = cs_site_admin_dashboard_payment_contexts($pdo, $leadRows);
        $licenseContexts = cs_site_admin_dashboard_license_contexts($pdo, $leadRows);

        foreach ($leadRows as $row) {
            $leadId = (int) ($row['id'] ?? 0);
            $email = strtolower(trim((string) ($row['email'] ?? '')));
            $payment = $paymentContexts['by_lead_id'][$leadId] ?? ($paymentContexts['by_email'][$email] ?? null);
            $license = $licenseContexts[$email] ?? null;
            $row = cs_site_admin_dashboard_enrich_lead($row, is_array($payment) ? $payment : null, is_array($license) ? $license : null);

            $data['leads'][] = $row;

            $source = trim((string) ($row['source'] ?? ''));
            if ($source !== '') {
                $data['sources'][$source] = ($data['sources'][$source] ?? 0) + 1;
            }

            $workflow = trim((string) ($row['current_workflow'] ?? ''));
            if ($workflow !== '') {
                $data['workflow_counts'][$workflow] = ($data['workflow_counts'][$workflow] ?? 0) + 1;
            }

            $merchant = trim((string) ($row['merchant_status'] ?? ''));
            if ($merchant !== '') {
                $data['merchant_counts'][$merchant] = ($data['merchant_counts'][$merchant] ?? 0) + 1;
            }

            $monitoring = array_filter(array_map('trim', explode(',', (string) ($row['monitoring_systems'] ?? ''))));
            foreach ($monitoring as $provider) {
                $data['monitoring_counts'][$provider] = ($data['monitoring_counts'][$provider] ?? 0) + 1;
            }
        }
    } catch (Throwable $exception) {
        $data['database_error'] = $exception->getMessage();
    }

    arsort($data['sources']);
    arsort($data['workflow_counts']);
    arsort($data['monitoring_counts']);
    arsort($data['merchant_counts']);

    return $data;
}

function cs_site_admin_badge_label(string $value): string
{
    $value = trim($value);

    if ($value === '') {
        return 'Unknown';
    }

    return ucwords(str_replace(['_', '-'], ' ', $value));
}

function cs_site_admin_license_tables_exist(PDO $pdo): bool
{
    $stmt = $pdo->query("SHOW TABLES LIKE 'licenses'");

    return (bool) $stmt->fetchColumn();
}

function cs_site_admin_license_data(): array
{
    $pdo = cs_site_admin_db();
    $data = [
        'available' => false,
        'stats' => [
            'total' => 0,
            'active' => 0,
            'expired' => 0,
            'auto_renew' => 0,
        ],
        'licenses' => [],
        'customers' => [],
        'error' => null,
    ];

    if (! $pdo) {
        return $data;
    }

    try {
        if (! cs_site_admin_license_tables_exist($pdo)) {
            return $data;
        }

        $data['available'] = true;
        cs_site_admin_ensure_archive_columns($pdo);

        $stats = $pdo->query(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN l.status = 'active' THEN 1 ELSE 0 END) AS active,
                SUM(CASE WHEN l.status = 'expired' THEN 1 ELSE 0 END) AS expired,
                SUM(CASE WHEN s.billing_cycle = 'monthly' THEN 1 ELSE 0 END) AS monthly_terms
             FROM licenses l
             LEFT JOIN license_subscriptions s ON s.license_id = l.id
             WHERE l.archived_at IS NULL"
        )->fetch(PDO::FETCH_ASSOC) ?: [];

        $data['stats']['total'] = (int) ($stats['total'] ?? 0);
        $data['stats']['active'] = (int) ($stats['active'] ?? 0);
        $data['stats']['expired'] = (int) ($stats['expired'] ?? 0);
        $data['stats']['monthly_terms'] = (int) ($stats['monthly_terms'] ?? 0);

        $licenseStmt = $pdo->query(
            "SELECT
                l.id,
                l.license_key,
                l.customer_email,
                l.customer_name,
                l.plan,
                l.status,
                l.created_at,
                l.expires_at,
                l.last_validated,
                l.domain,
                s.billing_cycle,
                s.amount,
                s.next_billing,
                s.last_payment_at,
                s.last_payment_status,
                (
                    SELECT q.client_count
                    FROM leads ld
                    LEFT JOIN lead_qualification_responses q ON q.lead_id = ld.id
                    WHERE LOWER(ld.email) = LOWER(l.customer_email)
                      AND ld.archived_at IS NULL
                    ORDER BY ld.created_at DESC, ld.id DESC
                    LIMIT 1
                ) AS client_count,
                (
                    SELECT q.current_workflow
                    FROM leads ld
                    LEFT JOIN lead_qualification_responses q ON q.lead_id = ld.id
                    WHERE LOWER(ld.email) = LOWER(l.customer_email)
                      AND ld.archived_at IS NULL
                    ORDER BY ld.created_at DESC, ld.id DESC
                    LIMIT 1
                ) AS current_workflow
             FROM licenses l
             LEFT JOIN license_subscriptions s ON s.license_id = l.id
             WHERE l.archived_at IS NULL
             ORDER BY l.created_at DESC
             LIMIT 150"
        );

        while ($row = $licenseStmt->fetch(PDO::FETCH_ASSOC)) {
            $data['licenses'][] = $row;
        }

        $customerStmt = $pdo->query(
            "SELECT
                l.customer_email,
                MAX(COALESCE(NULLIF(l.customer_name, ''), ld.name)) AS display_name,
                MAX(ld.company) AS company,
                MAX(ld.phone) AS phone,
                MAX(ld.status) AS lead_status,
                COUNT(l.id) AS license_count,
                SUM(CASE WHEN l.status = 'active' THEN 1 ELSE 0 END) AS active_licenses,
                GROUP_CONCAT(DISTINCT l.plan ORDER BY l.plan SEPARATOR ', ') AS plans,
                MAX(l.expires_at) AS latest_expiration,
	                MAX(l.created_at) AS latest_created
	             FROM licenses l
	             LEFT JOIN leads ld ON LOWER(ld.email) = LOWER(l.customer_email) AND ld.archived_at IS NULL
	             WHERE l.archived_at IS NULL
	             GROUP BY l.customer_email
	             ORDER BY latest_created DESC
	             LIMIT 150"
        );

        while ($row = $customerStmt->fetch(PDO::FETCH_ASSOC)) {
            $data['customers'][] = $row;
        }
    } catch (Throwable $exception) {
        $data['error'] = $exception->getMessage();
    }

    return $data;
}

function cs_site_admin_generate_license_key(int $length = 32): string
{
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $key = '';

    for ($i = 0; $i < $length; $i++) {
        $key .= $chars[random_int(0, strlen($chars) - 1)];
    }

    return rtrim(chunk_split($key, 4, '-'), '-');
}

function cs_site_admin_create_license(string $email, string $planKey, string $duration, string $customerName = ''): array
{
    $pdo = cs_site_admin_db();

    if (! $pdo) {
        return ['success' => false, 'message' => 'Lead database is not available on this host yet.'];
    }

    if (! cs_site_admin_license_tables_exist($pdo)) {
        return ['success' => false, 'message' => 'License tables are not available on this host yet.'];
    }

    $pricing = creditsoft_site_pricing_load();
    $plan = $pricing['plans'][$planKey] ?? null;
    $licensePlanKey = creditsoft_site_license_plan_key($planKey);

    if (! $plan) {
        return ['success' => false, 'message' => 'Choose a valid plan before creating a license.'];
    }

    $email = strtolower(trim($email));
    $customerName = trim($customerName);

    if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Use a valid customer email address.'];
    }

    $duration = in_array($duration, ['monthly', 'yearly', 'lifetime'], true) ? $duration : 'monthly';
    $expiresAt = match ($duration) {
        'yearly' => date('Y-m-d H:i:s', strtotime('+365 days')),
        'lifetime' => date('Y-m-d H:i:s', strtotime('+3650 days')),
        default => date('Y-m-d H:i:s', strtotime('+30 days')),
    };

    $amount = match ($duration) {
        'yearly' => (float) ($plan['yearly'] ?? 0),
        'lifetime' => 0.00,
        default => (float) ($plan['monthly'] ?? 0),
    };

    try {
        $licenseKey = cs_site_admin_generate_license_key();

        $stmt = $pdo->prepare(
            "INSERT INTO licenses (license_key, customer_email, customer_name, plan, status, created_at, expires_at, ip_address)
             VALUES (?, ?, ?, ?, 'active', NOW(), ?, ?)"
        );
        $stmt->execute([
            $licenseKey,
            $email,
            $customerName !== '' ? $customerName : null,
            $licensePlanKey,
            $expiresAt,
            $_SERVER['REMOTE_ADDR'] ?? null,
        ]);

        $licenseId = (int) $pdo->lastInsertId();

        if ($duration !== 'lifetime') {
            $subscription = $pdo->prepare(
                "INSERT INTO license_subscriptions (license_id, billing_cycle, amount, next_billing, auto_renew, created_at, updated_at)
                 VALUES (?, ?, ?, ?, 1, NOW(), NOW())"
            );
            $subscription->execute([$licenseId, $duration, $amount, $expiresAt]);
        }

        return [
            'success' => true,
            'message' => 'License created successfully.',
            'license_key' => $licenseKey,
            'expires_at' => $expiresAt,
        ];
    } catch (Throwable $exception) {
        return ['success' => false, 'message' => 'Could not create the license yet: ' . $exception->getMessage()];
    }
}

cs_site_admin_restore_remembered_login();
