<?php

use App\Services\PostalRoutingBarcodeService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $barcodes = app(PostalRoutingBarcodeService::class);
        $lastId = 0;

        do {
            $snapshots = DB::table('client_profile_snapshots as snapshots')
                ->leftJoin('clients', 'clients.id', '=', 'snapshots.client_id')
                ->where('snapshots.id', '>', $lastId)
                ->whereNull('snapshots.mailing_barcode')
                ->orderBy('snapshots.id')
                ->limit(500)
                ->get([
                    'snapshots.id',
                    'snapshots.address_line_1',
                    'snapshots.postal_code',
                    'clients.address_line_1 as client_address_line_1',
                    'clients.postal_code as client_postal_code',
                ]);

            foreach ($snapshots as $snapshot) {
                $lastId = max($lastId, (int) $snapshot->id);
                $payload = $barcodes->payloadForAddress(
                    $snapshot->postal_code ?: $snapshot->client_postal_code,
                    $snapshot->address_line_1 ?: $snapshot->client_address_line_1,
                );

                if ($payload === null) {
                    continue;
                }

                DB::table('client_profile_snapshots')
                    ->where('id', $snapshot->id)
                    ->update([
                        'mailing_barcode' => $payload['routing_code'],
                        'mailing_barcode_symbology' => $payload['symbology'],
                        'mailing_barcode_payload' => json_encode($payload, JSON_THROW_ON_ERROR),
                        'updated_at' => now(),
                    ]);
            }
        } while ($snapshots->isNotEmpty());
    }

    public function down(): void
    {
        DB::table('client_profile_snapshots')
            ->whereIn('mailing_barcode_symbology', [
                'usps-delivery-point-routing-code',
                'usps-zip4-routing-code',
                'usps-zip-routing-code',
            ])
            ->update([
                'mailing_barcode' => null,
                'mailing_barcode_symbology' => null,
                'mailing_barcode_payload' => null,
                'updated_at' => now(),
            ]);
    }
};
