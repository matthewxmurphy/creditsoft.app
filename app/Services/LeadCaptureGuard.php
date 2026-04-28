<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class LeadCaptureGuard
{
    /**
     * @return array{ok:bool,domain:?string,error:?string}
     */
    public function emailDomainAcceptsMail(?string $email): array
    {
        $email = Str::lower(trim((string) $email));

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [
                'ok' => false,
                'domain' => null,
                'error' => 'Enter a valid email address.',
            ];
        }

        $domain = substr((string) strrchr($email, '@'), 1);

        if ($domain === '') {
            return [
                'ok' => false,
                'domain' => null,
                'error' => 'Enter a valid email address.',
            ];
        }

        if (function_exists('idn_to_ascii')) {
            $asciiDomain = idn_to_ascii($domain, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
            $domain = is_string($asciiDomain) && $asciiDomain !== '' ? $asciiDomain : $domain;
        }

        $hasMx = function_exists('checkdnsrr') && checkdnsrr($domain, 'MX');

        if (! $hasMx) {
            return [
                'ok' => false,
                'domain' => $domain,
                'error' => 'That email domain does not publish mail server DNS yet.',
            ];
        }

        return [
            'ok' => true,
            'domain' => $domain,
            'error' => null,
        ];
    }

    /**
     * @return array{ok:bool,required:bool,skipped:bool,error:?string}
     */
    public function verifyTurnstile(?string $token, ?string $remoteIp = null, bool $required = true): array
    {
        $secret = trim((string) config('creditsoft.lead_capture.turnstile_secret', ''));
        $token = trim((string) $token);

        if ($secret === '') {
            return [
                'ok' => ! $required,
                'required' => $required,
                'skipped' => true,
                'error' => $required ? 'Turnstile is not configured for this lead form.' : null,
            ];
        }

        if ($token === '') {
            return [
                'ok' => false,
                'required' => true,
                'skipped' => false,
                'error' => 'Complete the browser check before submitting the lead.',
            ];
        }

        try {
            $response = Http::asForm()
                ->timeout(5)
                ->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', array_filter([
                    'secret' => $secret,
                    'response' => $token,
                    'remoteip' => $remoteIp,
                ]));
        } catch (ConnectionException) {
            return [
                'ok' => false,
                'required' => true,
                'skipped' => false,
                'error' => 'The browser check could not be verified right now.',
            ];
        }

        if (! $response->ok() || ! (bool) $response->json('success')) {
            return [
                'ok' => false,
                'required' => true,
                'skipped' => false,
                'error' => 'The browser check did not pass. Refresh and try again.',
            ];
        }

        return [
            'ok' => true,
            'required' => true,
            'skipped' => false,
            'error' => null,
        ];
    }
}
