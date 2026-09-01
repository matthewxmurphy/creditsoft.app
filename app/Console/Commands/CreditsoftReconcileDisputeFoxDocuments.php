<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Services\DisputeFoxDocumentInboxService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('creditsoft:disputefox:reconcile-documents {--client= : Limit to a CreditSoft client id or cuid.} {--delete-source : Delete matched files from the inbox after attaching.} {--keep-source : Keep matched files in the inbox after attaching. Deprecated; kept for old scripts.} {--no-prune-tiny-previews : Keep tiny DisputeFox preview images in the inbox.}')]
#[Description('Attach locally downloaded DisputeFox files to imported client document metadata.')]
class CreditsoftReconcileDisputeFoxDocuments extends Command
{
    public function handle(DisputeFoxDocumentInboxService $inbox): int
    {
        $client = $this->clientFromOption();
        $deleteSource = (bool) $this->option('delete-source') && ! (bool) $this->option('keep-source');
        $stats = $inbox->reconcile(
            client: $client,
            deleteSource: $deleteSource,
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
