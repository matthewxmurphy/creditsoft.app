<?php

namespace App\Services;

/**
 * Shared Amazon authority for the existing CreditSoft plan/update contract.
 * Copyright (C) 2026 Matthew X Murphy. https://MatthewxMurphy.com
 * Brand: https://builtnotbegged.com
 * SPDX-License-Identifier: MIT
 */
final class AwsLicenseAuthority
{
    public const BASE_URL = 'https://w473zm07ej.execute-api.us-east-1.amazonaws.com/license';

    public const OFFICE_PLANS = ['creditsoft', 'enterprise', 'enterprise_pro', 'office'];

    public static function normalizeKey(string $key): string
    {
        $key = strtoupper(trim($key));
        $stripped = str_replace(['-', ' '], '', $key);

        return ! str_contains($key, ':') && strlen($stripped) === 32 && ctype_alnum($stripped)
            ? implode('-', str_split($stripped, 4)) : $key;
    }

    public static function endpoint(string $action, string $configured = ''): string
    {
        if (! in_array($action, ['validate', 'updates'], true)) {
            throw new \InvalidArgumentException('Unknown CreditSoft license operation.');
        }

        // Configuration remains stored, but credentials go only to this owner’s
        // reviewed authority, including when an old override names another AWS tenant.
        return self::BASE_URL.'/'.$action;
    }

    public static function amazonHttpsUrl(string $url, bool $allowQuery = true): bool
    {
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));

        return is_array($parts)
            && ($parts['scheme'] ?? '') === 'https'
            && (str_ends_with($host, '.amazonaws.com') || str_ends_with($host, '.cloudfront.net'))
            && ! isset($parts['user']) && ! isset($parts['pass'])
            && ! isset($parts['fragment'])
            && (! isset($parts['port']) || $parts['port'] === 443)
            && ($allowQuery || ! isset($parts['query']));
    }

    public static function officePlan(array $payload): bool
    {
        $plan = $payload['plan_key'] ?? $payload['plan'] ?? $payload['product'] ?? null;

        return is_string($plan) && in_array($plan, self::OFFICE_PLANS, true)
            && (! isset($payload['product']) || in_array($payload['product'], self::OFFICE_PLANS, true));
    }

    public static function officeEdition(array $payload): string
    {
        $family = $payload['plan_key'] ?? $payload['plan'] ?? '';
        $plan = strtolower(trim((string) ($family === 'creditsoft' ? ($payload['plan'] ?? 'enterprise') : $family)));
        $plan = str_replace(['—', '-', ' '], '_', $plan);

        if (in_array($plan, ['cluster', 'cluster_license', 'office_cluster'], true)) {
            return 'cluster';
        }

        return in_array($plan, ['enterprise_pro', 'pro', 'api', 'api_version', 'packaged_pro'], true)
            ? 'enterprise_pro' : 'enterprise';
    }

    public static function packageMatches(string $path, array $release): bool
    {
        if (! is_file($path) || ! is_int($release['size'] ?? null)
            || filesize($path) !== $release['size']) {
            return false;
        }

        foreach (['md5' => 32, 'sha256' => 64] as $algorithm => $length) {
            $expected = $release[$algorithm] ?? null;

            if (! is_string($expected) || strlen($expected) !== $length
                || ! ctype_xdigit($expected)
                || ! hash_equals(strtolower($expected), (string) hash_file($algorithm, $path))) {
                return false;
            }
        }

        return true;
    }

    /** Map AWS's licensed nested release, without trusting web-host mirrors. */
    public static function updateFeed(array $payload): ?array
    {
        if (($payload['authorized'] ?? false) !== true || ! self::officePlan($payload)) {
            return null;
        }

        $latest = $payload['latest'] ?? null;

        if ($latest === null) {
            return ['authorized' => true, 'latest_version' => null,
                'headline' => 'No CreditSoft release published',
                'summary' => 'Your license is authorized. No office release is published for this plan yet.',
                'download_url' => null, 'package_path' => null, 'notes' => []];
        }

        if (! is_array($latest)) {
            return null;
        }

        $version = $latest['version'] ?? $payload['latest_version'] ?? null;
        $url = $latest['package_url'] ?? null;
        $md5 = $latest['md5'] ?? null;
        $sha256 = $latest['sha256'] ?? null;
        $size = $latest['size'] ?? null;
        $file = $latest['file'] ?? null;

        if (! is_string($version) || preg_match('/\A\d+(?:\.\d+){1,5}\z/D', $version) !== 1
            || ! is_string($url) || ! self::amazonHttpsUrl($url)
            || ! is_string($file) || $file !== 'creditsoft-office-v'.$version.'.zip'
            || array_key_exists('required_feature', $latest)
            || (! is_string($md5) || preg_match('/\A[a-f0-9]{32}\z/iD', $md5) !== 1)
            || ! is_string($sha256) || preg_match('/\A[a-f0-9]{64}\z/iD', $sha256) !== 1
            || ! is_int($size) || $size < 1) {
            return null;
        }

        $notes = $latest['notes'] ?? [];

        $artifacts = $latest['artifacts'] ?? [];
        if (! is_array($artifacts) || ! array_is_list($artifacts) || count($artifacts) > 64) {
            return null;
        }

        return ['authorized' => true, 'artifact_type' => 'office-application', 'file' => $file,
            'latest_version' => $version, 'latest_build' => $version,
            'download_url' => $url, 'package_path' => null, 'md5' => strtolower($md5),
            'sha256' => strtolower($sha256), 'size' => $size, 'artifacts' => $artifacts,
            'notes' => is_array($notes) ? $notes : (is_string($notes) ? [$notes] : [])];
    }

    /** AWS includes an auxiliary grant only when the existing feature is entitled. */
    public static function browserArtifact(array $feed): ?array
    {
        if (($feed['authorized'] ?? false) !== true || ! is_array($feed['artifacts'] ?? null)
            || ! array_is_list($feed['artifacts']) || count($feed['artifacts']) > 64) {
            return null;
        }

        $found = [];
        foreach ($feed['artifacts'] ?? [] as $item) {
            if (! is_array($item) || ($item['required_feature'] ?? null) !== 'browser_companion') {
                continue;
            }
            if (! is_string($item['file'] ?? null)
                || preg_match('/\Acreditsoft-browser-companion(?:-v\d+(?:\.\d+){1,5})?\.zip\z/D', $item['file']) !== 1
                || ($item['version'] ?? null) !== ($feed['published_latest_version'] ?? $feed['latest_version'] ?? null)
                || ! is_string($item['package_url'] ?? null) || ! self::amazonHttpsUrl($item['package_url'])
                || ! is_int($item['size'] ?? null) || $item['size'] < 1
                || ! is_string($item['md5'] ?? null) || preg_match('/\A[a-f0-9]{32}\z/iD', $item['md5']) !== 1
                || ! is_string($item['sha256'] ?? null) || preg_match('/\A[a-f0-9]{64}\z/iD', $item['sha256']) !== 1) {
                return null;
            }
            $found[] = array_replace($item, ['authorized' => true, 'artifact_type' => 'browser-companion',
                'download_url' => $item['package_url']]);
        }

        return count($found) === 1 ? $found[0] : null;
    }

    /** Reject a renamed browser/SDK ZIP before any application files are copied. */
    public static function archiveMatchesType(string $path, string $kind): bool
    {
        if (! class_exists(\ZipArchive::class) || ! in_array($kind, ['office-application', 'browser-companion'], true)) {
            return false;
        }
        $zip = new \ZipArchive();
        if ($zip->open($path, \ZipArchive::RDONLY) !== true) {
            return false;
        }
        try {
            if ($zip->numFiles < 1 || $zip->numFiles > 100000) {
                return false;
            }
            $names = []; $total = 0;
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $entry = $zip->statIndex($index);
                $name = $entry['name'] ?? '';
                if (! is_string($name) || $name === '' || strlen($name) > 1024 || preg_match('/[\\\\\x00-\x1f\x7f:]/', $name)
                    || str_starts_with($name, '/') || isset($names[$name])) {
                    return false;
                }
                foreach (explode('/', rtrim($name, '/')) as $part) {
                    if (in_array($part, ['', '.', '..'], true)) {
                        return false;
                    }
                }
                $system = $attributes = 0;
                if (! $zip->getExternalAttributesIndex($index, $system, $attributes)
                    || (($attributes >> 16) & 0170000) === 0120000) {
                    return false;
                }
                $total += $entry['size'] ?? 0;
                if ($total > 4 * 1024 * 1024 * 1024) {
                    return false;
                }
                $names[$name] = true;
            }
            if ($kind === 'browser-companion') {
                $raw = $zip->getFromName('manifest.json', 32769);
                if (! is_string($raw) || strlen($raw) > 32768 || ! isset($names['background.js'], $names['options.html'])) {
                    return false;
                }
                $manifest = json_decode($raw, true);

                return is_array($manifest) && ($manifest['manifest_version'] ?? null) === 3
                    && ($manifest['name'] ?? null) === 'CreditSoft Companion Capture';
            }
            foreach (array_keys($names) as $name) {
                if ($name !== 'artisan' && ! str_ends_with($name, '/artisan')) {
                    continue;
                }
                $prefix = substr($name, 0, -strlen('artisan'));
                if (isset($names[$prefix.'composer.json'], $names[$prefix.'routes/web.php'], $names[$prefix.'app/Services/LicenseCheckService.php'])) {
                    return true;
                }
            }

            return false;
        } finally {
            $zip->close();
        }
    }
}
