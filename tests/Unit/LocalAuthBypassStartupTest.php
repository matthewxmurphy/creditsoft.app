<?php

/**
 * Copyright 2026 Matthew X Murphy, https://MatthewxMurphy.com; https://builtnotbegged.com.
 * SPDX-License-Identifier: MIT
 */

namespace Tests\Unit;

use App\Providers\AppServiceProvider;
use App\Services\EnvironmentEditor;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Foundation\Application;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class LocalAuthBypassStartupTest extends TestCase
{
    public static function unsafeEnvironments(): array
    {
        return [
            'production' => ['production'],
            'staging' => ['staging'],
            'testing' => ['testing'],
            'unset' => [''],
            'case variant is not explicit local' => ['LOCAL'],
        ];
    }

    #[DataProvider('unsafeEnvironments')]
    public function test_enabled_bypass_rejects_non_local_startup_before_runtime_reads(string $environment): void
    {
        // No application kernel, environment file, provider discovery, database or real service is loaded.
        $previous = Container::getInstance();
        $app = new Application;
        $app->instance('env', $environment);
        $app->instance('config', new Repository([
            'creditsoft' => ['access' => ['local_auth_bypass' => ['enabled' => true]]],
        ]));
        $app->bind(EnvironmentEditor::class, function (): never {
            self::fail('Unsafe startup reached runtime environment synchronization.');
        });

        try {
            $this->expectException(LogicException::class);
            $this->expectExceptionMessage('Local authentication bypass must be disabled unless APP_ENV=local.');
            (new AppServiceProvider($app))->boot();
        } finally {
            Container::setInstance($previous);
        }
    }
}
