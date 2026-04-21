<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\AuditTrail;
use App\Services\OfficeEmailSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmailController extends Controller
{
    public function edit(OfficeEmailSettingsService $settings, ?string $section = null): Response
    {
        return Inertia::render('settings/Email', [
            'section' => $this->normalizeSection($section),
            'settings' => $settings->load(),
            'providers' => $settings->providers(),
        ]);
    }

    public function update(
        Request $request,
        OfficeEmailSettingsService $settings,
        AuditTrail $auditTrail,
    ): RedirectResponse {
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'use_local_sendmail' => ['nullable', 'boolean'],
            'provider' => ['required', 'string', 'in:microsoft_365,google_workspace,amazon_ses,sendgrid,mailgun,zoho_mail,postmark,brevo,smtp_com,custom_smtp'],
            'from_name' => ['nullable', 'string', 'max:255'],
            'from_email' => ['nullable', 'email', 'max:255'],
            'reply_to_email' => ['nullable', 'email', 'max:255'],
            'host' => ['nullable', 'string', 'max:255'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'scheme' => ['nullable', 'string', 'in:tls,ssl'],
            'username' => ['nullable', 'string', 'max:500'],
            'password' => ['nullable', 'string', 'max:1000'],
            'api_key' => ['nullable', 'string', 'max:1000'],
            'domain' => ['nullable', 'string', 'max:255'],
            'region' => ['nullable', 'string', 'max:64'],
            'redirect_to' => ['nullable', 'string', 'max:2048'],
        ]);

        $saved = $settings->save($validated);

        $auditTrail->record(
            $request->user(),
            'settings.email.updated',
            'Updated SMTP and email delivery settings.',
            null,
            [
                'enabled' => (bool) $saved['enabled'],
                'provider' => (string) $saved['provider'],
                'host' => (string) $saved['host'],
                'from_email' => (string) $saved['from_email'],
            ],
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'SMTP and email settings saved.',
        ]);

        return filled($validated['redirect_to'] ?? null)
            ? redirect()->to((string) $validated['redirect_to'])
            : back();
    }

    protected function normalizeSection(?string $section): string
    {
        $section = trim((string) $section);

        return in_array($section, ['microsoft-365', 'google-workspace', 'amazon-ses', 'sendgrid', 'mailgun', 'zoho-mail', 'postmark', 'brevo', 'smtp-com', 'smtp'], true)
            ? $section
            : 'smtp';
    }
}
