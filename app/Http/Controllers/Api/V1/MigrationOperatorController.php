<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ManagedLetterTemplate;
use App\Models\MigrationOperatorCapture;
use App\Services\AuditTrail;
use App\Services\MigrationOperatorLetterTemplateImporter;
use App\Support\ClientName;
use App\Support\PhoneNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class MigrationOperatorController extends Controller
{
    public function ping(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user?->hasRole('owner_admin'), 403);

        return response()->json([
            'data' => [
                'name' => 'CreditSoft Migration Operator',
                'user' => [
                    'id' => $user->getKey(),
                    'name' => $user->name,
                    'email' => $user->email,
                ],
                'allowed_hosts' => array_values(config('creditsoft.migration_operator.allowed_hosts', [])),
                'capture_types' => array_values(config('creditsoft.migration_operator.capture_types', [])),
                'template_import' => [
                    'enabled' => true,
                ],
            ],
        ]);
    }

    public function storeCapture(Request $request, AuditTrail $auditTrail): JsonResponse
    {
        $user = $request->user();
        abort_unless($user?->hasRole('owner_admin'), 403);

        $validated = $request->validate([
            'source_system' => ['required', 'string', 'max:120'],
            'capture_type' => ['required', 'string', 'max:120'],
            'page_title' => ['nullable', 'string', 'max:255'],
            'page_url' => ['required', 'url', 'max:2048'],
            'operator_note' => ['nullable', 'string'],
            'html' => ['required', 'string'],
            'metadata' => ['nullable', 'array'],
        ]);

        if (! in_array($validated['capture_type'], config('creditsoft.migration_operator.capture_types', []), true)) {
            return response()->json([
                'message' => 'That capture type is not allowed for the migration operator.',
            ], 422);
        }

        $host = Str::lower((string) parse_url($validated['page_url'], PHP_URL_HOST));
        if (! $this->allowedHost($host)) {
            return response()->json([
                'message' => 'That host is not allowed for the migration operator.',
            ], 422);
        }

        $capture = MigrationOperatorCapture::query()->create([
            'user_id' => $user->getKey(),
            'source_system' => Str::lower($validated['source_system']),
            'capture_type' => $validated['capture_type'],
            'page_title' => $validated['page_title'] ?? null,
            'page_url' => $validated['page_url'],
            'operator_note' => $validated['operator_note'] ?? null,
            'content_html' => $validated['html'],
            'extracted_text' => $this->extractText($validated['html']),
            'status' => 'staged',
            'metadata' => array_replace_recursive($validated['metadata'] ?? [], [
                'source_host' => $host,
                'captured_via' => 'migration_operator',
                'captured_at' => now()->toIso8601String(),
                'user_agent' => (string) $request->userAgent(),
            ]),
        ]);

        $auditTrail->record(
            $user,
            'migration_operator.capture_staged',
            "Staged {$capture->capture_type} capture from {$host}.",
            $capture,
            [
                'capture_id' => $capture->getKey(),
                'source_system' => $capture->source_system,
                'capture_type' => $capture->capture_type,
                'page_url' => $capture->page_url,
            ],
        );

        return response()->json([
            'message' => 'Capture staged for migration review.',
            'data' => [
                'capture' => [
                    'id' => $capture->getKey(),
                    'source_system' => $capture->source_system,
                    'capture_type' => $capture->capture_type,
                    'page_title' => $capture->page_title,
                    'page_url' => $capture->page_url,
                    'status' => $capture->status,
                    'created_at' => optional($capture->created_at)?->toIso8601String(),
                ],
            ],
        ], 201);
    }

    public function importLetterTemplate(
        Request $request,
        AuditTrail $auditTrail,
        MigrationOperatorLetterTemplateImporter $importer,
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user?->hasRole('owner_admin'), 403);

        $validated = $request->validate([
            'capture_id' => ['nullable', 'integer', 'exists:migration_operator_captures,id'],
            'source_system' => ['nullable', 'string', 'max:120'],
            'capture_type' => ['nullable', 'string', 'max:120'],
            'page_title' => ['nullable', 'string', 'max:255'],
            'page_url' => ['nullable', 'url', 'max:2048'],
            'operator_note' => ['nullable', 'string'],
            'operator_notes' => ['nullable', 'string'],
            'label' => ['nullable', 'string', 'max:255'],
            'letter_type' => ['nullable', 'in:dispute,follow_up,escalation'],
            'legal_basis' => ['nullable'],
            'ai_focus' => ['nullable', 'string'],
            'content_template' => ['nullable', 'string'],
            'html' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
        ]);

        $capture = null;

        if (filled($validated['capture_id'] ?? null)) {
            $capture = MigrationOperatorCapture::query()->findOrFail($validated['capture_id']);
        }

        if (! $capture && blank($validated['html'] ?? null)) {
            return response()->json([
                'message' => 'HTML is required when importing without an existing staged capture.',
            ], 422);
        }

        $pageUrl = (string) ($validated['page_url'] ?? $capture?->page_url ?? '');
        $host = Str::lower((string) parse_url($pageUrl, PHP_URL_HOST));

        if ($host !== '' && ! $this->allowedHost($host)) {
            return response()->json([
                'message' => 'That host is not allowed for the migration operator.',
            ], 422);
        }

        if (! $capture) {
            $capture = MigrationOperatorCapture::query()->create([
                'user_id' => $user->getKey(),
                'source_system' => Str::lower((string) ($validated['source_system'] ?? 'legacy')),
                'capture_type' => (string) ($validated['capture_type'] ?? 'letter_detail'),
                'page_title' => $validated['page_title'] ?? null,
                'page_url' => $pageUrl !== '' ? $pageUrl : null,
                'operator_note' => $validated['operator_note'] ?? $validated['operator_notes'] ?? null,
                'content_html' => $validated['html'] ?? null,
                'extracted_text' => $this->extractText((string) ($validated['html'] ?? '')),
                'status' => 'staged',
                'metadata' => array_replace_recursive($validated['metadata'] ?? [], [
                    'source_host' => $host,
                    'captured_via' => 'migration_operator',
                    'captured_at' => now()->toIso8601String(),
                    'user_agent' => (string) $request->userAgent(),
                ]),
            ]);
        }

        $template = $importer->import($validated, $user, $capture);

        $auditTrail->record(
            $user,
            'migration_operator.letter_template_imported',
            "Imported letter template {$template->label}.",
            $template,
            [
                'capture_id' => $capture->getKey(),
                'template_key' => $template->key,
                'source_system' => $capture->source_system,
                'page_url' => $capture->page_url,
            ],
        );

        return response()->json([
            'message' => 'Letter template imported into CreditSoft.',
            'data' => [
                'template' => $this->templatePayload($template),
                'capture' => [
                    'id' => $capture->getKey(),
                    'status' => $capture->status,
                ],
            ],
        ], 201);
    }

    public function syncClientProfile(Request $request, AuditTrail $auditTrail): JsonResponse
    {
        $user = $request->user();
        abort_unless($user?->hasRole('owner_admin'), 403);

        $validated = $request->validate([
            'source_system' => ['required', 'string', 'max:120'],
            'page_title' => ['nullable', 'string', 'max:255'],
            'page_url' => ['required', 'url', 'max:2048'],
            'operator_note' => ['nullable', 'string'],
            'client' => ['required', 'array'],
            'client.fields' => ['nullable', 'array'],
            'client.raw_fields' => ['nullable', 'array'],
            'client.confidence' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'metadata' => ['nullable', 'array'],
        ]);

        $host = Str::lower((string) parse_url($validated['page_url'], PHP_URL_HOST));
        if (! $this->allowedHost($host)) {
            return response()->json([
                'message' => 'That host is not allowed for the migration operator.',
            ], 422);
        }

        $profile = $this->normalizedClientProfile((array) $validated['client']);

        if ($profile['first_name'] === '' && $profile['last_name'] === '' && $profile['email'] === '') {
            return response()->json([
                'message' => 'CreditSoft could not detect enough client data on this page. Open the client account/profile page and try Sync client data again.',
            ], 422);
        }

        $client = $this->findClientForSync($profile);
        $created = $client === null;
        $syncedAt = now()->toIso8601String();
        $payload = [
            'first_name' => $profile['first_name'] !== '' ? $profile['first_name'] : ($client?->first_name ?: 'Unknown'),
            'last_name' => $profile['last_name'] !== '' ? $profile['last_name'] : ($client?->last_name ?: 'Client'),
            'email' => $profile['email'] !== '' ? Str::lower($profile['email']) : ($client?->email ?: null),
            'secondary_email' => $profile['secondary_email'] !== '' ? Str::lower($profile['secondary_email']) : ($client?->secondary_email ?: null),
            'phone' => $profile['phone'] !== '' ? $profile['phone'] : ($client?->phone ?: null),
            'address_line_1' => $profile['address_line_1'] !== '' ? $profile['address_line_1'] : ($client?->address_line_1 ?: null),
            'address_line_2' => $profile['address_line_2'] !== '' ? $profile['address_line_2'] : ($client?->address_line_2 ?: null),
            'city' => $profile['city'] !== '' ? $profile['city'] : ($client?->city ?: null),
            'state' => $profile['state'] !== '' ? $this->normalizedState($profile['state']) : ($client?->state ?: null),
            'postal_code' => $profile['postal_code'] !== '' ? $profile['postal_code'] : ($client?->postal_code ?: null),
            'date_of_birth' => $profile['date_of_birth'] ?? $client?->date_of_birth,
            'ssn' => $profile['ssn'] !== '' ? $profile['ssn'] : ($client?->ssn ?: null),
            'status' => $profile['status'] !== '' ? $this->statusForClientSync($profile['status']) : ($client?->status ?: 'active_review'),
            'metadata' => array_replace_recursive($client?->metadata ?? [], [
                'imports' => [
                    'disputefox' => [
                        'companion_sync' => [
                            'synced_at' => $syncedAt,
                            'source_system' => Str::lower($validated['source_system']),
                            'source_page_url' => $validated['page_url'],
                            'source_page_title' => $validated['page_title'] ?? null,
                            'source_host' => $host,
                            'operator_note' => $validated['operator_note'] ?? null,
                            'confidence' => $profile['confidence'],
                            'field_values' => $this->safeFieldSnapshot($profile['raw_fields']),
                            'ssn_present' => $profile['ssn'] !== '',
                        ],
                    ],
                ],
            ]),
        ];

        if ($client) {
            $client->fill($payload);
            $client->save();
        } else {
            $client = Client::query()->create([
                ...$payload,
                'cuid' => 'c_'.Str::lower(Str::random(10)),
            ]);
        }

        $auditTrail->record(
            $user,
            $created ? 'migration_operator.client_created' : 'migration_operator.client_synced',
            sprintf('%s client profile %s from %s.', $created ? 'Created' : 'Synced', $client->display_name, $host),
            $client,
            [
                'client_id' => $client->getKey(),
                'client_cuid' => $client->cuid,
                'source_system' => Str::lower($validated['source_system']),
                'page_url' => $validated['page_url'],
                'created' => $created,
            ],
        );

        return response()->json([
            'message' => $created ? 'Client created from migration sync.' : 'Client updated from migration sync.',
            'data' => [
                'client' => [
                    'id' => $client->getKey(),
                    'cuid' => $client->cuid,
                    'display_name' => $client->display_name,
                    'email' => $client->email,
                    'phone' => $client->phone,
                    'status' => $client->status,
                    'created' => $created,
                    'synced_at' => $syncedAt,
                ],
            ],
        ], $created ? 201 : 200);
    }

    protected function allowedHost(string $host): bool
    {
        if ($host === '') {
            return false;
        }

        return collect(config('creditsoft.migration_operator.allowed_hosts', []))
            ->contains(fn ($allowed) => $host === Str::lower((string) $allowed));
    }

    protected function extractText(string $html): string
    {
        $text = trim((string) preg_replace('/\s+/', ' ', strip_tags($html)));

        return Str::limit($text, 50000, '');
    }

    /**
     * @param  array<string, mixed>  $client
     * @return array{
     *     first_name:string,last_name:string,email:string,secondary_email:string,phone:string,address_line_1:string,
     *     address_line_2:string,city:string,state:string,postal_code:string,date_of_birth:?Carbon,ssn:string,status:string,
     *     confidence:float,raw_fields:list<array<string, mixed>>
     * }
     */
    protected function normalizedClientProfile(array $client): array
    {
        $fields = (array) ($client['fields'] ?? []);
        $fullName = $this->clean($fields['full_name'] ?? '');
        [$firstFromFull, $lastFromFull] = $this->splitName($fullName);

        return [
            'first_name' => ClientName::normalizePart($fields['first_name'] ?? $firstFromFull) ?? '',
            'last_name' => ClientName::normalizePart($fields['last_name'] ?? $lastFromFull) ?? '',
            'email' => $this->clean($fields['email'] ?? ''),
            'secondary_email' => $this->clean($fields['secondary_email'] ?? ''),
            'phone' => PhoneNumber::normalize($fields['phone'] ?? '') ?? '',
            'address_line_1' => $this->clean($fields['address_line_1'] ?? ''),
            'address_line_2' => $this->clean($fields['address_line_2'] ?? ''),
            'city' => $this->clean($fields['city'] ?? ''),
            'state' => $this->clean($fields['state'] ?? ''),
            'postal_code' => $this->clean($fields['postal_code'] ?? ''),
            'date_of_birth' => $this->parseDate($this->clean($fields['date_of_birth'] ?? '')),
            'ssn' => preg_replace('/[^0-9]/', '', $this->clean($fields['ssn'] ?? '')) ?? '',
            'status' => $this->clean($fields['status'] ?? $fields['progress'] ?? ''),
            'confidence' => max(0.0, min(1.0, (float) ($client['confidence'] ?? 0))),
            'raw_fields' => array_values(array_filter((array) ($client['raw_fields'] ?? []), 'is_array')),
        ];
    }

    /**
     * @param  array<string, mixed>  $profile
     */
    protected function findClientForSync(array $profile): ?Client
    {
        $email = Str::lower($profile['email']);

        if ($email !== '') {
            $client = Client::query()->whereRaw('lower(email) = ?', [$email])->first();

            if ($client) {
                return $client;
            }
        }

        if ($profile['phone'] !== '') {
            $client = Client::query()->where('phone', $profile['phone'])->first();

            if ($client) {
                return $client;
            }
        }

        if ($profile['first_name'] !== '' && $profile['last_name'] !== '') {
            $matches = Client::query()
                ->where('first_name', $profile['first_name'])
                ->where('last_name', $profile['last_name'])
                ->get();

            if ($matches->count() === 1) {
                return $matches->first();
            }
        }

        return null;
    }

    /**
     * @return array{0:string,1:string}
     */
    protected function splitName(string $name): array
    {
        $name = $this->clean(preg_replace('/\s+/', ' ', $name) ?? '');

        if ($name === '') {
            return ['', ''];
        }

        $parts = preg_split('/\s+/', $name) ?: [];

        if (count($parts) === 1) {
            return [$parts[0], ''];
        }

        return [array_shift($parts) ?: '', implode(' ', $parts)];
    }

    protected function parseDate(string $value): ?Carbon
    {
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    protected function statusForClientSync(string $value): string
    {
        $normalized = Str::of($value)->lower()->replaceMatches('/[^a-z0-9]+/', ' ')->squish()->value();

        if (str_contains($normalized, 'complete') || str_contains($normalized, 'monitor') || str_contains($normalized, 'graduate')) {
            return 'monitoring';
        }

        if ($normalized !== '') {
            return 'active_review';
        }

        return 'intake';
    }

    protected function normalizedState(string $value): string
    {
        $value = $this->clean($value);

        return strlen($value) <= 3 ? Str::upper($value) : $value;
    }

    protected function clean(mixed $value): string
    {
        return trim((string) $value);
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     * @return list<array<string, mixed>>
     */
    protected function safeFieldSnapshot(array $fields): array
    {
        return collect($fields)
            ->map(function (array $field): array {
                $label = $this->clean($field['label'] ?? $field['name'] ?? $field['id'] ?? '');
                $key = Str::of($label)->lower()->value();
                $sensitive = str_contains($key, 'ssn')
                    || str_contains($key, 'social security')
                    || str_contains($key, 'password')
                    || str_contains($key, 'secret')
                    || str_contains($key, 'token');

                return [
                    'label' => $label,
                    'name' => $this->clean($field['name'] ?? ''),
                    'id' => $this->clean($field['id'] ?? ''),
                    'mapped_to' => $this->clean($field['mapped_to'] ?? ''),
                    'value' => $sensitive ? '[redacted]' : Str::limit($this->clean($field['value'] ?? ''), 500, ''),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function templatePayload(ManagedLetterTemplate $template): array
    {
        return [
            'id' => $template->getKey(),
            'key' => $template->key,
            'label' => $template->label,
            'letter_type' => $template->letter_type,
            'source_system' => $template->source_system === 'imported' ? 'Imported' : $template->source_system,
            'source_page_url' => null,
            'created_at' => optional($template->created_at)?->toIso8601String(),
        ];
    }
}
