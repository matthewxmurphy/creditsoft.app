<?php

use App\Services\PostalRoutingBarcodeService;
use App\Support\MailingAddress;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->normalizeClients();
        $this->normalizeSnapshots();
    }

    public function down(): void
    {
        // Data cleanup only; do not recombine structured address fields.
    }

    protected function normalizeClients(): void
    {
        DB::table('clients')
            ->orderBy('id')
            ->select([
                'id',
                'address_line_1',
                'address_line_2',
                'city',
                'state',
                'postal_code',
            ])
            ->chunkById(500, function ($clients): void {
                foreach ($clients as $client) {
                    $address = MailingAddress::normalizeFields([
                        'address_line_1' => $client->address_line_1,
                        'address_line_2' => $client->address_line_2,
                        'city' => $client->city,
                        'state' => $client->state,
                        'postal_code' => $client->postal_code,
                    ]);

                    $updates = $this->changedAddressFields($client, $address);

                    if ($updates === []) {
                        continue;
                    }

                    $updates['updated_at'] = now();

                    DB::table('clients')
                        ->where('id', $client->id)
                        ->update($updates);
                }
            });
    }

    protected function normalizeSnapshots(): void
    {
        $barcodes = new PostalRoutingBarcodeService;

        DB::table('client_profile_snapshots')
            ->orderBy('id')
            ->select([
                'id',
                'first_name',
                'middle_name',
                'last_name',
                'name_suffix',
                'client_cuid',
                'address_line_1',
                'address_line_2',
                'city',
                'state',
                'postal_code',
                'mailing_label',
                'mailing_barcode',
                'mailing_barcode_symbology',
                'mailing_barcode_payload',
                'address_fingerprint',
            ])
            ->chunkById(500, function ($snapshots) use ($barcodes): void {
                foreach ($snapshots as $snapshot) {
                    $address = MailingAddress::normalizeFields([
                        'address_line_1' => $snapshot->address_line_1,
                        'address_line_2' => $snapshot->address_line_2,
                        'city' => $snapshot->city,
                        'state' => $snapshot->state,
                        'postal_code' => $snapshot->postal_code,
                    ]);
                    $name = trim(implode(' ', array_filter([
                        $snapshot->first_name,
                        $snapshot->middle_name,
                        $snapshot->last_name,
                        $snapshot->name_suffix,
                    ]))) ?: $snapshot->client_cuid;
                    $barcode = $barcodes->payloadForAddress($address['postal_code'] ?? null, $address['address_line_1'] ?? null);
                    $updates = [
                        ...$this->changedAddressFields($snapshot, $address),
                        'mailing_label' => MailingAddress::mailingLabel($name, $address),
                        'mailing_barcode' => $barcode['routing_code'] ?? null,
                        'mailing_barcode_symbology' => $barcode['symbology'] ?? null,
                        'mailing_barcode_payload' => $barcode !== null
                            ? json_encode($barcode, JSON_THROW_ON_ERROR)
                            : null,
                        'address_fingerprint' => MailingAddress::fingerprint($address),
                    ];

                    $updates = array_filter(
                        $updates,
                        fn ($value, $key): bool => $this->dbValue($snapshot->{$key} ?? null) !== $this->dbValue($value),
                        ARRAY_FILTER_USE_BOTH,
                    );

                    if ($updates === []) {
                        continue;
                    }

                    $updates['updated_at'] = now();

                    DB::table('client_profile_snapshots')
                        ->where('id', $snapshot->id)
                        ->update($updates);
                }
            });
    }

    /**
     * @param  array<string, mixed>  $address
     * @return array<string, mixed>
     */
    protected function changedAddressFields(object $record, array $address): array
    {
        $updates = [];

        foreach (['address_line_1', 'address_line_2', 'city', 'state', 'postal_code'] as $field) {
            if ($this->dbValue($record->{$field} ?? null) !== $this->dbValue($address[$field] ?? null)) {
                $updates[$field] = $address[$field] ?? null;
            }
        }

        return $updates;
    }

    protected function dbValue(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value !== '' ? $value : null;
    }
};
