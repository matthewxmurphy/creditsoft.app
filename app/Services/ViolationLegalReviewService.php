<?php

namespace App\Services;

use Illuminate\Support\Str;

class ViolationLegalReviewService
{
    /**
     * @param  list<array<string, mixed>>  $evidence
     * @return list<array{code:string,label:string,kind:string,title:string}>
     */
    public function frameworksFor(string $ruleKey, array $evidence = [], ?string $title = null): array
    {
        $frameworks = [];

        if ($this->qualifiesForFcra($ruleKey)) {
            $frameworks[] = [
                'code' => 'FCRA',
                'label' => 'FCRA',
                'kind' => 'lawsuit',
                'title' => 'Potential FCRA claim review for inaccurate, incomplete, or unverifiable credit reporting.',
            ];
        }

        if ($this->qualifiesForFdcpa($ruleKey, $evidence, $title)) {
            $frameworks[] = [
                'code' => 'FDCPA',
                'label' => 'FDCPA',
                'kind' => 'lawsuit',
                'title' => 'Potential FDCPA claim review where debt-collection conduct or collection reporting needs legal review.',
            ];
        }

        return collect($frameworks)
            ->unique('code')
            ->values()
            ->all();
    }

    protected function qualifiesForFcra(string $ruleKey): bool
    {
        return in_array($ruleKey, [
            'metro2_status_conflict',
            'metro2_balance_conflict',
            'metro2_missing_key_dates',
            'metro2_missing_bureau_entry',
            'duplicate_account',
            'stale_collection_open',
            'unverifiable_item',
            'metro2_payment_status_conflict',
            'metro2_open_state_conflict',
            'metro2_missing_credit_limit',
        ], true);
    }

    /**
     * @param  list<array<string, mixed>>  $evidence
     */
    protected function qualifiesForFdcpa(string $ruleKey, array $evidence, ?string $title): bool
    {
        if ($ruleKey === 'stale_collection_open') {
            return true;
        }

        $haystack = collect($evidence)
            ->pluck('detail')
            ->prepend($title ?? '')
            ->filter(fn ($value) => filled($value))
            ->implode(' ');

        $normalized = Str::lower($haystack);

        if ($normalized === '') {
            return false;
        }

        $collectionSignal = Str::contains($normalized, [
            'collection',
            'debt collector',
            'debt buyer',
            'charged off',
            'charge-off',
            'charged-off',
        ]);

        if (! $collectionSignal) {
            return false;
        }

        return in_array($ruleKey, [
            'metro2_status_conflict',
            'metro2_balance_conflict',
            'metro2_missing_key_dates',
            'duplicate_account',
            'stale_collection_open',
            'unverifiable_item',
            'metro2_payment_status_conflict',
            'metro2_open_state_conflict',
        ], true);
    }
}
