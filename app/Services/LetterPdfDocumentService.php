<?php

namespace App\Services;

use App\Models\ClientDocument;
use App\Models\LetterDraft;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class LetterPdfDocumentService
{
    public function __construct(
        protected SimplePdfWriter $pdfWriter,
        protected LetterDraftPresentationService $presentation,
    ) {
    }

    public function ensurePdf(LetterDraft $letter, ?User $user = null): ClientDocument
    {
        $letter->loadMissing('client', 'reportingCycle');

        $existing = $letter->client->documents()
            ->where('category', 'letter_pdf')
            ->get()
            ->first(fn (ClientDocument $document) => (int) data_get($document->metadata, 'letter_draft_id', 0) === $letter->getKey());

        $title = $this->presentation->title($letter);
        $content = $this->presentation->content($letter);
        $pdfProfile = $this->pdfProfile($letter);

        if ($existing && filled($existing->file_path) && File::exists($existing->file_path)) {
            $existingPdf = (string) File::get($existing->file_path);

            if (! $this->needsRefresh($existing, $letter, $existingPdf, $title, $content, $pdfProfile)) {
                return $existing;
            }
        }

        $directory = rtrim((string) config('creditsoft.document_path'), DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR.$letter->client->cuid;
        File::ensureDirectoryExists($directory);

        $fileName = now()->format('YmdHis').'-'.Str::slug($title ?: 'letter-draft').'.pdf';
        $filePath = $directory.DIRECTORY_SEPARATOR.$fileName;
        $pdf = $this->pdfWriter->renderLetter(
            $title,
            $this->renderMetaLines($letter),
            $content,
            $pdfProfile,
        );

        File::put($filePath, $pdf);

        $payload = [
            'user_id' => $user?->getKey(),
            'title' => $title,
            'category' => 'letter_pdf',
            'notes' => 'CreditSoft review packet PDF.',
            'file_name' => $fileName,
            'file_path' => $filePath,
            'mime_type' => 'application/pdf',
            'file_size' => filesize($filePath) ?: strlen($pdf),
            'portal_visible' => false,
            'metadata' => [
                'source' => 'letters',
                'letter_draft_id' => $letter->getKey(),
                'letter_type' => $letter->letter_type,
                'pdf_profile' => $pdfProfile,
            ],
            'uploaded_at' => now(),
        ];

        if ($existing) {
            if (filled($existing->file_path) && $existing->file_path !== $filePath && File::exists($existing->file_path)) {
                File::delete($existing->file_path);
            }

            $existing->update($payload);

            return $existing->fresh();
        }

        return $letter->client->documents()->create([
            'reporting_cycle_id' => $letter->reporting_cycle_id,
            ...$payload,
        ]);
    }

    /**
     * @return list<string>
     */
    protected function renderMetaLines(LetterDraft $letter): array
    {
        return array_values(array_filter([
            now()->timezone(config('app.timezone'))->format('F j, Y'),
        ]));
    }

    protected function needsRefresh(
        ClientDocument $document,
        LetterDraft $letter,
        string $existingPdf,
        string $title,
        string $content,
        array $pdfProfile,
    ): bool
    {
        if ((array) data_get($document->metadata, 'pdf_profile', []) !== $pdfProfile) {
            return true;
        }

        if ($document->updated_at?->lt($letter->updated_at)) {
            return true;
        }

        if (Str::contains($existingPdf, [
            'Client:',
            'File Reference:',
            'Dispute Type:',
            'Total Accounts Under Dispute:',
            '[Client Services on behalf of Client',
        ])) {
            return true;
        }

        if (! Str::contains($existingPdf, Str::upper($title))) {
            return true;
        }

        if (Str::contains($content, 'Sincerely, '.$letter->client->display_name)) {
            return ! Str::contains($existingPdf, $letter->client->display_name);
        }

        return false;
    }

    /**
     * @return array{style:string,typo_rate:string}
     */
    protected function pdfProfile(LetterDraft $letter): array
    {
        $style = (string) data_get($letter->ai_metadata, 'pdf_profile.style', 'typed');
        $typoRate = (string) data_get($letter->ai_metadata, 'pdf_profile.typo_rate', 'none');

        return [
            'style' => in_array($style, ['typed', 'typed_typos', 'handwritten_right', 'handwritten_left'], true) ? $style : 'typed',
            'typo_rate' => in_array($typoRate, ['none', 'light', 'medium'], true) ? $typoRate : 'none',
        ];
    }
}
