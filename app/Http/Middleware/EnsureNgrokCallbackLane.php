<?php

namespace App\Http\Middleware;

use App\Services\CreditsoftApiAccess;
use App\Services\NgrokConfigService;
use App\Services\NgrokTunnelService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class EnsureNgrokCallbackLane
{
    public function __construct(
        protected CreditsoftApiAccess $apiAccess,
        protected NgrokConfigService $ngrokConfig,
        protected NgrokTunnelService $ngrokTunnel,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->ensureRunning($request);

        return $next($request);
    }

    protected function ensureRunning(Request $request): void
    {
        if (! $this->apiAccess->ngrokEnabled()) {
            return;
        }

        $cacheKey = 'creditsoft.ngrok_callback_lane.last_ensure_attempt';
        $lastAttempt = (int) Cache::get($cacheKey, 0);

        if (time() - $lastAttempt < 20) {
            return;
        }

        Cache::put($cacheKey, time(), now()->addSeconds(30));

        $status = $this->ngrokConfig->current();

        if (
            ($status['running'] ?? false)
            || ! ($status['host_authtoken_saved'] ?? false)
            || ! ($status['validated'] ?? false)
        ) {
            return;
        }

        try {
            $this->ngrokTunnel->ensureRunning(
                $request->getPort(),
                (string) ($status['config_path'] ?? ''),
            );
        } catch (Throwable $exception) {
            Log::warning('CreditSoft ngrok callback lane could not be started during request.', [
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
