<?php

namespace App\Services;

use App\Models\CaseBrief;
use App\Models\ClientDocument;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class BriefPdfDocumentService
{
    public function __construct(protected SimplePdfWriter $pdfWriter)
    {
    }

    public function ensurePdf(CaseBrief $brief, ?User $user = null): ClientDocument
    {
        $brief->loadMissing('client', 'reportingCycle');

        $existing = $brief->client->documents()
            ->where('category', 'brief_pdf')
            ->get()
            ->first(fn (ClientDocument $document) => (int) data_get($document->metadata, 'brief_id', 0) === $brief->getKey());

        if ($existing && filled($existing->file_path) && File::exists($existing->file_path)) {
            return $existing;
        }

        $directory = rtrim((string) config('creditsoft.document_path'), DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR.$brief->client->cuid;
        File::ensureDirectoryExists($directory);

        $fileName = now()->format('YmdHis').'-'.Str::slug($brief->title ?: 'case-brief').'.pdf';
        $filePath = $directory.DIRECTORY_SEPARATOR.$fileName;
        $pdf = $this->pdfWriter->renderLetter(
            $brief->title ?: 'CreditSoft case brief',
            array_values(array_filter([
                'Client: '.$brief->client->display_name,
                $brief->reportingCycle?->cycle_label ? 'Cycle: '.$brief->reportingCycle->cycle_label : null,
                'Period: '.Str::headline($brief->period),
                'Status: '.($brief->approved_at ? 'Approved for release' : 'Waiting for review'),
                'Prepared: '.now()->timezone(config('app.timezone'))->format('F j, Y g:i:s A T'),
            ])),
            $brief->content ?? '',
        );

        File::put($filePath, $pdf);

        $payload = [
            'user_id' => $user?->getKey(),
            'title' => $brief->title,
            'category' => 'brief_pdf',
            'notes' => 'CreditSoft review packet PDF.',
            'file_name' => $fileName,
            'file_path' => $filePath,
            'mime_type' => 'application/pdf',
            'file_size' => filesize($filePath) ?: strlen($pdf),
            'portal_visible' => false,
            'metadata' => [
                'source' => 'briefs',
                'brief_id' => $brief->getKey(),
                'period' => $brief->period,
            ],
            'uploaded_at' => now(),
        ];

        if ($existing) {
            $existing->update($payload);

            return $existing->fresh();
        }

        return $brief->client->documents()->create([
            'reporting_cycle_id' => $brief->reporting_cycle_id,
            ...$payload,
        ]);
    }
}
