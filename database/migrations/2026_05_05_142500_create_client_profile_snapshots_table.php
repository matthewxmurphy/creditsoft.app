<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_profile_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('client_cuid')->index();
            $table->string('source')->default('office');
            $table->string('source_label')->nullable();
            $table->string('event')->default('profile_snapshot');
            $table->boolean('is_current')->default(false)->index();
            $table->timestamp('recorded_at')->useCurrent()->index();
            $table->timestamp('effective_at')->nullable();
            $table->string('first_name')->nullable();
            $table->string('middle_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('name_suffix', 40)->nullable();
            $table->string('email')->nullable();
            $table->string('secondary_email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('city', 120)->nullable();
            $table->string('state', 80)->nullable();
            $table->string('postal_code', 30)->nullable();
            $table->string('country', 2)->default('US');
            $table->date('date_of_birth')->nullable();
            $table->string('ssn_last_four', 4)->nullable();
            $table->unsignedSmallInteger('current_score')->nullable();
            $table->text('mailing_label')->nullable();
            $table->string('mailing_barcode')->nullable();
            $table->string('mailing_barcode_symbology', 80)->nullable();
            $table->text('mailing_barcode_payload')->nullable();
            $table->string('address_fingerprint', 64)->nullable()->index();
            $table->json('changed_fields')->nullable();
            $table->json('payload')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'recorded_at']);
            $table->index(['client_id', 'is_current']);
            $table->index(['client_cuid', 'recorded_at']);
        });

        DB::table('clients')
            ->orderBy('id')
            ->select([
                'id',
                'cuid',
                'first_name',
                'middle_name',
                'last_name',
                'name_suffix',
                'email',
                'secondary_email',
                'phone',
                'address_line_1',
                'address_line_2',
                'city',
                'state',
                'postal_code',
                'date_of_birth',
                'current_score',
                'created_at',
                'updated_at',
            ])
            ->chunk(500, function ($clients): void {
                $now = now();

                foreach ($clients as $client) {
                    $cityLine = trim(implode(' ', array_filter([
                        trim(implode(', ', array_filter([
                            trim((string) $client->city),
                            trim((string) $client->state),
                        ]))),
                        trim((string) $client->postal_code),
                    ])));
                    $mailingLabel = collect([
                        trim(implode(' ', array_filter([
                            $client->first_name,
                            $client->middle_name,
                            $client->last_name,
                            $client->name_suffix,
                        ]))),
                        $client->address_line_1,
                        $client->address_line_2,
                        $cityLine,
                    ])
                        ->map(fn ($line) => trim((string) $line))
                        ->filter()
                        ->implode("\n");
                    $fingerprint = collect([
                        $client->address_line_1,
                        $client->address_line_2,
                        $client->city,
                        $client->state,
                        $client->postal_code,
                    ])
                        ->map(fn ($value) => strtolower(trim(preg_replace('/[^a-z0-9]+/i', ' ', (string) $value) ?? '')))
                        ->filter()
                        ->implode('|');

                    DB::table('client_profile_snapshots')->insert([
                        'client_id' => $client->id,
                        'client_cuid' => $client->cuid,
                        'source' => 'migration',
                        'source_label' => 'Migration',
                        'event' => 'profile_snapshot',
                        'is_current' => true,
                        'recorded_at' => $now,
                        'effective_at' => $client->updated_at,
                        'first_name' => $client->first_name,
                        'middle_name' => $client->middle_name,
                        'last_name' => $client->last_name,
                        'name_suffix' => $client->name_suffix,
                        'email' => $client->email,
                        'secondary_email' => $client->secondary_email,
                        'phone' => $client->phone,
                        'address_line_1' => $client->address_line_1,
                        'address_line_2' => $client->address_line_2,
                        'city' => $client->city,
                        'state' => $client->state,
                        'postal_code' => $client->postal_code,
                        'country' => 'US',
                        'date_of_birth' => $client->date_of_birth,
                        'current_score' => $client->current_score,
                        'mailing_label' => $mailingLabel !== '' ? $mailingLabel : null,
                        'address_fingerprint' => $fingerprint !== '' ? hash('sha256', $fingerprint) : null,
                        'changed_fields' => json_encode([]),
                        'payload' => json_encode(['backfilled_from_clients_table' => true]),
                        'metadata' => json_encode(['backfilled_at' => $now->toIso8601String()]),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_profile_snapshots');
    }
};
