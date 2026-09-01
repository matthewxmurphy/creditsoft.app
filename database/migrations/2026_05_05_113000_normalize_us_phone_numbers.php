<?php

use App\Support\PhoneNumber;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->normalizeTablePhones('clients', ['phone']);
        $this->normalizeTablePhones('employee_profiles', ['phone', 'emergency_contact_phone']);
    }

    public function down(): void
    {
        // Phone normalization is intentionally not reversible.
    }

    /**
     * @param  list<string>  $columns
     */
    protected function normalizeTablePhones(string $table, array $columns): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'id')) {
            return;
        }

        $columns = array_values(array_filter(
            $columns,
            fn (string $column): bool => Schema::hasColumn($table, $column),
        ));

        if ($columns === []) {
            return;
        }

        DB::table($table)
            ->select(['id', ...$columns])
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($table, $columns): void {
                foreach ($rows as $row) {
                    $updates = [];

                    foreach ($columns as $column) {
                        $current = $row->{$column};
                        $normalized = PhoneNumber::normalize($current);

                        if ($normalized !== $current) {
                            $updates[$column] = $normalized;
                        }
                    }

                    if ($updates !== []) {
                        DB::table($table)->where('id', $row->id)->update($updates);
                    }
                }
            });
    }
};
