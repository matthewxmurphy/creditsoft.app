<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class OfficeGrowthRuntime
{
    public function __construct(
        protected OfficeGrowthSettingsService $settings,
        protected ClientAssignmentService $assignments,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->settings->load();
    }

    /**
     * @return array<string, mixed>
     */
    public function companyProfile(): array
    {
        return $this->all()['company_profile'] ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    public function signupProcess(): array
    {
        return $this->all()['signup_process'] ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    public function messaging(): array
    {
        return $this->all()['messaging'] ?? [];
    }

    /**
     * @return array<int, array{reason:string,group:string,bureau:string,round:string,key:string}>
     */
    public function creditReasons(?string $group = null, ?string $bureau = null, ?string $round = null): array
    {
        $resolvedGroup = Str::lower(trim((string) $group));
        $resolvedBureau = Str::lower(trim((string) $bureau));
        $resolvedRound = Str::lower(trim((string) $round));

        return collect($this->all()['credit_settings']['reasons'] ?? [])
            ->map(function ($reason): ?array {
                $text = trim((string) data_get($reason, 'reason'));

                if ($text === '') {
                    return null;
                }

                $group = trim((string) data_get($reason, 'group', 'Account')) ?: 'Account';
                $bureau = trim((string) data_get($reason, 'bureau', 'all')) ?: 'all';
                $round = trim((string) data_get($reason, 'round', 'any')) ?: 'any';

                return [
                    'key' => Str::slug($group.'-'.$bureau.'-'.$round.'-'.$text),
                    'reason' => $text,
                    'group' => $group,
                    'bureau' => $bureau,
                    'round' => $round,
                ];
            })
            ->filter()
            ->filter(function (array $reason) use ($resolvedGroup, $resolvedBureau, $resolvedRound): bool {
                $group = Str::lower($reason['group']);
                $bureau = Str::lower($reason['bureau']);
                $round = Str::lower($reason['round']);

                $groupMatches = $resolvedGroup === '' || $resolvedGroup === 'all' || $group === 'all' || $group === $resolvedGroup;
                $bureauMatches = $resolvedBureau === '' || $resolvedBureau === 'all' || $bureau === 'all' || $bureau === $resolvedBureau;
                $roundMatches = $resolvedRound === '' || $resolvedRound === 'any' || $round === 'any' || $round === $resolvedRound;

                return $groupMatches && $bureauMatches && $roundMatches;
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{label:string,key:string,type:string,target:string,required:bool}>
     */
    public function crmFields(string $target = 'client'): array
    {
        $resolvedTarget = Str::lower(trim($target)) ?: 'client';

        return collect($this->all()['crm_fields'] ?? [])
            ->map(function ($field): ?array {
                $label = trim((string) data_get($field, 'label'));
                $key = trim((string) data_get($field, 'key'));

                if ($label === '' || $key === '') {
                    return null;
                }

                return [
                    'label' => $label,
                    'key' => Str::snake($key),
                    'type' => trim((string) data_get($field, 'type', 'text')) ?: 'text',
                    'target' => trim((string) data_get($field, 'target', 'client')) ?: 'client',
                    'required' => (bool) data_get($field, 'required', false),
                ];
            })
            ->filter()
            ->filter(fn (array $field) => in_array(Str::lower($field['target']), [$resolvedTarget, 'both'], true))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{key:string,label:string,first_name:string,last_name:string,company:string,email:string,phone:string,assigned_to:string,matched_user_id:?int}>
     */
    public function affiliates(): array
    {
        return collect($this->all()['affiliates'] ?? [])
            ->map(function ($affiliate): ?array {
                $firstName = trim((string) data_get($affiliate, 'first_name'));
                $lastName = trim((string) data_get($affiliate, 'last_name'));
                $company = trim((string) data_get($affiliate, 'company'));
                $email = trim((string) data_get($affiliate, 'email'));
                $phone = trim((string) data_get($affiliate, 'phone'));
                $assignedTo = trim((string) data_get($affiliate, 'assigned_to'));

                if ($firstName === '' && $lastName === '' && $company === '' && $email === '') {
                    return null;
                }

                $label = trim(collect([
                    trim("{$firstName} {$lastName}"),
                    $company,
                ])->filter()->implode(' · '));

                return [
                    'key' => Str::slug(collect([$company, $email, trim("{$firstName} {$lastName}")])->filter()->implode('-')),
                    'label' => $label !== '' ? $label : ($email !== '' ? $email : 'Affiliate'),
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'company' => $company,
                    'email' => $email,
                    'phone' => $phone,
                    'assigned_to' => $assignedTo,
                    'matched_user_id' => $this->assignments->matchUserId([$assignedTo]),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function affiliateByKey(?string $key): ?array
    {
        $resolved = trim((string) $key);

        if ($resolved === '') {
            return null;
        }

        return collect($this->affiliates())
            ->first(fn (array $affiliate) => $affiliate['key'] === $resolved);
    }

    /**
     * @return array<string, mixed>
     */
    public function officeContext(): array
    {
        $company = $this->companyProfile();
        $signup = $this->signupProcess();

        return [
            'company_name' => trim((string) ($company['company_name'] ?? '')) ?: config('app.name', 'CreditSoft'),
            'customer_portal_link' => trim((string) ($company['customer_portal_link'] ?? '')),
            'support_email' => trim((string) ($company['support_email'] ?? '')),
            'business_phone' => trim((string) ($company['business_phone'] ?? '')),
            'security_sms_phone' => trim((string) ($company['security_sms_phone'] ?? '')),
            'fax' => trim((string) ($company['fax'] ?? '')),
            'usps_mailer_id' => trim((string) ($company['usps_mailer_id'] ?? '')),
            'intake_endpoint' => trim((string) ($signup['intake_endpoint'] ?? '/api/v1/clients')),
            'document_upload_endpoint' => trim((string) ($signup['document_upload_endpoint'] ?? '/api/v1/clients/{clientCuid}/documents')),
            'browser_capture_endpoint' => trim((string) ($signup['browser_capture_endpoint'] ?? '/api/v1/browser-companion/intake')),
        ];
    }

    public function renderTemplateSubject(string $type, ?string $fallback = null): string
    {
        $template = collect($this->messaging()['templates'] ?? [])
            ->first(fn ($candidate) => trim((string) data_get($candidate, 'type')) === $type);

        $subject = trim((string) data_get($template, 'subject', $fallback ?? ''));

        if ($subject === '') {
            return '';
        }

        return str_replace(
            ['[COMPANY-NAME]', '[SUPPORT-EMAIL]'],
            [
                (string) $this->officeContext()['company_name'],
                (string) $this->officeContext()['support_email'],
            ],
            $subject,
        );
    }
}
