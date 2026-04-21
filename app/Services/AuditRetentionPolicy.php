<?php

namespace App\Services;

class AuditRetentionPolicy
{
    public function effectiveDays(): int
    {
        $config = config('creditsoft.audit', []);
        $baseDays = max((int) ($config['retention_days'] ?? 30), 1);
        $minimumDays = max((int) ($config['minimum_retention_days'] ?? 7), 1);
        $maximumDays = max((int) ($config['maximum_retention_days'] ?? $baseDays), $baseDays);

        $metrics = $this->diskMetrics();

        if ($metrics === null) {
            return $baseDays;
        }

        $freeGb = $metrics['free_gb'];
        $freePercent = $metrics['free_percent'];

        $criticalGb = (float) ($config['disk_free_critical_gb'] ?? 20);
        $warningGb = (float) ($config['disk_free_warning_gb'] ?? 50);
        $criticalPercent = (float) ($config['disk_percent_critical'] ?? 10);
        $warningPercent = (float) ($config['disk_percent_warning'] ?? 20);

        if ($freeGb <= $criticalGb || $freePercent <= $criticalPercent) {
            return $minimumDays;
        }

        if ($freeGb <= $warningGb || $freePercent <= $warningPercent) {
            return max($minimumDays, min($baseDays, 14));
        }

        if ($freeGb >= 200 && $freePercent >= 40) {
            return $maximumDays;
        }

        return $baseDays;
    }

    /**
     * @return array{free_gb:float,free_percent:float}|null
     */
    protected function diskMetrics(): ?array
    {
        $path = (string) config('creditsoft.browser_capture_path', storage_path('app/private/browser-captures'));
        $target = is_dir($path) ? $path : dirname($path);

        $freeBytes = @disk_free_space($target);
        $totalBytes = @disk_total_space($target);

        if (! is_numeric($freeBytes) || ! is_numeric($totalBytes) || $totalBytes <= 0) {
            return null;
        }

        return [
            'free_gb' => round(((float) $freeBytes) / 1073741824, 2),
            'free_percent' => round((((float) $freeBytes) / ((float) $totalBytes)) * 100, 2),
        ];
    }
}
