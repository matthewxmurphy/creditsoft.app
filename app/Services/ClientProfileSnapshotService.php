<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientProfileSnapshot;
use App\Support\MailingAddress;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class ClientProfileSnapshotService
{
    public function __construct(
        protected PostalRoutingBarcodeService $postalRoutingBarcodes,
    ) {}

    /**
     * @var list<string>
     */
    public const TRACKED_FIELDS = [
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
        'ssn',
        'current_score',
    ];

    /**
     * @param  array<int, string>  $changedFields
     * @param  array<string, mixed>  $metadata
     * @param  array<string, mixed>  $payload
     */
    public function record(
        Client $client,
        string $source,
        array $changedFields = [],
        array $metadata = [],
        array $payload = [],
        ?CarbonInterface $recordedAt = null,
        bool $isCurrent = true,
    ): ClientProfileSnapshot {
        if ($isCurrent) {
            ClientProfileSnapshot::query()
                ->where('client_id', $client->getKey())
                ->where('is_current', true)
                ->update(['is_current' => false]);
        }

        $recordedAt ??= Carbon::now();
        $address = MailingAddress::normalizeFields([
            'address_line_1' => $client->address_line_1,
            'address_line_2' => $client->address_line_2,
            'city' => $client->city,
            'state' => $client->state,
            'postal_code' => $client->postal_code,
        ]);
        $mailingLabel = MailingAddress::mailingLabel($client->display_name, $address);
        $mailingBarcode = $this->postalRoutingBarcodes->forClient($client);

        return ClientProfileSnapshot::query()->create([
            'client_id' => $client->getKey(),
            'client_cuid' => (string) $client->cuid,
            'source' => $source,
            'source_label' => $this->sourceLabel($source),
            'event' => 'profile_snapshot',
            'is_current' => $isCurrent,
            'recorded_at' => $recordedAt,
            'first_name' => $client->first_name,
            'middle_name' => $client->middle_name,
            'last_name' => $client->last_name,
            'name_suffix' => $client->name_suffix,
            'email' => $client->email,
            'secondary_email' => $client->secondary_email,
            'phone' => $client->phone,
            'address_line_1' => $address['address_line_1'] ?? null,
            'address_line_2' => $address['address_line_2'] ?? null,
            'city' => $address['city'] ?? null,
            'state' => $address['state'] ?? null,
            'postal_code' => $address['postal_code'] ?? null,
            'country' => 'US',
            'date_of_birth' => $client->date_of_birth,
            'ssn_last_four' => $this->ssnLastFour($client),
            'current_score' => $client->current_score,
            'mailing_label' => $mailingLabel,
            'mailing_barcode' => $mailingBarcode['barcode'] ?? null,
            'mailing_barcode_symbology' => $mailingBarcode['symbology'] ?? null,
            'mailing_barcode_payload' => $mailingBarcode['payload'] ?? null,
            'address_fingerprint' => MailingAddress::fingerprint($address),
            'changed_fields' => array_values(array_unique($changedFields)),
            'payload' => $payload,
            'metadata' => $metadata,
        ]);
    }

    /**
     * @param  array<int, string>  $changedFields
     * @param  array<string, mixed>  $metadata
     * @param  array<string, mixed>  $payload
     */
    public function recordIfTrackedFieldsChanged(
        Client $client,
        array $changedFields,
        string $source,
        array $metadata = [],
        array $payload = [],
    ): ?ClientProfileSnapshot {
        $tracked = array_values(array_intersect($changedFields, self::TRACKED_FIELDS));

        if ($tracked === []) {
            return null;
        }

        return $this->record($client, $source, $tracked, $metadata, $payload);
    }

    public function mailingLabel(Client $client): ?string
    {
        return MailingAddress::mailingLabel($client->display_name, [
            'address_line_1' => $client->address_line_1,
            'address_line_2' => $client->address_line_2,
            'city' => $client->city,
            'state' => $client->state,
            'postal_code' => $client->postal_code,
        ]);
    }

    protected function sourceLabel(string $source): string
    {
        return match ($source) {
            'client_portal' => 'Client portal',
            'partner_api' => 'Partner API',
            'browser_companion' => 'Browser companion',
            'crm_automation' => 'CRM automation',
            'office' => 'Office',
            default => Str::of($source)->replace('_', ' ')->title()->value(),
        };
    }

    protected function addressFingerprint(Client $client): ?string
    {
        return MailingAddress::fingerprint([
            'address_line_1' => $client->address_line_1,
            'address_line_2' => $client->address_line_2,
            'city' => $client->city,
            'state' => $client->state,
            'postal_code' => $client->postal_code,
        ]);
    }

    protected function ssnLastFour(Client $client): ?string
    {
        $ssn = preg_replace('/\D+/', '', (string) ($client->ssn ?? '')) ?? '';

        return $ssn !== '' ? Str::substr($ssn, -4) : null;
    }
}
