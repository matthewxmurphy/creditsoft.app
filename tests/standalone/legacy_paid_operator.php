<?php
/**
 * Modern operator method fixture. No Laravel bootstrap or real filesystem facade.
 * Copyright (C) 2026 Matthew X Murphy. https://MatthewxMurphy.com
 * SPDX-License-Identifier: MIT
 */
namespace Illuminate\Console {
    class Command {
        public const SUCCESS = 0;
        public const FAILURE = 1;
        public array $options = [];
        public array $errors = [];
        public array $output = [];
        public function option($name) { return $this->options[$name] ?? null; }
        public function error($message) { $this->errors[] = $message; }
        public function line($message) { $this->output[] = $message; }
        public function info($message) { $this->output[] = $message; }
        public function table($headings, $rows) { $this->output[] = $rows; }
    }
}
namespace Illuminate\Support {
    class Carbon extends \DateTimeImmutable {
        public static int $calls = 0;
        public static function now($timezone = null) {
            self::$calls++;
            return new self('2026-11-18T12:00:00Z');
        }
    }
}
namespace Illuminate\Support\Facades {
    class File {
        public static int $calls = 0;
        public static int $writes = 0;
        public static function exists($path) { self::$calls++; return $GLOBALS['operatorFeedAvailable']; }
        public static function get($path) {
            self::$calls++;
            return json_encode(['latest_version' => '2026.11.18.001', 'notes' => ['Synthetic historical note']]);
        }
        public static function __callStatic($name, $args) {
            self::$writes++;
            throw new \RuntimeException('Unexpected operator filesystem mutation.');
        }
    }
}
namespace {
    $operatorChecks = $operatorAppCalls = $operatorConfigCalls = $operatorBasePathCalls = 0;
    $operatorFeedAvailable = true;
    function operator_need($value) {
        $GLOBALS['operatorChecks']++;
        if (! $value) { throw new \RuntimeException('Owned modern operator assertion stopped.'); }
    }
    function config($name, $default = null) { $GLOBALS['operatorConfigCalls']++; return $default; }
    function base_path($name = '') {
        $GLOBALS['operatorBasePathCalls']++;
        return __DIR__.'/synthetic-source'.($name === '' ? '' : '/'.$name);
    }
    function filled($value) { return $value !== '' && $value !== null; }
    function app($name) { $GLOBALS['operatorAppCalls']++; throw new \RuntimeException('Unexpected application call.'); }
    try {
        foreach (['fsockopen', 'pfsockopen', 'stream_socket_client', 'stream_socket_server', 'socket_create',
            'socket_connect', 'curl_init', 'curl_exec', 'curl_multi_exec', 'mail', 'exec', 'shell_exec',
            'system', 'passthru', 'proc_open', 'popen', 'pcntl_exec', 'pcntl_fork', 'dl', 'ini_set', 'putenv'] as $name) {
            operator_need(! function_exists($name));
        }
        operator_need(! filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOL));
        require __DIR__.'/CreditsoftReleaseVersion.php';
        require __DIR__.'/CreditsoftOfficeUpdatePackage.php';
        require __DIR__.'/CreditsoftPublishRelease.php';
        $builder = new \App\Services\CreditsoftOfficeUpdatePackage;
        foreach ([[], ['package' => true], ['required' => true, 'release' => '2026.11.18.001'],
                  ['dry-run' => false, 'package' => true, 'release' => 'invalid']] as $options) {
            \Illuminate\Support\Facades\File::$calls = 0;
            $command = new \App\Console\Commands\CreditsoftPublishRelease;
            $command->options = $options;
            operator_need($command->handle($builder) === 1);
            operator_need($command->errors === ['Use the Amazon licensed release channel.']);
            operator_need(\Illuminate\Support\Facades\File::$calls === 0);
            operator_need(\Illuminate\Support\Facades\File::$writes === 0 && $operatorAppCalls === 0);
            operator_need($operatorConfigCalls === 0 && $operatorBasePathCalls === 0 && \Illuminate\Support\Carbon::$calls === 0);
        }
        foreach ([[null, null], ['2026.11.18.001', '2026.11.18.001']] as $values) {
            $stopped = false;
            try { $builder->build(...$values); }
            catch (\RuntimeException $e) { $stopped = $e->getMessage() === 'Use the Amazon licensed release channel.'; }
            operator_need($stopped);
            operator_need(\Illuminate\Support\Facades\File::$calls === 0 && \Illuminate\Support\Facades\File::$writes === 0);
            operator_need($operatorConfigCalls === 0 && $operatorBasePathCalls === 0);
        }
        foreach ([['dry-run' => true], ['dry-run' => true, 'package' => true, 'release' => '2026.11.18.002',
                                      'note' => ['Synthetic dry run']]] as $options) {
            $command = new \App\Console\Commands\CreditsoftPublishRelease;
            $command->options = $options;
            operator_need($command->handle($builder) === 0);
            operator_need($command->errors === [] && $command->output[0][1] === ['Next version', '2026.11.18.002']);
            operator_need(\Illuminate\Support\Facades\File::$writes === 0 && $operatorAppCalls === 0);
        }
        foreach (['2026.11.18.001', 'invalid', '2026.11.19.001'] as $version) {
            $command = new \App\Console\Commands\CreditsoftPublishRelease;
            $command->options = ['dry-run' => true, 'release' => $version];
            operator_need($command->handle($builder) === 1 && count($command->errors) === 1);
            operator_need(\Illuminate\Support\Facades\File::$writes === 0 && $operatorAppCalls === 0);
        }
        $reflection = new \ReflectionClass($command);
        operator_need(str_contains($reflection->getProperty('description')->getValue($command), 'Amazon licensed release channel'));
        operator_need(str_contains($reflection->getProperty('signature')->getValue($command), '{--package : Retired;'));
        $operatorFeedAvailable = false;
        operator_need((new \ReflectionMethod($builder, 'releaseVersion'))->invoke($builder) === '2026.4.27.1');
        echo json_encode(['ok' => true, 'scope' => 'modern-legacy-paid-operator-entrypoints', 'checks' => $operatorChecks,
            'paid_command_mutations_denied' => 4, 'disk_builder_calls_denied' => 2, 'readonly_dry_runs_preserved' => 2,
            'modern_calendar_denials_preserved' => 3, 'historical_fallback_unchanged' => true,
            'filesystem_writes' => 0, 'application_calls' => 0, 'network_mail_process_disabled' => true,
            'composer_or_subprocess_invoked' => false]).PHP_EOL;
    } catch (\Throwable $error) {
        echo json_encode(['ok' => false, 'scope' => 'modern-legacy-paid-operator-entrypoints',
            'category' => 'operator-fixture-guard', 'checks' => $operatorChecks]).PHP_EOL;
        exit(1);
    }
}
