<?php

// Copyright (c) 2026 Matthew X Murphy. https://MatthewxMurphy.com
// Product/support: https://builtnotbegged.com
// SPDX-License-Identifier: MIT

namespace App\Support;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use RuntimeException;

/** Prospective release formatting; historical artifacts retain their identities. */
final class CreditsoftReleaseVersion
{
    public const TIMEZONE = 'America/Chicago';

    public static function next(string $previous, DateTimeInterface $now): string
    {
        $date = DateTimeImmutable::createFromInterface($now)->setTimezone(new DateTimeZone(self::TIMEZONE));
        $today = $date->format('Y.n.d');
        $counter = 1;
        $parts = self::historicalParts($previous);

        if ($parts !== null) {
            $previousDate = sprintf('%04d.%d.%02d', $parts[0], $parts[1], $parts[2]);
            if (self::dateKey($parts) > $date->format('Ymd')) {
                throw new RuntimeException('Previous release date is ahead of Chicago today; review the stamp and use an explicit --release with --required only for an intended correction.');
            }
            if ($previousDate === $today) {
                $counter = $parts[3] + 1;
            }
        } elseif ($previous !== '' && preg_match('/^\d+\.\d+\.\d+$/D', $previous) !== 1) {
            throw new RuntimeException('Previous release version is neither a valid historical calendar version nor a legacy three-part version.');
        }

        if ($counter > 999) {
            throw new RuntimeException('Daily release counter 999 is exhausted; do not wrap or alter historical releases.');
        }

        return $today.'.'.sprintf('%03d', $counter);
    }

    public static function isCanonical(string $version): bool
    {
        if (preg_match('/^([1-9]\d{3})\.([1-9]|1[0-2])\.(0[1-9]|[12]\d|3[01])\.(00[1-9]|0[1-9]\d|[1-9]\d{2})$/D', $version, $m) !== 1) {
            return false;
        }

        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
    }

    public static function validateForPublish(string $version, string $previous, DateTimeInterface $now, bool $required): void
    {
        if (! self::isCanonical($version)) {
            throw new RuntimeException('Future CreditSoft release versions must use a valid YYYY.M.DD.XXX date and counter 001–999.');
        }
        $date = DateTimeImmutable::createFromInterface($now)->setTimezone(new DateTimeZone(self::TIMEZONE));
        $parts = array_map('intval', explode('.', $version));
        if (self::dateKey($parts) !== $date->format('Ymd')) {
            throw new RuntimeException('The release date must be today in America/Chicago.');
        }
        $old = self::historicalParts($previous);
        if ($previous !== '' && $old === null && preg_match('/^\d+\.\d+\.\d+$/D', $previous) !== 1 && ! $required) {
            throw new RuntimeException('Unknown previous version requires an explicit --required correction after operator review.');
        }
        if ($old !== null && (self::compare($parts, $old) === 0 || (self::compare($parts, $old) < 0 && ! $required))) {
            throw new RuntimeException('Release must advance without reusing the previous identity; --required permits only a lower bad-stamp correction.');
        }
    }

    public static function chrome(string $version): string
    {
        return implode('.', self::canonicalParts($version));
    }

    public static function npm(string $version): string
    {
        [$year, $month, $day, $counter] = self::canonicalParts($version);

        return $year.'.'.$month.'.'.($day * 1000 + $counter);
    }

    /** @return array<int, int> */
    private static function canonicalParts(string $version): array
    {
        if (! self::isCanonical($version)) {
            throw new RuntimeException('Cannot adapt a noncanonical future release version.');
        }

        return array_map('intval', explode('.', $version));
    }

    /** @return array<int, int>|null */
    private static function historicalParts(string $version): ?array
    {
        if (preg_match('/^([1-9]\d{3})\.(\d{1,2})\.(\d{1,2})\.(\d{1,3})$/D', $version, $m) !== 1) {
            return null;
        }
        $parts = array_map('intval', array_slice($m, 1));

        return checkdate($parts[1], $parts[2], $parts[0]) && $parts[3] >= 1 && $parts[3] <= 999 ? $parts : null;
    }

    /** @param array<int, int> $parts */
    private static function dateKey(array $parts): string
    {
        return sprintf('%04d%02d%02d', $parts[0], $parts[1], $parts[2]);
    }

    /** @param array<int, int> $left @param array<int, int> $right */
    private static function compare(array $left, array $right): int
    {
        for ($i = 0; $i < 4; $i++) {
            if ($left[$i] !== $right[$i]) {
                return $left[$i] <=> $right[$i];
            }
        }

        return 0;
    }
}
