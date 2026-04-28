<?php

namespace App\Services;

use App\Models\Client;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class DisputeFoxClientImporter
{
    public function __construct(
        protected ClientAssignmentService $assignments,
    ) {}

    /**
     * @return array{
     *     rows:int,
     *     created:int,
     *     updated:int,
     *     skipped:int,
     *     clients:list<int>,
     *     headers:list<string>
     * }
     */
    public function import(UploadedFile $file, array $assignment = []): array
    {
        $sheet = $this->readWorkbook($file);
        $rows = $sheet['rows'];

        if ($rows === []) {
            throw new RuntimeException('The DisputeFox export did not contain any client rows to import.');
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $clientIds = [];
        $importedAt = now()->toIso8601String();
        $sourceFile = $file->getClientOriginalName() ?: 'disputefox-export.xlsx';

        foreach (array_values($rows) as $rowIndex => $row) {
            $firstName = $this->clean($row['first_name'] ?? null);
            $lastName = $this->clean($row['last_name'] ?? null);

            if ($firstName === '' && $lastName === '') {
                $skipped++;

                continue;
            }

            $email = Str::lower($this->clean($row['email'] ?? null));
            $agent = $this->clean($row['agent'] ?? null);
            $salesRep = $this->clean($row['sales_rep'] ?? null);
            $address = $this->clean($row['address'] ?? $row['address_line_1'] ?? null);
            $addressLine1 = $this->clean($row['address_line_1'] ?? $address);
            $addressLine2 = $this->clean($row['address_line_2'] ?? null);
            $city = $this->clean($row['city'] ?? null);
            $state = $this->normalizedState($this->clean($row['state'] ?? null));
            $postalCode = $this->clean($row['postal_code'] ?? null);
            $phone = $this->clean($row['phone'] ?? null);
            $secondaryEmail = Str::lower($this->clean($row['secondary_email'] ?? null));
            $dateOfBirth = $this->parseDateOfBirth($this->clean($row['date_of_birth'] ?? null));
            $ssn = $this->normalizedSsn($this->clean($row['ssn'] ?? null));
            $progressRaw = $this->clean($row['progress'] ?? null);
            $progressValue = $this->progressValue($progressRaw);
            $signature = $this->signatureForRow([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'agent' => $agent,
                'address_line_1' => $addressLine1,
                'address_line_2' => $addressLine2,
                'city' => $city,
                'address' => $address,
                'state' => $state,
                'postal_code' => $postalCode,
                'phone' => $phone,
                'secondary_email' => $secondaryEmail,
                'date_of_birth' => $dateOfBirth?->toDateString() ?? '',
                'ssn' => $ssn,
                'sales_rep' => $salesRep,
                'progress' => $progressRaw,
            ]);

            $client = $this->findExistingClient(
                firstName: $firstName,
                lastName: $lastName,
                email: $email,
                signature: $signature,
            );
            $matchedOwnerId = $this->assignments->matchUserId([$agent, $salesRep]);

            $payload = [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email !== '' ? $email : ($client?->email ?: null),
                'secondary_email' => $secondaryEmail !== '' ? $secondaryEmail : ($client?->secondary_email ?: null),
                'phone' => $phone !== '' ? $phone : ($client?->phone ?: null),
                'address_line_1' => $addressLine1 !== '' ? $addressLine1 : ($client?->address_line_1 ?: null),
                'address_line_2' => $addressLine2 !== '' ? $addressLine2 : ($client?->address_line_2 ?: null),
                'city' => $city !== '' ? $city : ($client?->city ?: null),
                'state' => $state !== '' ? $state : ($client?->state ?: null),
                'postal_code' => $postalCode !== '' ? $postalCode : ($client?->postal_code ?: null),
                'date_of_birth' => $dateOfBirth ?? $client?->date_of_birth,
                'ssn' => $ssn !== '' ? $ssn : ($client?->ssn ?: null),
                'status' => $this->statusForProgress($progressValue, $progressRaw),
                'assigned_to' => $this->resolvedAssignedTo(
                    $assignment,
                    $matchedOwnerId,
                    $rowIndex,
                    $client?->assigned_to,
                ),
                'metadata' => $this->mergeMetadata(
                    $client?->metadata ?? [],
                    [
                        'imports' => [
                            'disputefox' => [
                                'source_signature' => $signature,
                                'source_file' => $sourceFile,
                                'imported_at' => $importedAt,
                                'headers' => $sheet['headers'],
                                'agent' => $agent !== '' ? $agent : null,
                                'sales_rep' => $salesRep !== '' ? $salesRep : null,
                                'secondary_email' => $secondaryEmail !== '' ? $secondaryEmail : null,
                                'phone' => $phone !== '' ? $phone : null,
                                'address_line_1' => $addressLine1 !== '' ? $addressLine1 : null,
                                'address_line_2' => $addressLine2 !== '' ? $addressLine2 : null,
                                'city' => $city !== '' ? $city : null,
                                'address' => $address !== '' ? $address : null,
                                'state' => $state !== '' ? $state : null,
                                'postal_code' => $postalCode !== '' ? $postalCode : null,
                                'date_of_birth' => $dateOfBirth?->toDateString(),
                                'ssn_present' => $ssn !== '',
                                'progress' => [
                                    'raw' => $progressRaw !== '' ? $progressRaw : null,
                                    'value' => $progressValue,
                                ],
                                'raw_row' => is_array($row['_raw'] ?? null) ? $row['_raw'] : [],
                            ],
                        ],
                    ],
                ),
            ];

            if ($client) {
                $client->fill($payload);
                $client->save();
                $updated++;
            } else {
                $client = Client::create([
                    ...$payload,
                    'cuid' => 'c_'.Str::lower(Str::random(10)),
                ]);
                $created++;
            }

            $clientIds[] = $client->getKey();
        }

        return [
            'rows' => count($rows),
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'clients' => collect($clientIds)->unique()->values()->all(),
            'headers' => $sheet['headers'],
        ];
    }

    /**
     * @param  array<string, mixed>  $assignment
     */
    protected function resolvedAssignedTo(
        array $assignment,
        ?int $matchedOwnerId,
        int $rowIndex,
        ?int $existingAssignedTo,
    ): ?int {
        $mode = $assignment['mode'] ?? null;

        if (! is_string($mode) || $mode === '') {
            return $matchedOwnerId ?: $existingAssignedTo;
        }

        if ($mode === ClientAssignmentService::MODE_SOURCE_MATCH && $matchedOwnerId) {
            return $matchedOwnerId;
        }

        return $this->assignments->resolveForBatchRow(
            $mode,
            isset($assignment['assigned_to']) ? (int) $assignment['assigned_to'] : null,
            is_array($assignment['assignment_user_ids'] ?? null) ? $assignment['assignment_user_ids'] : [],
            $rowIndex,
        );
    }

    /**
     * @return array{rows:list<array<string, mixed>>, headers:list<string>}
     */
    protected function readWorkbook(UploadedFile $file): array
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('ZipArchive is required to import a DisputeFox XLSX export.');
        }

        $realPath = $file->getRealPath();

        if (! $realPath) {
            throw new RuntimeException('Could not open the uploaded DisputeFox export.');
        }

        $zip = new ZipArchive();

        if ($zip->open($realPath) !== true) {
            throw new RuntimeException('The uploaded file is not a readable XLSX workbook.');
        }

        try {
            $worksheetPath = $this->firstWorksheetPath($zip);

            if (! $worksheetPath) {
                throw new RuntimeException('No worksheet was found in the uploaded XLSX export.');
            }

            $worksheetXml = $zip->getFromName($worksheetPath);

            if (! is_string($worksheetXml) || trim($worksheetXml) === '') {
                throw new RuntimeException('The DisputeFox worksheet could not be read.');
            }

            $sharedStrings = $this->sharedStrings($zip);
            $rows = $this->rowsFromWorksheetXml($worksheetXml, $sharedStrings);
            $headerRowIndex = $this->detectHeaderRowIndex($rows);

            if ($headerRowIndex === null) {
                throw new RuntimeException('Could not find the expected DisputeFox headers in the XLSX export.');
            }

            $headerMap = $this->headerMap($rows[$headerRowIndex]);
            $rawHeaders = $this->rawHeaders($rows[$headerRowIndex]);

            if ($headerMap === []) {
                throw new RuntimeException('The DisputeFox export headers were empty.');
            }

            $headers = collect($rawHeaders)
                ->filter()
                ->values()
                ->all();
            $normalizedRows = [];

            foreach (array_slice($rows, $headerRowIndex + 1) as $row) {
                $normalized = [];
                $rawRow = [];

                foreach ($rawHeaders as $columnIndex => $label) {
                    if ($label === '') {
                        continue;
                    }

                    $value = isset($row[$columnIndex]) ? $this->clean($row[$columnIndex]) : null;

                    if ($value !== null && $value !== '') {
                        $rawRow[$label] = $value;
                    }
                }

                foreach ($headerMap as $columnIndex => $key) {
                    $normalized[$key] = isset($row[$columnIndex]) ? $this->clean($row[$columnIndex]) : null;
                }

                if (collect($normalized)->every(fn ($value) => $value === null || $value === '')) {
                    continue;
                }

                $normalized['_raw'] = $rawRow;
                $normalizedRows[] = $normalized;
            }

            return [
                'rows' => $normalizedRows,
                'headers' => $headers,
            ];
        } finally {
            $zip->close();
        }
    }

    protected function firstWorksheetPath(ZipArchive $zip): ?string
    {
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);

            if (is_string($name) && preg_match('#^xl/worksheets/[^/]+\.xml$#', $name) === 1) {
                return $name;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    protected function sharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');

        if (! is_string($xml) || trim($xml) === '') {
            return [];
        }

        $xpath = $this->xpath($xml);
        $strings = [];

        foreach ($xpath->query('/x:sst/x:si') as $node) {
            $strings[] = trim($this->concatenatedText($xpath->query('.//x:t', $node)));
        }

        return $strings;
    }

    /**
     * @param  list<string>  $sharedStrings
     * @return list<array<int, string>>
     */
    protected function rowsFromWorksheetXml(string $xml, array $sharedStrings): array
    {
        $xpath = $this->xpath($xml);
        $rows = [];

        foreach ($xpath->query('/x:worksheet/x:sheetData/x:row') as $rowNode) {
            $row = [];

            foreach ($xpath->query('./x:c', $rowNode) as $cellNode) {
                $reference = (string) $cellNode->attributes?->getNamedItem('r')?->nodeValue;

                if ($reference === '') {
                    continue;
                }

                $column = preg_replace('/\d+/', '', $reference) ?? '';
                $columnIndex = $this->columnIndex($column);

                if ($columnIndex < 0) {
                    continue;
                }

                $cellType = (string) $cellNode->attributes?->getNamedItem('t')?->nodeValue;
                $value = match ($cellType) {
                    'inlineStr' => trim($this->concatenatedText($xpath->query('.//x:t', $cellNode))),
                    's' => $sharedStrings[(int) $xpath->evaluate('string(./x:v)', $cellNode)] ?? '',
                    'b' => $xpath->evaluate('string(./x:v)', $cellNode) === '1' ? 'true' : 'false',
                    default => trim((string) $xpath->evaluate('string(./x:v)', $cellNode)),
                };

                $row[$columnIndex] = $value;
            }

            if ($row !== []) {
                ksort($row);
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * @param  list<array<int, string>>  $rows
     */
    protected function detectHeaderRowIndex(array $rows): ?int
    {
        foreach ($rows as $index => $row) {
            $normalized = collect($row)
                ->map(fn ($value) => $this->normalizeHeader((string) $value))
                ->filter()
                ->values()
                ->all();

            if (
                in_array('first name', $normalized, true)
                && in_array('last name', $normalized, true)
                && (in_array('email', $normalized, true) || in_array('email address', $normalized, true))
            ) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @param  array<int, string>  $headerRow
     * @return array<int, string>
     */
    protected function headerMap(array $headerRow): array
    {
        $map = [];

        foreach ($headerRow as $columnIndex => $value) {
            $normalized = $this->normalizeHeader($value);

            $key = match ($normalized) {
                'first name' => 'first_name',
                'last name' => 'last_name',
                'email', 'email address' => 'email',
                'additional email' => 'secondary_email',
                'agent' => 'agent',
                'address', 'current address', 'address line 1' => 'address_line_1',
                'address line 2' => 'address_line_2',
                'city', 'current city' => 'city',
                'state' => 'state',
                'zip', 'zipcode', 'zip code', 'postal code' => 'postal_code',
                'sales rep' => 'sales_rep',
                'cell phone', 'phone', 'cell phone only for sms security codes' => 'phone',
                'date of birth', 'date of birth*', 'date of birth *', 'dob', 'date of birth required', 'date of birth required *' => 'date_of_birth',
                'ssn', 'ssns', 'social security', 'social security number' => 'ssn',
                'progress' => 'progress',
                default => null,
            };

            if ($key !== null) {
                $map[$columnIndex] = $key;
            }
        }

        return $map;
    }

    /**
     * @param  array<int, string>  $headerRow
     * @return array<int, string>
     */
    protected function rawHeaders(array $headerRow): array
    {
        $headers = [];

        foreach ($headerRow as $columnIndex => $value) {
            $headers[$columnIndex] = $this->clean($value);
        }

        return $headers;
    }

    /**
     * @return array<string, int>
     */
    protected function userDirectory(): array
    {
        return User::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->reduce(function (array $carry, User $user): array {
                $normalized = $this->normalizePerson($user->name);

                if ($normalized !== '' && ! isset($carry[$normalized])) {
                    $carry[$normalized] = $user->getKey();
                }

                return $carry;
            }, []);
    }

    /**
     * @param  list<string>  $candidates
     * @param  array<string, int>  $userDirectory
     */
    protected function matchedUserId(array $candidates, array $userDirectory): ?int
    {
        foreach ($candidates as $candidate) {
            $normalized = $this->normalizePerson($candidate);

            if ($normalized !== '' && isset($userDirectory[$normalized])) {
                return $userDirectory[$normalized];
            }
        }

        return null;
    }

    protected function findExistingClient(string $firstName, string $lastName, string $email, string $signature): ?Client
    {
        if ($email !== '') {
            $emailMatch = Client::query()
                ->whereRaw('lower(email) = ?', [$email])
                ->first();

            if ($emailMatch) {
                return $emailMatch;
            }
        }

        $nameMatches = Client::query()
            ->where('first_name', $firstName)
            ->where('last_name', $lastName)
            ->get();

        if ($nameMatches->count() === 1) {
            return $nameMatches->first();
        }

        return $nameMatches->first(function (Client $client) use ($signature): bool {
            return data_get($client->metadata, 'imports.disputefox.source_signature') === $signature;
        });
    }

    /**
     * @param  array<string, mixed>  $existing
     * @param  array<string, mixed>  $incoming
     * @return array<string, mixed>
     */
    protected function mergeMetadata(array $existing, array $incoming): array
    {
        return array_replace_recursive($existing, $incoming);
    }

    protected function statusForProgress(?float $progressValue, string $progressRaw): string
    {
        if ($progressValue !== null && $progressValue >= 1.0) {
            return 'monitoring';
        }

        if ($progressValue !== null && $progressValue > 0) {
            return 'active_review';
        }

        if ($progressRaw !== '') {
            return 'active_review';
        }

        return 'intake';
    }

    protected function progressValue(string $progressRaw): ?float
    {
        if ($progressRaw === '' || ! is_numeric($progressRaw)) {
            return null;
        }

        return (float) $progressRaw;
    }

    /**
     * @param  array<string, string>  $row
     */
    protected function signatureForRow(array $row): string
    {
        return sha1(json_encode($row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '');
    }

    protected function clean(?string $value): string
    {
        return trim((string) $value);
    }

    protected function parseDateOfBirth(string $value): ?Carbon
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

    protected function normalizedSsn(string $value): string
    {
        if ($value === '') {
            return '';
        }

        return preg_replace('/[^0-9]/', '', $value) ?? '';
    }

    protected function normalizedState(string $value): string
    {
        if ($value === '') {
            return '';
        }

        return strlen($value) <= 3 ? Str::upper($value) : $value;
    }

    protected function normalizeHeader(string $value): string
    {
        return Str::of($value)
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->trim()
            ->value();
    }

    protected function normalizePerson(string $value): string
    {
        return Str::of($value)
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->squish()
            ->value();
    }

    protected function columnIndex(string $letters): int
    {
        if ($letters === '') {
            return -1;
        }

        $letters = strtoupper($letters);
        $index = 0;

        foreach (str_split($letters) as $letter) {
            $index = ($index * 26) + (ord($letter) - 64);
        }

        return $index - 1;
    }

    protected function xpath(string $xml): \DOMXPath
    {
        $document = new \DOMDocument();
        $document->loadXML($xml);

        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        return $xpath;
    }

    /**
     * @param  \DOMNodeList<int, \DOMNode>|false  $nodes
     */
    protected function concatenatedText(\DOMNodeList|false $nodes): string
    {
        if ($nodes === false) {
            return '';
        }

        $values = [];

        foreach ($nodes as $node) {
            $values[] = $node->textContent;
        }

        return implode('', $values);
    }
}
