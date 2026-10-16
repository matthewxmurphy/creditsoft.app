<?php

/** Copyright 2026 Matthew X Murphy, https://MatthewxMurphy.com; https://builtnotbegged.com. */

namespace Tests\Feature;

use App\Http\Middleware\AllowLocalAuthBypass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LocalAuthBypassSecurityTest extends TestCase
{
    use RefreshDatabase;

    public static function requestCases(): array
    {
        return [
            'remote Host localhost' => ['local', '203.0.113.10', [], false],
            'remote forwarded localhost' => ['local', '203.0.113.10', ['X-Forwarded-Host' => 'localhost'], false],
            'loopback proxy forwarded host' => ['local', '127.0.0.1', ['X-Forwarded-Host' => 'localhost'], false],
            'loopback proxy forwarded address' => ['local', '127.0.0.1', ['X-Forwarded-For' => '203.0.113.10'], false],
            'loopback proxy scheme only' => ['local', '127.0.0.1', ['X-Forwarded-Proto' => 'https'], false],
            'loopback proxy port only' => ['local', '127.0.0.1', ['X-Forwarded-Port' => '443'], false],
            'loopback custom forwarding' => ['local', '127.0.0.1', ['X-Forwarded-Custom' => 'proxy'], false],
            'loopback standardized forwarding' => ['local', '127.0.0.1', ['Forwarded' => 'for=203.0.113.10;host=localhost'], false],
            'loopback production' => ['production', '127.0.0.1', [], false],
            'local explicit opt-in' => ['local', '127.0.0.1', [], true],
            'IPv6 explicit opt-in' => ['local', '::1', [], true],
        ];
    }

    #[DataProvider('requestCases')]
    public function test_host_headers_never_establish_a_local_owner_identity(string $environment, string $peer, array $headers, bool $expected): void
    {
        $owner = User::factory()->create(['email' => 'owner@example.invalid']);
        $this->app['env'] = $environment;
        config(['creditsoft.access.local_auth_bypass.enabled' => true,
            'creditsoft.access.local_auth_bypass.email' => $owner->email,
            'creditsoft.access.local_auth_bypass.allowed_hosts' => ['localhost']]);
        Route::get('/__local-bypass-fixture', fn () => response()->json(['authenticated' => auth()->check()]))
            ->middleware(AllowLocalAuthBypass::class);
        $this->withServerVariables(['REMOTE_ADDR' => $peer])
            ->get('/__local-bypass-fixture', ['Host' => 'localhost', ...$headers])
            ->assertOk()->assertJson(['authenticated' => $expected]);
    }
}
