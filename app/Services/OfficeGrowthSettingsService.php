<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class OfficeGrowthSettingsService
{
    public function load(): array
    {
        $defaults = $this->defaults();
        $path = $this->storagePath();

        if (! is_file($path)) {
            return $defaults;
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (! is_array($decoded)) {
            return $defaults;
        }

        return $this->sanitize($decoded);
    }

    public function save(array $input): array
    {
        $clean = $this->sanitize($input);
        $directory = dirname($this->storagePath());

        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        file_put_contents(
            $this->storagePath(),
            json_encode($clean, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL,
        );

        return $clean;
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function importActivityCsv(UploadedFile|string $file): array
    {
        $path = $file instanceof UploadedFile ? $file->getRealPath() : $file;

        if (! $path || ! is_file($path)) {
            return [];
        }

        $handle = fopen($path, 'rb');

        if (! $handle) {
            return [];
        }

        $header = fgetcsv($handle, 0, ',', '"', '\\') ?: [];
        $rows = [];

        while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            $item = array_combine($header, $row);

            if (! is_array($item)) {
                continue;
            }

            $rows[] = [
                'user_name' => trim((string) ($item['User Name'] ?? '')),
                'title' => trim((string) ($item['Title'] ?? '')),
                'body' => trim((string) ($item['Body'] ?? '')),
                'relative_date' => trim((string) ($item['Date'] ?? '')),
            ];
        }

        fclose($handle);

        $existing = $this->load();
        $existing['activity_history'] = array_slice(array_values(array_filter($rows, function (array $row): bool {
            return $row['user_name'] !== '' || $row['title'] !== '' || $row['body'] !== '';
        })), 0, 120);

        $this->save($existing);

        return $existing['activity_history'];
    }

    public function defaults(): array
    {
        return [
            'company_profile' => [
                'company_name' => 'CreditSoft Office',
                'customer_portal_link' => '',
                'support_email' => '',
                'business_phone' => '',
                'security_sms_phone' => '',
                'fax' => '',
                'timezone_mode' => 'browser',
                'allow_round_robin' => true,
                'assigned_only_default' => false,
                'internal_note' => '',
            ],
            'signup_process' => [
                'default_name' => 'System Default',
                'shareable_link' => '',
                'default_sales_rep' => 'Office Admin',
                'api_driven' => true,
                'intake_endpoint' => '/api/v1/clients',
                'portal_uploads_to_backend' => true,
                'document_upload_endpoint' => '/api/v1/clients/{clientCuid}/documents',
                'browser_capture_endpoint' => '/api/v1/browser-companion/intake',
                'auto_audit' => true,
                'pricing_required' => true,
                'contract_required' => true,
                'booking_required' => true,
                'id_docs_required' => true,
                'mobile_app_enabled' => false,
                'auto_password_email' => true,
                'follow_up_email' => true,
            ],
            'messaging' => [
                'email_enabled' => true,
                'sms_enabled' => false,
                'provider' => 'builtin',
                'zapier_enabled' => false,
                'from_name' => 'CreditSoft Office',
                'from_email' => '',
                'reply_to_email' => '',
                'sms_sender' => '',
                'sendgrid' => [
                    'enabled' => false,
                    'api_key' => '',
                    'from_name' => '',
                    'from_email' => '',
                    'reply_to_email' => '',
                ],
                'ses' => [
                    'enabled' => false,
                    'access_key' => '',
                    'secret_key' => '',
                    'region' => 'us-west-2',
                    'configuration_set' => '',
                    'from_name' => '',
                    'from_email' => '',
                    'reply_to_email' => '',
                ],
                'zapier_webhook_url' => '',
                'templates' => [
                    [
                        'name' => 'Action Plan Email Template',
                        'type' => 'system_action_plan_template',
                        'subject' => 'Your Personalized Credit Action Plan is Ready!',
                    ],
                    [
                        'name' => 'Signup Process AutoPassword Email',
                        'type' => 'signup_process_auto_password',
                        'subject' => 'Login credentials for you on [COMPANY-NAME]',
                    ],
                    [
                        'name' => 'Follow Up to Customer After 30 Minutes',
                        'type' => 'signup_process_email_template',
                        'subject' => 'Complete Signup Process',
                    ],
                ],
            ],
            'appointments' => [
                'portal_booking_name' => 'Detailed Credit Analysis Consultation',
                'calendar_email' => '',
                'links' => [
                    [
                        'name' => 'Detailed Credit Analysis Consultation',
                        'url' => '',
                        'channel' => 'consultation',
                    ],
                    [
                        'name' => 'Business Credit Consultation',
                        'url' => '',
                        'channel' => 'business-credit',
                    ],
                ],
            ],
            'credit_settings' => [
                'show_default_reasons' => true,
                'reasons' => [
                    [
                        'reason' => 'Permissible purpose',
                        'group' => 'Inquiry',
                        'bureau' => 'all',
                        'round' => 'any',
                    ],
                    [
                        'reason' => 'How did you verify this account',
                        'group' => 'Collections',
                        'bureau' => 'all',
                        'round' => 'any',
                    ],
                    [
                        'reason' => 'Validation of debt',
                        'group' => 'Account',
                        'bureau' => 'all',
                        'round' => 'any',
                    ],
                    [
                        'reason' => 'Proof of inquiry verification',
                        'group' => 'Inquiry',
                        'bureau' => 'all',
                        'round' => 'any',
                    ],
                    [
                        'reason' => 'Provide proof of verification using original bankruptcy court records, including the filing and discharge dates.',
                        'group' => 'Public Records',
                        'bureau' => 'all',
                        'round' => '2',
                    ],
                    [
                        'reason' => 'The payment history, balance, and account status contain factual inconsistencies across bureaus and internal records.',
                        'group' => 'Account',
                        'bureau' => 'all',
                        'round' => '1',
                    ],
                ],
            ],
            'crm_fields' => [
                [
                    'label' => 'Monitoring provider',
                    'key' => 'monitoring_provider',
                    'type' => 'select',
                    'target' => 'client',
                    'required' => false,
                ],
                [
                    'label' => 'Current software',
                    'key' => 'current_software',
                    'type' => 'text',
                    'target' => 'lead',
                    'required' => false,
                ],
                [
                    'label' => 'Outsourcing status',
                    'key' => 'outsourcing_status',
                    'type' => 'select',
                    'target' => 'lead',
                    'required' => false,
                ],
            ],
            'affiliates' => [
                [
                    'first_name' => 'Justin',
                    'last_name' => 'Brown',
                    'company' => 'Moneta Strategies',
                    'email' => 'justin@monetastrategies.com',
                    'phone' => '+1 (832) 567-4691',
                    'assigned_to' => 'Office Admin',
                ],
                [
                    'first_name' => "De'Mont",
                    'last_name' => 'Perry',
                    'company' => 'Digital Media Partners',
                    'email' => 'dmpkcmo@gmail.com',
                    'phone' => '+1 8162871858',
                    'assigned_to' => 'Office Admin',
                ],
            ],
            'activity_history' => [],
        ];
    }

    protected function sanitize(array $input): array
    {
        $defaults = $this->defaults();

        $companyProfile = [
            'company_name' => trim((string) Arr::get($input, 'company_profile.company_name', $defaults['company_profile']['company_name'])),
            'customer_portal_link' => trim((string) Arr::get($input, 'company_profile.customer_portal_link', $defaults['company_profile']['customer_portal_link'])),
            'support_email' => trim((string) Arr::get($input, 'company_profile.support_email', $defaults['company_profile']['support_email'])),
            'business_phone' => trim((string) Arr::get($input, 'company_profile.business_phone', $defaults['company_profile']['business_phone'])),
            'security_sms_phone' => trim((string) Arr::get($input, 'company_profile.security_sms_phone', $defaults['company_profile']['security_sms_phone'])),
            'fax' => trim((string) Arr::get($input, 'company_profile.fax', $defaults['company_profile']['fax'])),
            'timezone_mode' => trim((string) Arr::get($input, 'company_profile.timezone_mode', $defaults['company_profile']['timezone_mode'])) ?: 'browser',
            'allow_round_robin' => (bool) Arr::get($input, 'company_profile.allow_round_robin', $defaults['company_profile']['allow_round_robin']),
            'assigned_only_default' => (bool) Arr::get($input, 'company_profile.assigned_only_default', $defaults['company_profile']['assigned_only_default']),
            'internal_note' => trim((string) Arr::get($input, 'company_profile.internal_note', $defaults['company_profile']['internal_note'])),
        ];

        $signup = [
            'default_name' => trim((string) Arr::get($input, 'signup_process.default_name', $defaults['signup_process']['default_name'])),
            'shareable_link' => trim((string) Arr::get($input, 'signup_process.shareable_link', $defaults['signup_process']['shareable_link'])),
            'default_sales_rep' => trim((string) Arr::get($input, 'signup_process.default_sales_rep', $defaults['signup_process']['default_sales_rep'])),
            'api_driven' => (bool) Arr::get($input, 'signup_process.api_driven', $defaults['signup_process']['api_driven']),
            'intake_endpoint' => trim((string) Arr::get($input, 'signup_process.intake_endpoint', $defaults['signup_process']['intake_endpoint'])) ?: $defaults['signup_process']['intake_endpoint'],
            'portal_uploads_to_backend' => (bool) Arr::get($input, 'signup_process.portal_uploads_to_backend', $defaults['signup_process']['portal_uploads_to_backend']),
            'document_upload_endpoint' => trim((string) Arr::get($input, 'signup_process.document_upload_endpoint', $defaults['signup_process']['document_upload_endpoint'])) ?: $defaults['signup_process']['document_upload_endpoint'],
            'browser_capture_endpoint' => trim((string) Arr::get($input, 'signup_process.browser_capture_endpoint', $defaults['signup_process']['browser_capture_endpoint'])) ?: $defaults['signup_process']['browser_capture_endpoint'],
            'auto_audit' => (bool) Arr::get($input, 'signup_process.auto_audit', $defaults['signup_process']['auto_audit']),
            'pricing_required' => (bool) Arr::get($input, 'signup_process.pricing_required', $defaults['signup_process']['pricing_required']),
            'contract_required' => (bool) Arr::get($input, 'signup_process.contract_required', $defaults['signup_process']['contract_required']),
            'booking_required' => (bool) Arr::get($input, 'signup_process.booking_required', $defaults['signup_process']['booking_required']),
            'id_docs_required' => (bool) Arr::get($input, 'signup_process.id_docs_required', $defaults['signup_process']['id_docs_required']),
            'mobile_app_enabled' => (bool) Arr::get($input, 'signup_process.mobile_app_enabled', $defaults['signup_process']['mobile_app_enabled']),
            'auto_password_email' => (bool) Arr::get($input, 'signup_process.auto_password_email', $defaults['signup_process']['auto_password_email']),
            'follow_up_email' => (bool) Arr::get($input, 'signup_process.follow_up_email', $defaults['signup_process']['follow_up_email']),
        ];

        $templates = [];
        foreach ((array) Arr::get($input, 'messaging.templates', $defaults['messaging']['templates']) as $template) {
            $name = trim((string) Arr::get($template, 'name'));
            $type = trim((string) Arr::get($template, 'type'));
            $subject = trim((string) Arr::get($template, 'subject'));

            if ($name === '' && $type === '' && $subject === '') {
                continue;
            }

            $templates[] = [
                'name' => $name,
                'type' => $type,
                'subject' => $subject,
            ];
        }

        if ($templates === []) {
            $templates = $defaults['messaging']['templates'];
        }

        $provider = trim((string) Arr::get($input, 'messaging.provider', $defaults['messaging']['provider'])) ?: 'builtin';
        if (! in_array($provider, ['builtin', 'sendgrid', 'ses'], true)) {
            $provider = 'builtin';
        }

        $messaging = [
            'email_enabled' => (bool) Arr::get($input, 'messaging.email_enabled', $defaults['messaging']['email_enabled']),
            'sms_enabled' => (bool) Arr::get($input, 'messaging.sms_enabled', $defaults['messaging']['sms_enabled']),
            'provider' => $provider,
            'zapier_enabled' => (bool) Arr::get($input, 'messaging.zapier_enabled', $defaults['messaging']['zapier_enabled']),
            'from_name' => trim((string) Arr::get($input, 'messaging.from_name', $defaults['messaging']['from_name'])),
            'from_email' => trim((string) Arr::get($input, 'messaging.from_email', $defaults['messaging']['from_email'])),
            'reply_to_email' => trim((string) Arr::get($input, 'messaging.reply_to_email', $defaults['messaging']['reply_to_email'])),
            'sms_sender' => trim((string) Arr::get($input, 'messaging.sms_sender', $defaults['messaging']['sms_sender'])),
            'sendgrid' => [
                'enabled' => (bool) Arr::get($input, 'messaging.sendgrid.enabled', $defaults['messaging']['sendgrid']['enabled']),
                'api_key' => trim((string) Arr::get($input, 'messaging.sendgrid.api_key', $defaults['messaging']['sendgrid']['api_key'])),
                'from_name' => trim((string) Arr::get($input, 'messaging.sendgrid.from_name', $defaults['messaging']['sendgrid']['from_name'])),
                'from_email' => trim((string) Arr::get($input, 'messaging.sendgrid.from_email', $defaults['messaging']['sendgrid']['from_email'])),
                'reply_to_email' => trim((string) Arr::get($input, 'messaging.sendgrid.reply_to_email', $defaults['messaging']['sendgrid']['reply_to_email'])),
            ],
            'ses' => [
                'enabled' => (bool) Arr::get($input, 'messaging.ses.enabled', $defaults['messaging']['ses']['enabled']),
                'access_key' => trim((string) Arr::get($input, 'messaging.ses.access_key', $defaults['messaging']['ses']['access_key'])),
                'secret_key' => trim((string) Arr::get($input, 'messaging.ses.secret_key', $defaults['messaging']['ses']['secret_key'])),
                'region' => trim((string) Arr::get($input, 'messaging.ses.region', $defaults['messaging']['ses']['region'])) ?: 'us-west-2',
                'configuration_set' => trim((string) Arr::get($input, 'messaging.ses.configuration_set', $defaults['messaging']['ses']['configuration_set'])),
                'from_name' => trim((string) Arr::get($input, 'messaging.ses.from_name', $defaults['messaging']['ses']['from_name'])),
                'from_email' => trim((string) Arr::get($input, 'messaging.ses.from_email', $defaults['messaging']['ses']['from_email'])),
                'reply_to_email' => trim((string) Arr::get($input, 'messaging.ses.reply_to_email', $defaults['messaging']['ses']['reply_to_email'])),
            ],
            'zapier_webhook_url' => trim((string) Arr::get($input, 'messaging.zapier_webhook_url', $defaults['messaging']['zapier_webhook_url'])),
            'templates' => $templates,
        ];

        $links = [];
        foreach ((array) Arr::get($input, 'appointments.links', $defaults['appointments']['links']) as $link) {
            $name = trim((string) Arr::get($link, 'name'));
            $url = trim((string) Arr::get($link, 'url'));
            $channel = trim((string) Arr::get($link, 'channel'));

            if ($name === '' && $url === '' && $channel === '') {
                continue;
            }

            $links[] = [
                'name' => $name,
                'url' => $url,
                'channel' => $channel ?: 'general',
            ];
        }

        if ($links === []) {
            $links = $defaults['appointments']['links'];
        }

        $appointments = [
            'portal_booking_name' => trim((string) Arr::get($input, 'appointments.portal_booking_name', $defaults['appointments']['portal_booking_name'])),
            'calendar_email' => trim((string) Arr::get($input, 'appointments.calendar_email', $defaults['appointments']['calendar_email'])),
            'links' => $links,
        ];

        $creditReasons = [];
        foreach ((array) Arr::get($input, 'credit_settings.reasons', $defaults['credit_settings']['reasons']) as $reason) {
            $text = trim((string) Arr::get($reason, 'reason'));

            if ($text === '') {
                continue;
            }

            $creditReasons[] = [
                'reason' => $text,
                'group' => trim((string) Arr::get($reason, 'group', 'Account')) ?: 'Account',
                'bureau' => trim((string) Arr::get($reason, 'bureau', 'all')) ?: 'all',
                'round' => trim((string) Arr::get($reason, 'round', 'any')) ?: 'any',
            ];
        }

        if ($creditReasons === []) {
            $creditReasons = $defaults['credit_settings']['reasons'];
        }

        $creditSettings = [
            'show_default_reasons' => (bool) Arr::get($input, 'credit_settings.show_default_reasons', $defaults['credit_settings']['show_default_reasons']),
            'reasons' => $creditReasons,
        ];

        $crmFields = [];
        foreach ((array) Arr::get($input, 'crm_fields', $defaults['crm_fields']) as $field) {
            $label = trim((string) Arr::get($field, 'label'));

            if ($label === '') {
                continue;
            }

            $key = trim((string) Arr::get($field, 'key'));
            $crmFields[] = [
                'label' => $label,
                'key' => $key !== '' ? Str::snake($key) : Str::snake($label),
                'type' => trim((string) Arr::get($field, 'type', 'text')) ?: 'text',
                'target' => trim((string) Arr::get($field, 'target', 'client')) ?: 'client',
                'required' => (bool) Arr::get($field, 'required', false),
            ];
        }

        if ($crmFields === []) {
            $crmFields = $defaults['crm_fields'];
        }

        $affiliates = [];
        foreach ((array) Arr::get($input, 'affiliates', []) as $affiliate) {
            $firstName = trim((string) Arr::get($affiliate, 'first_name'));
            $lastName = trim((string) Arr::get($affiliate, 'last_name'));
            $company = trim((string) Arr::get($affiliate, 'company'));
            $email = trim((string) Arr::get($affiliate, 'email'));
            $phone = trim((string) Arr::get($affiliate, 'phone'));
            $assignedTo = trim((string) Arr::get($affiliate, 'assigned_to'));

            if ($firstName === '' && $lastName === '' && $company === '' && $email === '') {
                continue;
            }

            $affiliates[] = [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'company' => $company,
                'email' => $email,
                'phone' => $phone,
                'assigned_to' => $assignedTo,
            ];
        }

        $activityHistory = [];
        foreach ((array) Arr::get($input, 'activity_history', []) as $entry) {
            $userName = trim((string) Arr::get($entry, 'user_name'));
            $title = trim((string) Arr::get($entry, 'title'));
            $body = trim((string) Arr::get($entry, 'body'));
            $relativeDate = trim((string) Arr::get($entry, 'relative_date'));

            if ($userName === '' && $title === '' && $body === '') {
                continue;
            }

            $activityHistory[] = [
                'user_name' => $userName,
                'title' => $title,
                'body' => $body,
                'relative_date' => $relativeDate,
            ];
        }

        return [
            'company_profile' => $companyProfile,
            'signup_process' => $signup,
            'messaging' => $messaging,
            'appointments' => $appointments,
            'credit_settings' => $creditSettings,
            'crm_fields' => $crmFields,
            'affiliates' => $affiliates,
            'activity_history' => $activityHistory,
        ];
    }

    protected function storagePath(): string
    {
        return storage_path('app/private/office-growth-settings.json');
    }
}
