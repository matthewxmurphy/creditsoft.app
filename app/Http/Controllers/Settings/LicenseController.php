<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\CreditsoftUpdateFeed;
use App\Services\InstallerState;
use App\Services\LicenseCheckService;
use App\Services\LicenseStateService;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class LicenseController extends Controller
{
    public function edit(
        Request $request,
        InstallerState $installerState,
        LicenseStateService $licenseState,
        CreditsoftUpdateFeed $updateFeed,
    ): View {
        $state = $installerState->read();
        $current = $licenseState->current();

        $updates = $updateFeed->current();

        return view('settings-license', [
            'license' => $current,
            'savedLicenseKey' => filled((string) ($state['license_key'] ?? '')),
            'renewal' => $this->renewalPayload($current, $state),
            'updates' => $updates,
            'changelog' => $this->versionedChangelog($updates),
            'officeProfile' => [
                'company_name' => $state['company_name'] ?? config('app.name', 'CreditSoft'),
                'admin_email' => $state['admin_email'] ?? $request->user()?->email,
                'tailscale_hostname' => $state['tailscale_hostname'] ?? config('creditsoft.tailscale_hostname'),
            ],
        ]);
    }

    public function update(
        Request $request,
        InstallerState $installerState,
        LicenseCheckService $licenseCheckService,
    ): RedirectResponse {
        $validated = $request->validate([
            'license_key' => ['nullable', 'string', 'max:255'],
        ]);

        $state = $installerState->read();
        $resolvedKey = Str::upper(trim((string) ($validated['license_key'] ?? '')));

        if ($resolvedKey === '') {
            $resolvedKey = Str::upper(trim((string) ($state['license_key'] ?? '')));
        }

        if ($resolvedKey === '') {
            return back()
                ->withErrors(['license_key' => 'Enter the office license key or save one before re-checking.'])
                ->withInput();
        }

        $companyName = (string) ($state['company_name'] ?? config('app.name', 'CreditSoft'));
        $adminEmail = (string) ($state['admin_email'] ?? $request->user()?->email ?? '');
        $tailscaleHostname = (string) ($state['tailscale_hostname'] ?? config('creditsoft.tailscale_hostname', 'creditsoft-intranet'));

        $license = $licenseCheckService->check($resolvedKey, [
            'company_name' => $companyName,
            'admin_email' => $adminEmail,
            'tailscale_hostname' => $tailscaleHostname,
        ]);

        $previousLicense = (array) ($state['license'] ?? []);
        $license['last_verified_at'] = ($license['mode'] ?? null) === 'remote'
            ? ($license['last_verified_at'] ?? now()->toIso8601String())
            : ($previousLicense['last_verified_at'] ?? null);

        if (($license['remote_unreachable'] ?? false) === true) {
            $license['verification_fail_started_at'] = $previousLicense['verification_fail_started_at']
                ?? now()->toIso8601String();
        } else {
            $license['verification_fail_started_at'] = null;
        }

        $installerState->merge([
            'company_name' => $companyName,
            'admin_email' => $adminEmail,
            'tailscale_hostname' => $tailscaleHostname,
            'license_key' => $resolvedKey,
            'license' => $license,
        ]);

        $request->session()->flash('toast', [
            'type' => ($license['valid'] ?? false) ? 'success' : 'warning',
            'message' => (string) ($license['message'] ?? 'License status updated.'),
        ]);

        return redirect()->route('settings.license');
    }

    /**
     * @param  array<string, mixed>  $updates
     * @return array{total:int,versions:list<array{version:string,notes:list<string>}>}
     */
    protected function versionedChangelog(array $updates): array
    {
        $notes = array_values(array_filter(array_map('strval', (array) ($updates['notes'] ?? []))));
        $versions = [];
        $currentIndex = -1;
        $fallbackVersion = trim((string) ($updates['latest_version'] ?? 'Latest'));

        foreach ($notes as $note) {
            if (preg_match('/^CreditSoft\s+([0-9]+(?:\.[0-9A-Za-z-]+)+)\s+(.+)$/i', $note, $match) === 1) {
                $versions[] = [
                    'version' => $match[1],
                    'notes' => [ucfirst(trim($match[2]))],
                ];
                $currentIndex = array_key_last($versions);

                continue;
            }

            if ($currentIndex < 0) {
                $versions[] = [
                    'version' => $fallbackVersion !== '' ? $fallbackVersion : 'Latest',
                    'notes' => [],
                ];
                $currentIndex = 0;
            }

            $versions[$currentIndex]['notes'][] = $note;
        }

        return [
            'total' => count($notes),
            'versions' => array_values(array_filter(
                $versions,
                fn (array $version) => count($version['notes']) > 0,
            )),
        ];
    }

    /**
     * @param  array<string, mixed>  $license
     * @return array<string, mixed>
     */
    protected function renewalPayload(array $license, array $state = []): array
    {
        $payeeName = trim((string) config('creditsoft.licensing.renewal.payee_name', 'Matthew Murphy'));
        $zelleContact = trim((string) config('creditsoft.licensing.renewal.zelle_contact', 'z@creditsoft.app'));
        $bankName = trim((string) config('creditsoft.licensing.renewal.bank_name', 'Chase'));
        $expectedSubject = trim((string) config('creditsoft.licensing.renewal.expected_subject', 'You received money with Zelle®'));
        $dedicatedMailboxNote = trim((string) config('creditsoft.licensing.renewal.dedicated_mailbox_note', 'Use a dedicated inbox for Zelle/Chase payment notices only. Do not route Supabase, support, app alerts, or general office mail into this mailbox.'));
        $zelleAddressNote = trim((string) config('creditsoft.licensing.renewal.zelle_address_note', 'Some banks/Zelle reject addresses like zelle@yourdomain.com. Use the bank-approved Zelle email shown on the QR, such as z@creditsoft.app or another dedicated address the bank accepts.'));
        $memoPrefix = trim((string) config('creditsoft.licensing.renewal.memo_prefix', 'CreditSoft renewal'));
        $defaultAmount = trim((string) config('creditsoft.licensing.renewal.default_amount', ''));
        $supportEmail = trim((string) config('creditsoft.licensing.renewal.support_email', 'hello@creditsoft.app'));
        $supportPhone = trim((string) config('creditsoft.licensing.renewal.support_phone', ''));
        $paymentNote = trim((string) config('creditsoft.licensing.renewal.payment_note', 'Payments can take up to 8 hours to process.'));
        $qrImagePath = trim((string) config('creditsoft.licensing.renewal.qr_image_path', '/assets/payments/zelle.png'));
        $qrImageUrl = $this->renewalQrImageUrl($qrImagePath);
        $planLabel = trim((string) ($license['plan_label'] ?? $license['plan'] ?? 'Office license'));
        $memoEmail = strtolower(trim((string) ($state['admin_email'] ?? $license['admin_email'] ?? $license['customer_email'] ?? '')));
        $memo = filter_var($memoEmail, FILTER_VALIDATE_EMAIL)
            ? $memoEmail
            : trim($memoPrefix.' '.$planLabel);
        $price = $this->renewalPriceForLicense($license);
        $amount = $defaultAmount !== ''
            ? $defaultAmount
            : ($price['zelle_amount_label'] ?? null);
        $configuredQrText = trim((string) config('creditsoft.licensing.renewal.qr_text', ''));

        $qrText = $configuredQrText !== ''
            ? $configuredQrText
            : implode("\n", array_filter([
                'CreditSoft office renewal',
                $payeeName !== '' ? 'Payee: '.$payeeName : null,
                $zelleContact !== '' ? 'Zelle: '.$zelleContact : null,
                $bankName !== '' ? 'Bank: '.$bankName : null,
                $amount ? 'Amount: '.$amount : null,
                $memo !== '' ? 'Memo: '.$memo : null,
                $paymentNote,
            ]));

        return [
            'payee_name' => $payeeName,
            'zelle_contact' => $zelleContact,
            'bank_name' => $bankName,
            'expected_subject' => $expectedSubject,
            'dedicated_mailbox_note' => $dedicatedMailboxNote,
            'zelle_address_note' => $zelleAddressNote,
            'memo' => $memo,
            'amount' => $amount,
            'base_amount_label' => $price['base_amount_label'] ?? null,
            'discount_percent' => $price['discount_percent'] ?? 10,
            'discount_amount_label' => $price['discount_amount_label'] ?? null,
            'zelle_amount_label' => $price['zelle_amount_label'] ?? $amount,
            'pricing_interval_label' => $price['interval_label'] ?? 'month',
            'pricing_placeholder_note' => $qrImageUrl
                ? 'Scan this live Zelle QR, then use the email address on your CreditSoft account/license as the memo for faster processing.'
                : 'QR preview unavailable right now. Use the amount and destination below.',
            'payment_note' => $paymentNote,
            'support_email' => $supportEmail,
            'support_phone' => $supportPhone,
            'qr_image_url' => $qrImageUrl,
            'qr_text' => $qrText,
            'qr_data_uri' => $this->qrDataUri($qrText),
        ];
    }

    protected function renewalQrImageUrl(string $path): ?string
    {
        if ($path === '') {
            return null;
        }

        $publicPath = public_path(ltrim($path, '/'));

        return is_file($publicPath) ? asset($path) : null;
    }

    protected function qrDataUri(string $payload): ?string
    {
        try {
            $renderer = new ImageRenderer(
                new RendererStyle(280),
                new SvgImageBackEnd(),
            );

            $svg = (new Writer($renderer))->writeString($payload);

            return 'data:image/svg+xml;base64,'.base64_encode($svg);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $license
     * @return array<string, mixed>
     */
    protected function renewalPriceForLicense(array $license): array
    {
        $pricingConfigPath = base_path('web/pricing-config.php');

        if (is_file($pricingConfigPath)) {
            require_once $pricingConfigPath;
        }

        $pricing = function_exists('creditsoft_site_pricing_load')
            ? creditsoft_site_pricing_load()
            : [];
        $plans = is_array($pricing['plans'] ?? null) ? $pricing['plans'] : [];
        $licensePlanKey = strtolower(trim((string) ($license['plan_key'] ?? $license['plan'] ?? 'enterprise_pro')));
        $catalogKey = match ($licensePlanKey) {
            'enterprise' => 'professional',
            'enterprise_pro', 'enterprise-pro', 'pro', 'api', 'api_version' => 'enterprise',
            default => str_contains($licensePlanKey, 'pro') ? 'enterprise' : 'professional',
        };
        $plan = is_array($plans[$catalogKey] ?? null) ? $plans[$catalogKey] : null;
        $billing = strtolower(trim((string) ($license['billing_interval'] ?? $license['interval'] ?? 'monthly')));
        $priceKey = in_array($billing, ['yearly', 'annual'], true) ? 'yearly' : 'monthly';
        $baseAmount = (float) ($plan[$priceKey] ?? 0);

        if ($baseAmount <= 0) {
            return [
                'discount_percent' => 10,
                'interval_label' => $priceKey === 'yearly' ? 'year' : 'month',
            ];
        }

        $discountPercent = 10;
        $discountAmount = round($baseAmount * ($discountPercent / 100), 2);
        $zelleAmount = round($baseAmount - $discountAmount, 2);

        return [
            'base_amount' => $baseAmount,
            'base_amount_label' => '$'.number_format($baseAmount, 2),
            'discount_percent' => $discountPercent,
            'discount_amount' => $discountAmount,
            'discount_amount_label' => '$'.number_format($discountAmount, 2),
            'zelle_amount' => $zelleAmount,
            'zelle_amount_label' => '$'.number_format($zelleAmount, 2),
            'interval_label' => $priceKey === 'yearly' ? 'year' : 'month',
        ];
    }
}
