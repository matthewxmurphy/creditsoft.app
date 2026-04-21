<?php

namespace App\Services;

use App\Models\BrowserCapture;
use App\Models\Client;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

class BrowserCaptureCleanupService
{
    /**
     * @return array{extra_count:int,group_count:int}
     */
    public function duplicateSummary(Client $client): array
    {
        $groups = $this->duplicateGroups($client);

        return [
            'extra_count' => $groups->sum(fn (Collection $group) => max($group->count() - 1, 0)),
            'group_count' => $groups->count(),
        ];
    }

    /**
     * @return Collection<int, BrowserCapture>
     */
    public function pruneDuplicates(Client $client): Collection
    {
        $deleted = collect();

        $this->duplicateGroups($client)->each(function (Collection $group) use ($deleted): void {
            $group
                ->sortByDesc(fn (BrowserCapture $capture) => $capture->imported_at?->getTimestamp() ?? 0)
                ->values()
                ->slice(1)
                ->each(function (BrowserCapture $capture) use ($deleted): void {
                    $this->deleteCapture($capture);
                    $deleted->push($capture);
                });
        });

        return $deleted->values();
    }

    public function deleteCapture(BrowserCapture $capture): void
    {
        $capture->delete();
    }

    public function purgeCapture(BrowserCapture $capture): void
    {
        if (filled($capture->file_path) && File::exists($capture->file_path)) {
            File::delete($capture->file_path);
        }

        $capture->forceDelete();
    }

    /**
     * @return Collection<int, Collection<int, BrowserCapture>>
     */
    protected function duplicateGroups(Client $client): Collection
    {
        return $client->browserCaptures()
            ->get()
            ->groupBy(fn (BrowserCapture $capture) => $this->signature($capture))
            ->filter(fn (Collection $group) => $group->count() > 1)
            ->values();
    }

    protected function signature(BrowserCapture $capture): string
    {
        $day = $capture->imported_at?->toDateString() ?? 'unknown-day';
        $providerKey = (string) data_get($capture->metadata, 'provider_key', '');
        $importProfile = (string) data_get($capture->metadata, 'import_profile', '');
        $contentFingerprint = sha1(trim((string) ($capture->content_html ?? $capture->extracted_text ?? '')));

        return implode('|', [
            $capture->client_id,
            $capture->reporting_cycle_id ?? 'no-cycle',
            $day,
            $capture->source_type,
            $providerKey,
            $importProfile,
            mb_strtolower(trim((string) $capture->page_title)),
            mb_strtolower(trim((string) $capture->page_url)),
            $contentFingerprint,
        ]);
    }
}
