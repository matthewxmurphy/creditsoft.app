<?php

namespace App\Services;

use App\Models\CashAppPaymentRequest;
use App\Models\Client;
use App\Models\ClientBillingProfile;
use App\Models\ClientPayment;
use App\Models\OfficeCashAppSetting;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class OfficeCashAppPaymentService
{
    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $setting = $this->setting();
        $requests = CashAppPaymentRequest::query()
            ->with('client')
            ->latest()
            ->limit(40)
            ->get();
        $allRequests = CashAppPaymentRequest::query();
        $configured = $this->customerRequestConfigured($setting);
        $networkConfigured = $this->networkConfigured($setting);

        return [
            'settings' => [
                'enabled' => $setting->enabled,
                'environment' => $setting->environment ?: 'sandbox',
                'api_base_url' => $setting->api_base_url ?: $this->baseUrlForEnvironment((string) $setting->environment),
                'client_id' => $setting->client_id,
                'has_api_key_id' => filled($setting->api_key_id),
                'masked_api_key_id' => filled($setting->api_key_id) ? 'Saved on file' : null,
                'has_api_secret' => filled($setting->api_secret),
                'masked_api_secret' => filled($setting->api_secret) ? 'Saved on file' : null,
                'region' => $setting->region ?: 'PDX',
                'scope_id' => $setting->scope_id,
                'merchant_id' => $setting->merchant_id,
                'redirect_url' => $setting->redirect_url,
                'user_agent' => $setting->user_agent ?: 'CreditSoft Intranet',
                'auto_capture' => $setting->auto_capture,
                'last_checked_at' => optional($setting->last_checked_at)?->toDateTimeString(),
                'last_error' => $setting->last_error,
                'configured' => $configured,
                'network_configured' => $networkConfigured,
                'blocked_reason' => $configured ? null : $this->missingCustomerRequestReason($setting),
            ],
            'stats' => [
                'total_requests' => (clone $allRequests)->count(),
                'pending_count' => (clone $allRequests)->where('status', 'pending')->count(),
                'approved_count' => (clone $allRequests)->whereIn('status', ['approved', 'authorized'])->count(),
                'paid_count' => (clone $allRequests)->whereIn('status', ['paid', 'captured', 'completed'])->count(),
                'failed_count' => (clone $allRequests)->whereIn('status', ['failed', 'declined', 'expired', 'canceled'])->count(),
                'requested_amount' => round((float) (clone $allRequests)->sum('amount'), 2),
            ],
            'requests' => $requests->map(fn (CashAppPaymentRequest $request): array => [
                'id' => $request->getKey(),
                'client_id' => $request->client_id,
                'client_name' => $request->client?->display_name,
                'amount' => (float) $request->amount,
                'currency' => $request->currency,
                'status' => $request->status,
                'cash_app_request_id' => $request->cash_app_request_id,
                'cash_app_payment_id' => $request->cash_app_payment_id,
                'grant_id' => $request->grant_id,
                'reference_id' => $request->reference_id,
                'qr_code_image_url' => $request->qr_code_image_url,
                'qr_code_svg_url' => $request->qr_code_svg_url,
                'mobile_url' => $request->mobile_url,
                'desktop_url' => $request->desktop_url,
                'refreshes_at' => optional($request->refreshes_at)?->toDateTimeString(),
                'expires_at' => optional($request->expires_at)?->toDateTimeString(),
                'approved_at' => optional($request->approved_at)?->toDateTimeString(),
                'paid_at' => optional($request->paid_at)?->toDateTimeString(),
                'last_error' => $request->last_error,
                'created_at' => optional($request->created_at)?->toDateTimeString(),
            ])->values(),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function updateSettings(array $input): OfficeCashAppSetting
    {
        $setting = $this->setting();
        $environment = strtolower($this->clean((string) ($input['environment'] ?? 'sandbox'), 24)) ?: 'sandbox';
        $apiBaseUrl = $this->cleanUrl((string) ($input['api_base_url'] ?? '')) ?: $this->baseUrlForEnvironment($environment);
        $apiKeyId = trim((string) ($input['api_key_id'] ?? ''));
        $apiSecret = trim((string) ($input['api_secret'] ?? ''));

        $setting->fill([
            'enabled' => (bool) ($input['enabled'] ?? false),
            'environment' => $environment,
            'api_base_url' => $apiBaseUrl,
            'client_id' => $this->clean((string) ($input['client_id'] ?? ''), 255) ?: null,
            'region' => strtoupper($this->clean((string) ($input['region'] ?? 'PDX'), 24) ?: 'PDX'),
            'scope_id' => $this->clean((string) ($input['scope_id'] ?? ''), 255) ?: null,
            'merchant_id' => $this->clean((string) ($input['merchant_id'] ?? ''), 255) ?: null,
            'redirect_url' => $this->cleanUrl((string) ($input['redirect_url'] ?? '')) ?: null,
            'user_agent' => $this->clean((string) ($input['user_agent'] ?? 'CreditSoft Intranet'), 255) ?: 'CreditSoft Intranet',
            'auto_capture' => (bool) ($input['auto_capture'] ?? false),
            'last_error' => null,
        ]);

        if ($apiKeyId !== '') {
            $setting->api_key_id = $apiKeyId;
        }

        if ($apiSecret !== '') {
            $setting->api_secret = $apiSecret;
        }

        $setting->save();

        return $setting;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function createCustomerRequest(array $input): array
    {
        $setting = $this->setting();

        if (! $this->customerRequestConfigured($setting)) {
            return $this->finishSettingCheck($setting, [
                'success' => false,
                'error' => $this->missingCustomerRequestReason($setting),
            ]);
        }

        $amount = round((float) ($input['amount'] ?? 0), 2);

        if ($amount <= 0) {
            return $this->finishSettingCheck($setting, [
                'success' => false,
                'error' => 'Enter a Cash App amount greater than zero.',
            ]);
        }

        $client = ! empty($input['client_id'])
            ? Client::query()->find((int) $input['client_id'])
            : null;
        $currency = strtoupper($this->clean((string) ($input['currency'] ?? 'USD'), 3) ?: 'USD');
        $idempotencyKey = (string) Str::uuid();
        $referenceId = 'cs-cashapp-'.Str::lower(Str::random(24));
        $redirectUrl = $this->cleanUrl((string) ($input['redirect_url'] ?? '')) ?: $setting->redirect_url;
        $scopeId = (string) $setting->scope_id;
        $request = CashAppPaymentRequest::query()->create([
            'office_cash_app_setting_id' => $setting->getKey(),
            'client_id' => $client?->getKey(),
            'idempotency_key' => $idempotencyKey,
            'reference_id' => $referenceId,
            'status' => 'pending',
            'amount' => $amount,
            'currency' => $currency,
            'action_type' => 'ONE_TIME_PAYMENT',
            'channel' => 'ONLINE',
            'scope_id' => $scopeId,
            'merchant_id' => $setting->merchant_id,
            'redirect_url' => $redirectUrl,
        ]);
        $payload = [
            'idempotency_key' => $idempotencyKey,
            'request' => [
                'actions' => [[
                    'amount' => $this->amountToCents($amount),
                    'currency' => $currency,
                    'scope_id' => $scopeId,
                    'type' => 'ONE_TIME_PAYMENT',
                ]],
                'channel' => 'ONLINE',
                'reference_id' => $referenceId,
                'metadata' => array_filter([
                    'creditsoft_request_id' => (string) $request->getKey(),
                    'creditsoft_client_id' => $client ? (string) $client->getKey() : null,
                ]),
                'customer_metadata' => array_filter([
                    'reference_id' => $client ? 'client-'.$client->getKey() : $referenceId,
                ]),
            ],
        ];

        if ($redirectUrl) {
            $payload['request']['redirect_url'] = $redirectUrl;
        }

        try {
            $response = Http::timeout(20)
                ->withHeaders($this->customerRequestHeaders($setting))
                ->post($this->endpoint($setting, '/customer-request/v1/requests'), $payload);

            if (! $response->successful()) {
                $error = $this->responseError($response->json(), 'Cash App customer request failed with HTTP '.$response->status().'.');
                $request->forceFill([
                    'status' => 'failed',
                    'last_error' => $error,
                    'raw_response' => $response->json(),
                ])->save();

                return $this->finishSettingCheck($setting, [
                    'success' => false,
                    'error' => $error,
                ]);
            }

            $request = $this->applyCustomerRequestResponse($request, $response->json());

            return $this->finishSettingCheck($setting, [
                'success' => true,
                'request_id' => $request->getKey(),
                'cash_app_request_id' => $request->cash_app_request_id,
                'status' => $request->status,
            ]);
        } catch (RequestException $exception) {
            return $this->markRequestFailed($setting, $request, $exception->getMessage());
        } catch (\Throwable $exception) {
            return $this->markRequestFailed($setting, $request, $exception->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function syncCustomerRequest(CashAppPaymentRequest $request): array
    {
        $setting = $this->setting();

        if (! $this->customerRequestConfigured($setting)) {
            return $this->finishSettingCheck($setting, [
                'success' => false,
                'error' => $this->missingCustomerRequestReason($setting),
            ]);
        }

        if (blank($request->cash_app_request_id)) {
            return $this->finishSettingCheck($setting, [
                'success' => false,
                'error' => 'Cash App has not returned a request id for this record yet.',
            ]);
        }

        try {
            $response = Http::timeout(20)
                ->withHeaders($this->customerRequestHeaders($setting))
                ->get($this->endpoint($setting, '/customer-request/v1/requests/'.$request->cash_app_request_id));

            if (! $response->successful()) {
                $error = $this->responseError($response->json(), 'Cash App retrieve request failed with HTTP '.$response->status().'.');
                $request->forceFill(['last_error' => $error, 'raw_response' => $response->json()])->save();

                return $this->finishSettingCheck($setting, [
                    'success' => false,
                    'error' => $error,
                ]);
            }

            $request = $this->applyCustomerRequestResponse($request, $response->json());

            if ($setting->auto_capture && $request->grant_id && $this->networkConfigured($setting) && ! $request->cash_app_payment_id) {
                $this->createNetworkPayment($setting, $request);
            }

            return $this->finishSettingCheck($setting, [
                'success' => true,
                'request_id' => $request->getKey(),
                'status' => $request->status,
            ]);
        } catch (\Throwable $exception) {
            $request->forceFill(['last_error' => $exception->getMessage()])->save();

            return $this->finishSettingCheck($setting, [
                'success' => false,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    protected function createNetworkPayment(OfficeCashAppSetting $setting, CashAppPaymentRequest $request): CashAppPaymentRequest
    {
        $idempotencyKey = (string) Str::uuid();
        $body = [
            'idempotency_key' => $idempotencyKey,
            'payment' => [
                'amount' => $this->amountToCents((float) $request->amount),
                'currency' => $request->currency,
                'merchant_id' => $request->merchant_id ?: $setting->merchant_id,
                'grant_id' => $request->grant_id,
                'reference_id' => $request->reference_id,
                'capture' => true,
            ],
        ];
        $path = '/network/v1/payments';
        $response = Http::timeout(20)
            ->withHeaders($this->networkHeaders($setting, 'POST', $path, $body))
            ->post($this->endpoint($setting, $path), $body);

        if (! $response->successful()) {
            $request->forceFill([
                'last_error' => $this->responseError($response->json(), 'Cash App payment creation failed with HTTP '.$response->status().'.'),
                'raw_response' => $response->json(),
            ])->save();

            return $request;
        }

        $payment = (array) data_get($response->json(), 'payment', []);
        $paymentStatus = $this->normalizeStatus((string) ($payment['status'] ?? 'authorized'));
        $clientPayment = $this->recordClientPayment($request, (string) ($payment['id'] ?? ''), $paymentStatus);

        $request->forceFill([
            'client_payment_id' => $clientPayment?->getKey(),
            'cash_app_payment_id' => $this->clean((string) ($payment['id'] ?? ''), 255) ?: null,
            'status' => $paymentStatus,
            'paid_at' => in_array($paymentStatus, ['paid', 'captured', 'completed', 'authorized'], true) ? now() : $request->paid_at,
            'raw_response' => $response->json(),
            'last_error' => null,
        ])->save();

        return $request;
    }

    protected function recordClientPayment(CashAppPaymentRequest $request, string $paymentId, string $status): ?ClientPayment
    {
        if (! $request->client_id || ! in_array($status, ['paid', 'captured', 'completed', 'authorized'], true)) {
            return null;
        }

        $profile = ClientBillingProfile::query()->where('client_id', $request->client_id)->first();
        $payment = ClientPayment::query()
            ->where('gateway_name', 'Cash App Pay')
            ->where('gateway_transaction_id', $paymentId !== '' ? $paymentId : $request->cash_app_request_id)
            ->first();

        if (! $payment) {
            $payment = ClientPayment::query()->create([
                'client_id' => $request->client_id,
                'client_billing_profile_id' => $profile?->getKey(),
                'amount' => $request->amount,
                'currency' => $request->currency,
                'status' => 'paid',
                'paid_at' => now(),
                'gateway_name' => 'Cash App Pay',
                'gateway_transaction_id' => $paymentId !== '' ? $paymentId : $request->cash_app_request_id,
                'reference' => 'Cash App Pay request '.$request->reference_id,
                'notes' => 'Imported from the Cash App Pay API.',
                'metadata' => [
                    'source' => 'cash_app_pay_api',
                    'cash_app_request_id' => $request->cash_app_request_id,
                    'cash_app_payment_id' => $paymentId,
                ],
            ]);
        }

        if ($profile) {
            $profile->last_paid_at = $payment->paid_at ?? now();
            $profile->next_due_at = $this->nextDueAt($profile, $profile->last_paid_at);
            $profile->save();
        }

        return $payment;
    }

    protected function setting(): OfficeCashAppSetting
    {
        return OfficeCashAppSetting::query()->firstOrCreate([], [
            'enabled' => false,
            'environment' => 'sandbox',
            'api_base_url' => 'https://sandbox.api.cash.app',
            'region' => 'PDX',
            'user_agent' => 'CreditSoft Intranet',
            'auto_capture' => false,
        ]);
    }

    protected function applyCustomerRequestResponse(CashAppPaymentRequest $record, array $payload): CashAppPaymentRequest
    {
        $request = (array) data_get($payload, 'request', []);
        $triggers = (array) data_get($request, 'auth_flow_triggers', []);
        $status = $this->normalizeStatus((string) ($request['status'] ?? $record->status));
        $grantId = $this->extractGrantId($request);

        $record->forceFill([
            'cash_app_request_id' => $this->clean((string) ($request['id'] ?? $record->cash_app_request_id), 255) ?: $record->cash_app_request_id,
            'grant_id' => $grantId ?: $record->grant_id,
            'status' => $status,
            'qr_code_image_url' => $this->cleanUrl((string) ($triggers['qr_code_image_url'] ?? '')) ?: $record->qr_code_image_url,
            'qr_code_svg_url' => $this->cleanUrl((string) ($triggers['qr_code_svg_url'] ?? '')) ?: $record->qr_code_svg_url,
            'mobile_url' => $this->cleanUrl((string) ($triggers['mobile_url'] ?? '')) ?: $record->mobile_url,
            'desktop_url' => $this->cleanUrl((string) ($triggers['desktop_url'] ?? '')) ?: $record->desktop_url,
            'refreshes_at' => $this->parseDate($triggers['refreshes_at'] ?? null),
            'expires_at' => $this->parseDate($request['expires_at'] ?? null),
            'approved_at' => in_array($status, ['approved', 'authorized'], true) ? now() : $record->approved_at,
            'raw_response' => $payload,
            'last_error' => null,
        ])->save();

        return $record;
    }

    protected function customerRequestConfigured(OfficeCashAppSetting $setting): bool
    {
        return $setting->enabled
            && filled($setting->client_id)
            && filled($setting->scope_id)
            && filled($setting->api_base_url);
    }

    protected function networkConfigured(OfficeCashAppSetting $setting): bool
    {
        return $this->customerRequestConfigured($setting)
            && filled($setting->api_key_id)
            && filled($setting->api_secret)
            && filled($setting->merchant_id)
            && filled($setting->region);
    }

    protected function missingCustomerRequestReason(OfficeCashAppSetting $setting): string
    {
        if (! $setting->enabled) {
            return 'Turn on Cash App Pay API before creating requests.';
        }

        $missing = [];

        foreach ([
            'client_id' => 'Client ID',
            'scope_id' => 'Scope ID',
            'api_base_url' => 'API base URL',
        ] as $field => $label) {
            if (blank($setting->{$field})) {
                $missing[] = $label;
            }
        }

        return $missing === []
            ? 'Cash App Pay API is not configured.'
            : 'Save Cash App Pay '.$this->humanList($missing).' before creating requests.';
    }

    /**
     * @return array<string, string>
     */
    protected function customerRequestHeaders(OfficeCashAppSetting $setting): array
    {
        return [
            'Accept' => 'application/json',
            'Authorization' => 'Client '.$setting->client_id,
            'Content-Type' => 'application/json',
            'User-Agent' => $setting->user_agent ?: 'CreditSoft Intranet',
        ];
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, string>
     */
    protected function networkHeaders(OfficeCashAppSetting $setting, string $method, string $path, array $body = []): array
    {
        $bodyJson = $body === [] ? '' : (json_encode($body, JSON_UNESCAPED_SLASHES) ?: '');
        $base = parse_url((string) $setting->api_base_url);
        $host = strtolower((string) ($base['host'] ?? 'api.cash.app'));
        $authorization = 'Client '.$setting->client_id.' '.$setting->api_key_id;
        $headersToSign = [
            'accept' => 'application/json',
            'authorization' => $authorization,
            'content-type' => 'application/json',
            'host' => $host,
        ];
        $headersString = collect($headersToSign)
            ->map(fn (string $value, string $name): string => $name.':'.trim($value))
            ->implode("\n");
        $digest = hash('sha256', $bodyJson);
        $signature = $setting->environment === 'sandbox' && blank($setting->api_secret)
            ? 'sandbox:skip-signature-check'
            : 'V1 '.hash_hmac('sha256', strtoupper($method)."\n".$path."\n".$headersString."\n".$digest, (string) $setting->api_secret);

        return [
            'Accept' => 'application/json',
            'Authorization' => $authorization,
            'Content-Type' => 'application/json',
            'User-Agent' => $setting->user_agent ?: 'CreditSoft Intranet',
            'X-Region' => $setting->region ?: 'PDX',
            'X-Signature' => $signature,
        ];
    }

    protected function endpoint(OfficeCashAppSetting $setting, string $path): string
    {
        return rtrim((string) $setting->api_base_url, '/').'/'.ltrim($path, '/');
    }

    protected function baseUrlForEnvironment(string $environment): string
    {
        return strtolower($environment) === 'production'
            ? 'https://api.cash.app'
            : 'https://sandbox.api.cash.app';
    }

    protected function amountToCents(float $amount): int
    {
        return (int) round($amount * 100);
    }

    protected function normalizeStatus(string $status): string
    {
        $status = strtolower(trim($status));

        return match ($status) {
            'authorized' => 'authorized',
            'approved' => 'approved',
            'captured', 'complete', 'completed', 'paid' => 'paid',
            'declined' => 'declined',
            'expired' => 'expired',
            'canceled', 'cancelled' => 'canceled',
            'failed' => 'failed',
            default => $status !== '' ? $status : 'pending',
        };
    }

    protected function extractGrantId(array $request): string
    {
        foreach ([
            data_get($request, 'grants.0.id'),
            data_get($request, 'grants.0.grant_id'),
            data_get($request, 'grant.id'),
            data_get($request, 'grant_id'),
        ] as $candidate) {
            $candidate = $this->clean((string) $candidate, 255);

            if ($candidate !== '') {
                return $candidate;
            }
        }

        return '';
    }

    protected function responseError(mixed $payload, string $fallback): string
    {
        if (! is_array($payload)) {
            return $fallback;
        }

        foreach ([
            data_get($payload, 'errors.0.detail'),
            data_get($payload, 'errors.0.code'),
            data_get($payload, 'error.message'),
            data_get($payload, 'message'),
        ] as $message) {
            $message = trim((string) $message);

            if ($message !== '') {
                return $message;
            }
        }

        return $fallback;
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    protected function finishSettingCheck(OfficeCashAppSetting $setting, array $result): array
    {
        $setting->last_checked_at = now();
        $setting->last_error = empty($result['success']) ? (string) ($result['error'] ?? 'Unknown Cash App Pay API error.') : null;
        $setting->save();

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    protected function markRequestFailed(OfficeCashAppSetting $setting, CashAppPaymentRequest $request, string $error): array
    {
        $request->forceFill([
            'status' => 'failed',
            'last_error' => $error,
        ])->save();

        return $this->finishSettingCheck($setting, [
            'success' => false,
            'error' => $error,
        ]);
    }

    protected function parseDate(mixed $value): ?CarbonInterface
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }

    protected function nextDueAt(ClientBillingProfile $profile, CarbonInterface $paidAt): ?CarbonInterface
    {
        return match ($profile->billing_interval) {
            'weekly' => $paidAt->copy()->addWeek(),
            'monthly' => $paidAt->copy()->addMonth(),
            'annual' => $paidAt->copy()->addYear(),
            default => null,
        };
    }

    protected function cleanUrl(string $value): string
    {
        $value = trim($value);

        return filter_var($value, FILTER_VALIDATE_URL) ? mb_substr($value, 0, 2048) : '';
    }

    protected function clean(string $value, int $max = 255): string
    {
        $value = trim(preg_replace('/\s+/', ' ', $value) ?: '');

        return $value === '' ? '' : mb_substr($value, 0, $max);
    }

    /**
     * @param  array<int, string>  $items
     */
    protected function humanList(array $items): string
    {
        if (count($items) <= 1) {
            return $items[0] ?? '';
        }

        $last = array_pop($items);

        return implode(', ', $items).' and '.$last;
    }
}
