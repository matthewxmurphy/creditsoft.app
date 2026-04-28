<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;

class OfficeEmailSettingsService
{
    public function __construct(
        protected EnvironmentEditor $editor,
    ) {}

    /**
     * @return array<int, array<string, string>>
     */
    public function providers(): array
    {
        return [
            [
                'key' => 'microsoft_365',
                'section' => 'microsoft-365',
                'label' => 'Microsoft 365',
                'logo' => '/branding/email-providers/microsoft-365.svg',
                'help' => 'Business email for Outlook and Office environments.',
            ],
            [
                'key' => 'google_workspace',
                'section' => 'google-workspace',
                'label' => 'Google Workspace',
                'logo' => '/branding/email-providers/google-workspace.svg',
                'help' => 'Gmail-based sending for teams and clients.',
            ],
            [
                'key' => 'amazon_ses',
                'section' => 'amazon-ses',
                'label' => 'Amazon SES',
                'logo' => '/branding/email-providers/amazon-ses.svg',
                'help' => 'Low-cost, scalable delivery for system emails.',
            ],
            [
                'key' => 'sendgrid',
                'section' => 'sendgrid',
                'label' => 'SendGrid',
                'logo' => '/branding/email-providers/sendgrid.svg',
                'help' => 'Reliable API-driven email with strong deliverability.',
            ],
            [
                'key' => 'mailgun',
                'section' => 'mailgun',
                'label' => 'Mailgun',
                'logo' => '/branding/email-providers/mailgun.svg',
                'help' => 'Domain-based sending for automation and workflows.',
            ],
            [
                'key' => 'zoho_mail',
                'section' => 'zoho-mail',
                'label' => 'Zoho Mail',
                'logo' => '/branding/email-providers/zoho-mail.svg',
                'help' => 'Affordable business email for small teams.',
            ],
            [
                'key' => 'postmark',
                'section' => 'postmark',
                'label' => 'Postmark',
                'logo' => '/branding/email-providers/postmark.svg',
                'help' => 'Fast transactional email with excellent delivery.',
            ],
            [
                'key' => 'brevo',
                'section' => 'brevo',
                'label' => 'Brevo',
                'logo' => '/branding/email-providers/brevo.svg',
                'help' => 'Marketing and transactional email in one platform.',
            ],
            [
                'key' => 'smtp_com',
                'section' => 'smtp-com',
                'label' => 'SMTP.com',
                'logo' => '/branding/email-providers/smtp-com.svg',
                'help' => 'Enterprise-level delivery with scalable infrastructure.',
            ],
            [
                'key' => 'custom_smtp',
                'section' => 'smtp',
                'label' => 'Custom SMTP',
                'logo' => '/branding/email-providers/smtp.svg',
                'help' => 'Connect any mail server not listed here.',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function load(): array
    {
        $managed = $this->editor->readManagedVariables();
        $provider = $this->normalizeProvider($managed['CREDITSOFT_MAIL_PROVIDER'] ?? config('creditsoft.mail.provider', 'custom_smtp'));
        $providerSettings = $this->providerSettingsFromManaged($managed);
        $activeSettings = $this->activeSmtpSettings($managed, $providerSettings, $provider);
        $scheme = $this->normalizeScheme($activeSettings['scheme'] ?? config('mail.mailers.smtp.scheme', 'tls'));
        $mailer = (string) config('mail.default', 'log');
        $localRelay = filter_var($managed['CREDITSOFT_MAIL_LOCAL_RELAY'] ?? config('creditsoft.mail.local_relay', false), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false;
        $activePassword = (string) ($activeSettings['password'] ?? $managed['MAIL_PASSWORD'] ?? '');

        return [
            'enabled' => (bool) config('creditsoft.mail.enabled', false),
            'provider' => $provider,
            'mailer' => $mailer,
            'use_local_sendmail' => $localRelay || $mailer === 'sendmail',
            'sendmail_path' => (string) config('mail.mailers.sendmail.path', ''),
            'from_name' => (string) config('mail.from.name', ''),
            'from_email' => (string) config('mail.from.address', ''),
            'reply_to_email' => (string) config('mail.reply_to.address', ''),
            'host' => (string) ($activeSettings['host'] ?? config('mail.mailers.smtp.host', '')),
            'port' => (string) ($activeSettings['port'] ?? config('mail.mailers.smtp.port', '587')),
            'scheme' => $scheme,
            'username' => (string) ($activeSettings['username'] ?? config('mail.mailers.smtp.username', '')),
            'masked_password' => $this->mask($activePassword),
            'domain' => (string) ($activeSettings['domain'] ?? config('mail.mailers.smtp.local_domain', '')),
            'region' => (string) ($activeSettings['region'] ?? $managed['AWS_DEFAULT_REGION'] ?? 'us-east-1'),
            'masked_sendgrid_api_key' => $this->mask($provider === 'sendgrid' ? $activePassword : ($managed['SENDGRID_API_KEY'] ?? '')),
            'provider_settings' => $this->publicProviderSettings($providerSettings, $managed, $provider),
            'providers' => $this->providers(),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function save(array $input): array
    {
        $existing = $this->editor->readManagedVariables();
        $provider = $this->normalizeProvider((string) Arr::get($input, 'provider', 'custom_smtp'));
        $providerSettings = $this->providerSettingsFromManaged($existing);
        $providerDefaults = $this->activeSmtpSettings($existing, $providerSettings, $provider);

        $enabled = (bool) Arr::get($input, 'enabled', false);
        $useLocalRelay = (bool) Arr::get($input, 'use_local_sendmail', false);
        $fromName = trim((string) Arr::get($input, 'from_name', config('mail.from.name', 'CreditSoft Office')));
        $fromEmail = trim((string) Arr::get($input, 'from_email', config('mail.from.address', '')));
        $replyToEmail = trim((string) Arr::get($input, 'reply_to_email', config('mail.reply_to.address', '')));
        $region = trim((string) Arr::get($input, 'region', $providerDefaults['region'] ?? $existing['AWS_DEFAULT_REGION'] ?? 'us-east-1')) ?: 'us-east-1';

        $variables = [
            'CREDITSOFT_MAIL_ENABLED' => $enabled ? 'true' : 'false',
            'CREDITSOFT_MAIL_PROVIDER' => $provider,
            'CREDITSOFT_MAIL_LOCAL_RELAY' => $useLocalRelay ? 'true' : 'false',
            'MAIL_MAILER' => $enabled ? ($useLocalRelay ? 'sendmail' : 'smtp') : 'log',
            'MAIL_SENDMAIL_PATH' => $existing['MAIL_SENDMAIL_PATH'] ?? '/usr/sbin/sendmail -t -i',
            'MAIL_FROM_NAME' => $fromName,
            'MAIL_FROM_ADDRESS' => $fromEmail,
            'MAIL_REPLY_TO_ADDRESS' => $replyToEmail,
            'MAIL_REPLY_TO_NAME' => $fromName,
            'AWS_DEFAULT_REGION' => $region,
        ];

        $smtp = $this->smtpVariablesFor($provider, $input, $existing, $region, $fromEmail, $providerDefaults);
        $variables = array_merge($variables, $smtp);

        if ($useLocalRelay && ($smtp['MAIL_HOST'] ?? '') !== '') {
            $variables['MAIL_SENDMAIL_PATH'] = $this->writeMsmtpConfig($smtp, $fromEmail);
        }

        if ($provider === 'sendgrid') {
            $variables['SENDGRID_API_KEY'] = $smtp['MAIL_PASSWORD'] ?? ($existing['SENDGRID_API_KEY'] ?? '');
        }

        $providerSettings[$provider] = [
            'host' => $smtp['MAIL_HOST'] ?? '',
            'port' => $smtp['MAIL_PORT'] ?? '587',
            'scheme' => $smtp['MAIL_SCHEME'] ?? 'tls',
            'username' => $smtp['MAIL_USERNAME'] ?? '',
            'password' => $smtp['MAIL_PASSWORD'] ?? '',
            'domain' => $smtp['MAIL_EHLO_DOMAIN'] ?? '',
            'region' => $region,
            'updated_at' => now()->toIso8601String(),
        ];

        $variables['CREDITSOFT_MAIL_PROVIDER_SETTINGS'] = $this->encodeProviderSettings($providerSettings);

        $this->editor->setMany($variables);

        return $this->load();
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, string>  $existing
     * @param  array<string, string>  $providerDefaults
     * @return array<string, string>
     */
    protected function smtpVariablesFor(string $provider, array $input, array $existing, string $region, string $fromEmail, array $providerDefaults = []): array
    {
        $host = trim((string) Arr::get($input, 'host', $providerDefaults['host'] ?? $existing['MAIL_HOST'] ?? ''));
        $port = trim((string) Arr::get($input, 'port', $providerDefaults['port'] ?? $existing['MAIL_PORT'] ?? '587')) ?: '587';
        $scheme = $this->normalizeScheme(Arr::get($input, 'scheme', $providerDefaults['scheme'] ?? $existing['MAIL_SCHEME'] ?? 'tls'));
        $username = trim((string) Arr::get($input, 'username', $providerDefaults['username'] ?? $existing['MAIL_USERNAME'] ?? ''));
        $password = trim((string) Arr::get($input, 'password', ''));
        $apiKey = trim((string) Arr::get($input, 'api_key', ''));
        $domain = trim((string) Arr::get($input, 'domain', $providerDefaults['domain'] ?? $existing['MAIL_EHLO_DOMAIN'] ?? ''));

        if ($password === '' && $apiKey !== '') {
            $password = $apiKey;
        }

        if ($password === '') {
            $password = (string) ($providerDefaults['password'] ?? $existing['MAIL_PASSWORD'] ?? '');
        }

        if ($provider === 'microsoft_365') {
            $host = 'smtp.office365.com';
            $port = '587';
            $scheme = 'tls';
            $username = $username !== '' ? $username : $fromEmail;
        } elseif ($provider === 'google_workspace') {
            $host = 'smtp.gmail.com';
            $port = '587';
            $scheme = 'tls';
            $username = $username !== '' ? $username : $fromEmail;
        } elseif ($provider === 'sendgrid') {
            $host = 'smtp.sendgrid.net';
            $port = '587';
            $scheme = 'tls';
            $username = 'apikey';
            $password = $apiKey !== '' ? $apiKey : (string) ($providerDefaults['password'] ?? $existing['SENDGRID_API_KEY'] ?? $password);
        } elseif ($provider === 'amazon_ses') {
            $host = "email-smtp.{$region}.amazonaws.com";
            $port = '587';
            $scheme = 'tls';
        } elseif ($provider === 'mailgun') {
            $host = $host !== '' ? $host : 'smtp.mailgun.org';
            $port = '587';
            $scheme = 'tls';
        } elseif ($provider === 'zoho_mail') {
            $host = 'smtp.zoho.com';
            $port = '587';
            $scheme = 'tls';
        } elseif ($provider === 'postmark') {
            $host = 'smtp.postmarkapp.com';
            $port = '587';
            $scheme = 'tls';
        } elseif ($provider === 'brevo') {
            $host = 'smtp-relay.brevo.com';
            $port = '587';
            $scheme = 'tls';
        } elseif ($provider === 'smtp_com') {
            $host = 'send.smtp.com';
            $port = '587';
            $scheme = 'tls';
        }

        return [
            'MAIL_HOST' => $host,
            'MAIL_PORT' => $port,
            'MAIL_SCHEME' => $scheme,
            'MAIL_USERNAME' => $username,
            'MAIL_PASSWORD' => $password,
            'MAIL_EHLO_DOMAIN' => $domain,
        ];
    }

    protected function normalizeProvider(string $provider): string
    {
        return in_array($provider, $this->providerKeys(), true) ? $provider : 'custom_smtp';
    }

    /**
     * @return array<int, string>
     */
    protected function providerKeys(): array
    {
        return array_column($this->providers(), 'key');
    }

    protected function normalizeScheme(mixed $scheme): string
    {
        $scheme = trim((string) $scheme);

        return in_array($scheme, ['tls', 'ssl'], true) ? $scheme : 'tls';
    }

    /**
     * @param  array<string, string>  $managed
     * @return array<string, array<string, string>>
     */
    protected function providerSettingsFromManaged(array $managed): array
    {
        $encoded = $managed['CREDITSOFT_MAIL_PROVIDER_SETTINGS'] ?? config('creditsoft.mail.provider_settings', '');
        $decoded = is_string($encoded) && $encoded !== '' ? json_decode($encoded, true) : [];

        if (! is_array($decoded)) {
            return [];
        }

        $settings = [];

        foreach ($this->providerKeys() as $provider) {
            $slot = $decoded[$provider] ?? null;

            if (! is_array($slot)) {
                continue;
            }

            $settings[$provider] = [
                'host' => trim((string) ($slot['host'] ?? '')),
                'port' => trim((string) ($slot['port'] ?? '587')) ?: '587',
                'scheme' => $this->normalizeScheme($slot['scheme'] ?? 'tls'),
                'username' => trim((string) ($slot['username'] ?? '')),
                'password' => (string) ($slot['password'] ?? ''),
                'domain' => trim((string) ($slot['domain'] ?? '')),
                'region' => trim((string) ($slot['region'] ?? 'us-east-1')) ?: 'us-east-1',
                'updated_at' => trim((string) ($slot['updated_at'] ?? '')),
            ];
        }

        return $settings;
    }

    /**
     * @param  array<string, string>  $managed
     * @return array<string, string>
     */
    protected function legacyProviderSettings(array $managed, string $provider): array
    {
        return [
            'host' => (string) ($managed['MAIL_HOST'] ?? config('mail.mailers.smtp.host', '')),
            'port' => (string) ($managed['MAIL_PORT'] ?? config('mail.mailers.smtp.port', '587')),
            'scheme' => $this->normalizeScheme($managed['MAIL_SCHEME'] ?? config('mail.mailers.smtp.scheme', 'tls')),
            'username' => (string) ($managed['MAIL_USERNAME'] ?? config('mail.mailers.smtp.username', '')),
            'password' => (string) ($provider === 'sendgrid'
                ? ($managed['SENDGRID_API_KEY'] ?? $managed['MAIL_PASSWORD'] ?? config('mail.mailers.smtp.password', ''))
                : ($managed['MAIL_PASSWORD'] ?? config('mail.mailers.smtp.password', ''))),
            'domain' => (string) ($managed['MAIL_EHLO_DOMAIN'] ?? config('mail.mailers.smtp.local_domain', '')),
            'region' => (string) ($managed['AWS_DEFAULT_REGION'] ?? 'us-east-1'),
            'updated_at' => '',
        ];
    }

    /**
     * @param  array<string, string>  $base
     * @param  array<string, string>  $overlay
     * @return array<string, string>
     */
    protected function mergeNonEmpty(array $base, array $overlay): array
    {
        foreach ($overlay as $key => $value) {
            if ($value !== '') {
                $base[$key] = $value;
            }
        }

        return $base;
    }

    /**
     * @param  array<string, string>  $managed
     * @param  array<string, array<string, string>>  $providerSettings
     * @return array<string, string>
     */
    protected function activeSmtpSettings(array $managed, array $providerSettings, string $provider): array
    {
        return $this->mergeNonEmpty(
            $this->legacyProviderSettings($managed, $provider),
            $providerSettings[$provider] ?? [],
        );
    }

    /**
     * @param  array<string, array<string, string>>  $providerSettings
     * @param  array<string, string>  $managed
     * @return array<string, array<string, string|bool|null>>
     */
    protected function publicProviderSettings(array $providerSettings, array $managed, string $activeProvider): array
    {
        $public = [];

        foreach ($this->providerKeys() as $provider) {
            $settings = $providerSettings[$provider] ?? [];

            if ($provider === $activeProvider) {
                $settings = $this->activeSmtpSettings($managed, $providerSettings, $provider);
            }

            $password = (string) ($settings['password'] ?? '');

            $public[$provider] = [
                'configured' => $this->isConfiguredProvider($settings),
                'host' => (string) ($settings['host'] ?? ''),
                'port' => (string) ($settings['port'] ?? ''),
                'scheme' => $this->normalizeScheme($settings['scheme'] ?? 'tls'),
                'username' => (string) ($settings['username'] ?? ''),
                'domain' => (string) ($settings['domain'] ?? ''),
                'region' => (string) ($settings['region'] ?? 'us-east-1'),
                'updated_at' => (string) ($settings['updated_at'] ?? ''),
                'masked_password' => $this->mask($password),
                'masked_sendgrid_api_key' => $provider === 'sendgrid' ? $this->mask($password) : null,
            ];
        }

        return $public;
    }

    /**
     * @param  array<string, string>  $settings
     */
    protected function isConfiguredProvider(array $settings): bool
    {
        return trim((string) ($settings['host'] ?? '')) !== ''
            && (
                trim((string) ($settings['username'] ?? '')) !== ''
                || trim((string) ($settings['password'] ?? '')) !== ''
            );
    }

    /**
     * @param  array<string, array<string, string>>  $settings
     */
    protected function encodeProviderSettings(array $settings): string
    {
        $encoded = json_encode($settings, JSON_UNESCAPED_SLASHES);

        return is_string($encoded) ? $encoded : '{}';
    }

    /**
     * @param  array<string, string>  $smtp
     */
    protected function writeMsmtpConfig(array $smtp, string $fromEmail): string
    {
        $path = storage_path('app/private/msmtp/msmtprc');
        File::ensureDirectoryExists(dirname($path));

        $scheme = $this->normalizeScheme($smtp['MAIL_SCHEME'] ?? 'tls');
        $username = (string) ($smtp['MAIL_USERNAME'] ?? '');
        $password = (string) ($smtp['MAIL_PASSWORD'] ?? '');
        $domain = trim((string) ($smtp['MAIL_EHLO_DOMAIN'] ?? ''));
        $from = $fromEmail !== '' ? $fromEmail : $username;

        $lines = [
            'defaults',
            'logfile '.$this->msmtpValue(storage_path('logs/msmtp.log')),
            'tls on',
            'tls_starttls '.($scheme === 'ssl' ? 'off' : 'on'),
            'tls_trust_file /etc/ssl/certs/ca-certificates.crt',
            'account default',
            'host '.$this->msmtpValue((string) ($smtp['MAIL_HOST'] ?? '')),
            'port '.$this->msmtpValue((string) ($smtp['MAIL_PORT'] ?? '587')),
            'from '.$this->msmtpValue($from),
            'auth '.($username !== '' || $password !== '' ? 'on' : 'off'),
        ];

        if ($username !== '') {
            $lines[] = 'user '.$this->msmtpValue($username);
        }

        if ($password !== '') {
            $lines[] = 'password '.$this->msmtpValue($password);
        }

        if ($domain !== '') {
            $lines[] = 'domain '.$this->msmtpValue($domain);
        }

        File::put($path, implode(PHP_EOL, $lines).PHP_EOL);
        @chmod($path, 0600);

        return '/usr/bin/msmtp --file='.$path.' -t';
    }

    protected function msmtpValue(?string $value): string
    {
        $value = str_replace(["\r", "\n"], '', (string) $value);

        if ($value === '') {
            return '""';
        }

        if (preg_match('/^[A-Za-z0-9._@:+\/-]+$/', $value) === 1) {
            return $value;
        }

        return '"'.addcslashes($value, "\"\\").'"';
    }

    protected function mask(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (strlen($value) <= 8) {
            return str_repeat('*', strlen($value));
        }

        return substr($value, 0, 4).str_repeat('*', max(4, strlen($value) - 8)).substr($value, -4);
    }
}
