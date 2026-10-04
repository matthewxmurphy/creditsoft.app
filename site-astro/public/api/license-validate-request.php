<?php
declare(strict_types=1);

/** Validation-only compatibility. No body, key or private configuration is logged. */
final class CreditSoftLicenseValidation {
    public const MAX_BODY_BYTES = 16384;
    public const MAX_KEY_BYTES = 256;

    private static function invalid(string $message, int $status = 400): array {
        return ['ok' => false, 'http_status' => $status, 'payload' => [
            'valid' => false, 'status' => 'invalid', 'message' => $message,
            'product' => 'creditsoft', 'features' => [], 'can_access_workspace' => false,
        ]];
    }

    public static function unavailable(): array {
        return ['http_status' => 503, 'payload' => [
            'valid' => false, 'status' => 'unavailable', 'message' => 'License validation service is unavailable.',
            'product' => 'creditsoft', 'features' => [], 'can_access_workspace' => false,
        ]];
    }

    /** Detect duplicate top-level JSON keys after decoding escaped key names. */
    private static function duplicateJsonKeys(string $raw): bool {
        $depth = 0;
        $keys = [];
        $length = strlen($raw);
        for ($i = 0; $i < $length; $i++) {
            $char = $raw[$i];
            if ($char === '{' || $char === '[') { $depth++; }
            elseif ($char === '}' || $char === ']') { $depth--; }
            elseif ($char === '"') {
                $start = $i;
                for ($i++; $i < $length; $i++) {
                    if ($raw[$i] === '\\') { $i++; }
                    elseif ($raw[$i] === '"') { break; }
                }
                $next = $i + 1;
                while ($next < $length && ctype_space($raw[$next])) { $next++; }
                if ($depth === 1 && $next < $length && $raw[$next] === ':') {
                    $key = json_decode(substr($raw, $start, $i - $start + 1));
                    if (!is_string($key) || isset($keys[$key])) { return true; }
                    $keys[$key] = true;
                }
            }
        }
        return false;
    }

    private static function duplicateFormKeys(string $raw): bool {
        $seen = [];
        foreach (explode('&', $raw) as $part) {
            $key = urldecode(explode('=', $part, 2)[0]);
            if (in_array($key, ['key', 'license_key', 'action', 'product'], true)) {
                if (isset($seen[$key])) { return true; }
                $seen[$key] = true;
            }
        }
        return false;
    }

    public static function normalize(string $method, array $query, array $form, string $raw, string $contentType = '', $contentLength = null, string $queryString = ''): array {
        $method = strtoupper($method);
        if (!in_array($method, ['GET', 'POST', 'OPTIONS'], true)) { return self::invalid('Use GET or POST for validation.', 405); }
        if ($contentLength !== null && (!is_scalar($contentLength) || preg_match('/\A[0-9]+\z/D', (string) $contentLength) !== 1 || (float) $contentLength > self::MAX_BODY_BYTES)) {
            return self::invalid('Request exceeds the supported size or has an invalid length.');
        }
        if (strlen($raw) > self::MAX_BODY_BYTES || strlen($queryString) > self::MAX_BODY_BYTES) { return self::invalid('Request exceeds the supported size.'); }
        if ($method === 'OPTIONS') { return ['ok' => false, 'http_status' => 204, 'payload' => null]; }
        if (self::duplicateFormKeys($queryString)) { return self::invalid('Duplicate validation fields are not accepted.'); }
        $data = [];
        if ($method === 'POST') {
            $mediaType = strtolower(trim(explode(';', $contentType, 2)[0]));
            if ($mediaType === 'application/json') {
                $decoded = json_decode($raw);
                if (json_last_error() !== JSON_ERROR_NONE || !is_object($decoded) || self::duplicateJsonKeys($raw)) { return self::invalid('Use a JSON object with unique validation fields.'); }
                $data = (array) $decoded;
            } elseif ($mediaType === 'application/x-www-form-urlencoded') {
                if (self::duplicateFormKeys($raw)) { return self::invalid('Duplicate validation fields are not accepted.'); }
                $data = $form;
            } else { return self::invalid('Use JSON or URL-encoded form data.'); }
        } elseif ($raw !== '' || $form !== []) { return self::invalid('GET validation does not accept a request body.'); }
        $keys = [];
        foreach ([$query, $data] as $fields) {
            foreach (['action', 'product', 'license_key', 'key'] as $name) {
                if (!array_key_exists($name, $fields)) { continue; }
                $value = $fields[$name];
                if (!is_string($value)) { return self::invalid('Validation fields must be strings.'); }
                if ($name === 'action') {
                    if ($value !== 'validate') { return self::invalid('This endpoint only validates licenses.'); }
                } elseif ($name === 'product') {
                    if ($value !== 'creditsoft') { return self::invalid('This license service does not grant that product.', 403); }
                } else {
                    $key = strtoupper(trim($value));
                    if (strlen($key) > self::MAX_KEY_BYTES || preg_match('/\A[A-Z0-9]+(?:-[A-Z0-9]+)*\z/D', $key) !== 1) { return self::invalid('Provide a supported license key.'); }
                    $key = str_replace('-', '', $key);
                    if (strlen($key) < 20) { return self::invalid('Provide a supported license key.'); }
                    $keys[] = $key;
                }
            }
        }
        if ($keys === []) { return self::invalid('A license key is required.'); }
        if (count(array_unique($keys)) !== 1) { return self::invalid('Conflicting license keys are not accepted.'); }
        return ['ok' => true, 'key' => $keys[0], 'product' => 'creditsoft'];
    }

    private static function text($value, int $limit): string {
        if (!is_string($value)) { return ''; }
        $value = trim(preg_replace('/[\x00-\x1f\x7f]/', ' ', strip_tags($value)));
        $value = substr($value, 0, $limit);
        while ($value !== '' && preg_match('//u', $value) !== 1) { $value = substr($value, 0, -1); }
        return $value;
    }

    private static function realExpiry($value): bool {
        if (!is_string($value) || preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}[ T][0-9]{2}:[0-9]{2}:[0-9]{2}(?:Z|[+-][0-9]{2}:[0-9]{2})?\z/D', $value) !== 1) { return false; }
        try {
            $date = new DateTimeImmutable($value, new DateTimeZone('UTC'));
            return $date->format('Y-m-d') === substr($value, 0, 10);
        } catch (Throwable $error) { return false; }
    }

    private static function features($value, int $depth, int &$nodes) {
        if ($depth > 4 || ++$nodes > 256) { throw new RuntimeException('Unsupported feature data'); }
        if (is_array($value)) {
            if (count($value) > 100) { throw new RuntimeException('Unsupported feature data'); }
            $clean = [];
            foreach ($value as $key => $item) {
                if (!is_int($key) && (!is_string($key) || strlen($key) > 80 || preg_match('/\A[A-Za-z0-9_.-]+\z/D', $key) !== 1 || preg_match('/\A(?:customer(?:_email|_name)?|license_key|api_key|password|passwd|token|secret|session_id|authorization|credentials)\z/iD', $key))) { throw new RuntimeException('Unsupported feature data'); }
                $clean[$key] = self::features($item, $depth + 1, $nodes);
            }
            return $clean;
        }
        if (is_string($value)) {
            if (strlen($value) > 160 || self::text($value, 160) !== $value) { throw new RuntimeException('Unsupported feature data'); }
            return $value;
        }
        if (is_bool($value) || is_int($value) || $value === null) { return $value; }
        if (is_float($value) && is_finite($value)) { return $value; }
        throw new RuntimeException('Unsupported feature data');
    }

    public static function filterResponse(string $raw, string $requestKey = ''): array {
        if (strlen($raw) > 65536) { return self::unavailable(); }
        $data = json_decode($raw, true);
        if (!is_array($data) || !array_key_exists('valid', $data) || !is_bool($data['valid']) || !isset($data['status']) || !is_string($data['status'])) { return self::unavailable(); }
        // Older CreditSoft responses omit product; an explicit different product
        // cannot become a CreditSoft grant simply by normalizing this envelope.
        if (array_key_exists('product', $data) && $data['product'] !== 'creditsoft') { return self::unavailable(); }
        $status = strtolower($data['status']);
        $message = isset($data['message']) && is_string($data['message']) ? $data['message'] : '';
        foreach (['demo', 'demo_mode', 'is_demo', 'format_only', 'soft_validation'] as $flag) {
            if (!empty($data[$flag])) { return self::unavailable(); }
        }
        if (isset($data['mode']) && in_array($data['mode'], ['demo', 'soft', 'format'], true)) { return self::unavailable(); }
        if (preg_match('/(?:no[\s_-]*(?:db|database)|format[\s_-]*only|demo(?:[\s_-]*mode)?|soft[\s_-]*validation)/i', $message) || !in_array($status, ['active', 'valid', 'licensed', 'grace', 'expired', 'locked', 'suspended', 'invalid', 'inactive', 'revoked'], true)) { return self::unavailable(); }
        if ($data['valid'] && !in_array($status, ['active', 'valid', 'licensed'], true)) { return self::unavailable(); }
        $payload = ['valid' => $data['valid'], 'status' => $status, 'product' => 'creditsoft'];
        $message = self::text($message, 255);
        if ($requestKey !== '') {
            $message = str_replace($requestKey, '[redacted]', $message);
        }
        $payload['message'] = $message !== '' ? $message : ($data['valid'] ? 'License validated.' : 'License is not active.');
        foreach (['plan', 'plan_key', 'edition', 'tier', 'sku', 'access_state'] as $name) {
            if (isset($data[$name]) && is_string($data[$name])) { $payload[$name] = self::text($data[$name], 80); }
        }
        foreach (['expires_at', 'expired_at', 'grace_ends_at'] as $name) {
            if (array_key_exists($name, $data) && ($data[$name] === null || self::realExpiry($data[$name]))) { $payload[$name] = $data[$name]; }
        }
        foreach (['grace_days', 'grace_days_remaining'] as $name) {
            if (isset($data[$name]) && is_int($data[$name]) && $data[$name] >= 0 && $data[$name] <= 3650) { $payload[$name] = $data[$name]; }
        }
        foreach (['grace_expired', 'in_grace_period', 'can_access_workspace'] as $name) {
            if (isset($data[$name]) && is_bool($data[$name])) { $payload[$name] = $data[$name]; }
        }
        // The deployed validationPayload reports valid:false during a supported
        // grace window, with this complete and explicit access contract.
        $graceAccess = !$data['valid'] && $status === 'grace'
            && ($payload['in_grace_period'] ?? false) === true
            && ($payload['grace_expired'] ?? true) === false
            && ($payload['can_access_workspace'] ?? false) === true
            && ($payload['access_state'] ?? '') === 'grace'
            && isset($payload['grace_ends_at'])
            && isset($payload['grace_days_remaining']);
        if ($data['valid'] || $graceAccess) {
            try {
                $nodes = 0;
                $payload['features'] = self::features(isset($data['features']) && is_array($data['features']) ? $data['features'] : [], 0, $nodes);
                if (strlen(json_encode($payload['features'])) > 8192) { return self::unavailable(); }
            } catch (Throwable $error) { return self::unavailable(); }
            if (!isset($payload['can_access_workspace'])) { $payload['can_access_workspace'] = $data['valid']; }
        } else {
            $payload['features'] = [];
            $payload['can_access_workspace'] = false;
            if (isset($payload['in_grace_period'])) { $payload['in_grace_period'] = false; }
            if (isset($payload['access_state'])) { $payload['access_state'] = 'locked'; }
        }
        return ['http_status' => 200, 'payload' => $payload];
    }

    private static function send(array $result): void {
        http_response_code($result['http_status']);
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: private, no-store, max-age=0');
        header('X-Robots-Tag: noindex, nofollow, noarchive');
        header('Allow: GET, POST, OPTIONS');
        if ($result['payload'] !== null) { echo json_encode($result['payload'], JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE); }
    }

    /** Fixed local paths supplied by the entrypoint, never selected by requests.
     * The optional factory permits isolated tests; the public entrypoint uses PDO.
     */
    public static function prepare(string $legacyPath, string $configPath, ?callable $connectionFactory = null, ?array $input = null): ?array {
        if ($input === null) {
            $raw = file_get_contents('php://input', false, null, 0, self::MAX_BODY_BYTES + 1);
            $input = [
                'method' => isset($_SERVER['REQUEST_METHOD']) ? (string) $_SERVER['REQUEST_METHOD'] : '',
                'query' => $_GET, 'form' => $_POST, 'raw' => is_string($raw) ? $raw : '',
                'content_type' => isset($_SERVER['CONTENT_TYPE']) ? (string) $_SERVER['CONTENT_TYPE'] : '',
                'content_length' => isset($_SERVER['CONTENT_LENGTH']) ? $_SERVER['CONTENT_LENGTH'] : null,
                'query_string' => isset($_SERVER['QUERY_STRING']) ? (string) $_SERVER['QUERY_STRING'] : '',
            ];
        }
        $request = self::normalize($input['method'], $input['query'], $input['form'], $input['raw'], $input['content_type'], $input['content_length'], $input['query_string']);
        if (!$request['ok']) { self::send($request); return null; }
        if (!is_file($legacyPath) || !is_readable($legacyPath) || !is_file($configPath) || !is_readable($configPath)) { self::send(self::unavailable()); return null; }
        $level = ob_get_level();
        ob_start();
        try {
            require_once $configPath;
            ob_end_clean();
            foreach (['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'] as $name) {
                if (!defined($name) || !is_string(constant($name)) || ($name !== 'DB_PASS' && constant($name) === '')) { throw new RuntimeException('License configuration unavailable'); }
            }
            $factory = $connectionFactory ?: function ($dsn, $user, $password) {
                return new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]);
            };
            $connection = $factory('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS);
            if (!is_object($connection) || !method_exists($connection, 'query')) { throw new RuntimeException('License database unavailable'); }
            $probe = $connection->query('SELECT 1');
            if (!is_object($probe) || !method_exists($probe, 'fetchColumn') || (string) $probe->fetchColumn() !== '1') { throw new RuntimeException('License database unavailable'); }
            $connection = null;
        } catch (Throwable $error) {
            while (ob_get_level() > $level) { ob_end_clean(); }
            self::send(self::unavailable());
            return null;
        }
        return $request;
    }

    public static function startLegacyResponseFilter(string $key): int {
        $level = ob_get_level();
        // Exit in the legacy script still invokes this response filter at flush.
        ob_start(function ($output) use ($key) {
            $result = self::filterResponse($output, $key);
            http_response_code($result['http_status']);
            header('Content-Type: application/json; charset=UTF-8');
            header('Cache-Control: private, no-store, max-age=0');
            header('X-Robots-Tag: noindex, nofollow, noarchive');
            return json_encode($result['payload'], JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        });
        $_GET = ['action' => 'validate', 'key' => $key];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        return $level;
    }

    public static function legacyException(int $level): void {
        while (ob_get_level() > $level) { ob_end_clean(); }
        self::send(self::unavailable());
    }
}
