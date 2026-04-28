<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Services\DisputeFoxDocumentInboxService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('creditsoft:disputefox:reconcile-documents {--client= : Limit to a CreditSoft client id or cuid.} {--keep-source : Keep matched files in the inbox after attaching.} {--no-prune-tiny-previews : Keep tiny DisputeFox preview images in the inbox.}')]
#[Description('Attach locally downloaded DisputeFox files to imported client document metadata.')]
class CreditsoftReconcileDisputeFoxDocuments extends Command
{
    public function handle(DisputeFoxDocumentInboxService $inbox): int
    {
        $client = $this->clientFromOption();
        $stats = $inbox->reconcile(
            client: $client,
            deleteSource: ! (bool) $this->option('keep-source'),
            pruneTinyPreviews: ! (bool) $this->option('no-prune-tiny-previews')
                && (bool) config('creditsoft.disputefox_document_inbox_prune_tiny_previews', true),
        );

        $this->info('DisputeFox document inbox reconciled.');
        $this->table(
            ['Inbox files', 'Documents checked', 'Attached', 'Missing', 'Deleted sources', 'Pruned previews'],
            [[
                $stats['inbox_files'],
                $stats['documents_checked'],
                $stats['attached'],
                $stats['missing'],
                $stats['deleted_sources'],
                $stats['pruned_tiny_previews'],
            ]],
        );

        return self::SUCCESS;
    }

    protected function clientFromOption(): ?Client
    {
        $value = trim((string) $this->option('client'));

        if ($value === '') {
            return null;
        }

        return Client::query()
            ->where('id', ctype_digit($value) ? (int) $value : 0)
            ->orWhere('cuid', $value)
            ->firstOrFail();
    }
}
