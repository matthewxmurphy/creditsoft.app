<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Tradeline extends Model
{
    use HasFactory;

    protected $fillable = [
        'bureau_snapshot_id',
        'normalized_key',
        'creditor_name',
        'account_name',
        'account_type',
        'bureau_account_reference',
        'is_revolving',
        'is_open',
        'balance',
        'credit_limit',
        'utilization_percent',
        'payment_status',
        'account_status',
        'date_opened',
        'date_last_payment',
        'date_reported',
        'positive_classification',
        'provenance',
        'remarks',
        'data',
    ];

    protected function casts(): array
    {
        return [
            'is_revolving' => 'boolean',
            'is_open' => 'boolean',
            'positive_classification' => 'boolean',
            'balance' => 'decimal:2',
            'credit_limit' => 'decimal:2',
            'utilization_percent' => 'decimal:2',
            'date_opened' => 'date',
            'date_last_payment' => 'date',
            'date_reported' => 'date',
            'data' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Tradeline $tradeline): void {
            if (blank($tradeline->normalized_key)) {
                $tradeline->normalized_key = self::buildNormalizedKey([
                    'creditor_name' => $tradeline->creditor_name,
                    'account_type' => $tradeline->account_type,
                    'bureau_account_reference' => $tradeline->bureau_account_reference,
                ]);
            }

            if ($tradeline->balance !== null && $tradeline->credit_limit) {
                $tradeline->utilization_percent = round(((float) $tradeline->balance / (float) $tradeline->credit_limit) * 100, 2);
            }
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function buildNormalizedKey(array $attributes): string
    {
        return Str::of((string) ($attributes['creditor_name'] ?? 'unknown'))
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '-')
            ->append('-'.Str::slug((string) ($attributes['account_type'] ?? 'account')))
            ->append('-'.Str::lower(Str::substr((string) ($attributes['bureau_account_reference'] ?? '0000'), -4)))
            ->trim('-')
            ->toString();
    }

    public function bureauSnapshot(): BelongsTo
    {
        return $this->belongsTo(BureauSnapshot::class);
    }
}
