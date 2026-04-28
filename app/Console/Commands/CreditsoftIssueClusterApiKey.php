<?php

namespace App\Console\Commands;

use App\Services\CreditsoftClusterApiKeyService;
use Illuminate\Console\Command;

class CreditsoftIssueClusterApiKey extends Command
{
    protected $signature = 'creditsoft:api-key:issue-cluster
        {name : Human label for the key, such as "Credit Essence Website Bridge"}
        {--user= : Owner email that must exist on every server node. Defaults to CREDITSOFT_OWNER_EMAIL}
        {--ability=* : Ability to grant. Repeat for multiple abilities. Defaults to partner_api}
        {--keep-existing : Do not revoke older active keys with the same name for this owner}
        {--show-token : Print the raw token once for installing into an external website bridge}';

    protected $description = 'Issue one API key and sync it to every configured cluster server node.';

    public function handle(CreditsoftClusterApiKeyService $clusterApiKeyService): int
    {
        $name = trim((string) $this->argument('name'));
        $userEmail = strtolower(trim((string) ($this->option('user') ?: config('creditsoft.access.owner.email', ''))));
        $abilities = collect((array) $this->option('ability'))
            ->map(fn (mixed $ability): string => trim((string) $ability))
            ->filter()
            ->values()
            ->all();

        if ($userEmail === '') {
            $this->error('No owner email is configured. Set CREDITSOFT_OWNER_EMAIL or pass --user.');

            return self::FAILURE;
        }

        if ($abilities === []) {
            $abilities = ['partner_api'];
        }

        $result = $clusterApiKeyService->issueClusterKey(
            name: $name,
            userEmail: $userEmail,
            abilities: $abilities,
            revokeExisting: ! (bool) $this->option('keep-existing'),
        );

        $local = $result['local'];
        $this->info(sprintf(
            'Installed API key "%s" on this node for %s.',
            $local['name'] ?? $name,
            $local['user_email'] ?? $userEmail,
        ));
        $this->line(sprintf('Token fingerprint: %s...%s', $local['token_prefix'] ?? 'unknown', $local['token_suffix'] ?? 'unknown'));
        $this->line('Abilities: '.implode(', ', (array) ($local['abilities'] ?? $abilities)));

        foreach ((array) $result['messages'] as $message) {
            $this->line($message);
        }

        foreach ((array) $result['deliveries'] as $delivery) {
            $status = (string) ($delivery['status'] ?? 'unknown');
            $label = (string) ($delivery['label'] ?? 'peer');
            $detail = (string) ($delivery['message'] ?? $delivery['remote_status'] ?? '');

            $this->line(sprintf(
                'Cluster peer %s: %s%s',
                $label,
                $status,
                $detail !== '' ? " ({$detail})" : '',
            ));
        }

        if ((bool) $this->option('show-token')) {
            $this->warn('Raw token, shown once:');
            $this->line($result['token']);
        } else {
            $this->warn('Raw token hidden. Re-run intentionally with --show-token only when installing an external website bridge.');
        }

        return collect((array) $result['deliveries'])->contains(fn (array $delivery): bool => ($delivery['status'] ?? '') === 'failed')
            ? self::FAILURE
            : self::SUCCESS;
    }
}
