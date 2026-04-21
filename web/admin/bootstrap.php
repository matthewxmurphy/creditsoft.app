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

function cs_site_admin_assessment_table_exists(PDO $pdo): bool
{
    $stmt = $pdo->query("SHOW TABLES LIKE 'assessment_results'");

    return (bool) $stmt->fetchColumn();
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
        if (function_exists('creditsoft_lead_ensure_qualification_table')) {
            creditsoft_lead_ensure_qualification_table($pdo);
        }

        $statsRow = $pdo->query(
            "SELECT
                COUNT(*) AS total_leads,
                SUM(CASE WHEN status = 'new' THEN 1 ELSE 0 END) AS new_leads,
                SUM(CASE WHEN status = 'qualified' THEN 1 ELSE 0 END) AS qualified_leads,
                SUM(CASE WHEN status = 'converted' THEN 1 ELSE 0 END) AS converted_leads
             FROM leads"
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
             ORDER BY l.created_at DESC
             LIMIT 60"
        );

        while ($row = $leadStmt->fetch(PDO::FETCH_ASSOC)) {
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

        $stats = $pdo->query(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN l.status = 'active' THEN 1 ELSE 0 END) AS active,
                SUM(CASE WHEN l.status = 'expired' THEN 1 ELSE 0 END) AS expired,
                SUM(CASE WHEN s.auto_renew = 1 THEN 1 ELSE 0 END) AS auto_renew
             FROM licenses l
             LEFT JOIN license_subscriptions s ON s.license_id = l.id"
        )->fetch(PDO::FETCH_ASSOC) ?: [];

        $data['stats']['total'] = (int) ($stats['total'] ?? 0);
        $data['stats']['active'] = (int) ($stats['active'] ?? 0);
        $data['stats']['expired'] = (int) ($stats['expired'] ?? 0);
        $data['stats']['auto_renew'] = (int) ($stats['auto_renew'] ?? 0);

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
                s.auto_renew,
                s.last_payment_at,
                s.last_payment_status,
                s.failed_attempts
             FROM licenses l
             LEFT JOIN license_subscriptions s ON s.license_id = l.id
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
             LEFT JOIN leads ld ON LOWER(ld.email) = LOWER(l.customer_email)
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
