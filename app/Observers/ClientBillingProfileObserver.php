<?php

namespace App\Observers;

use App\Models\Client;
use App\Models\ClientBillingProfile;
use App\Services\ClientHealthSignalService;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class ClientBillingProfileObserver implements ShouldHandleEventsAfterCommit
{
    public function saved(ClientBillingProfile $profile): void
    {
        $this->syncClientIds([
            $profile->client_id,
            $profile->getOriginal('client_id'),
        ]);
    }

    public function deleted(ClientBillingProfile $profile): void
    {
        $this->syncClientIds([
            $profile->client_id,
            $profile->getOriginal('client_id'),
        ]);
    }

    /**
     * @param  list<int|string|null>  $clientIds
     */
    protected function syncClientIds(array $clientIds): void
    {
        $health = app(ClientHealthSignalService::class);

        Client::query()
            ->whereKey(collect($clientIds)->filter()->unique()->values()->all())
            ->get()
            ->each(fn (Client $client) => $health->sync($client));
    }
}
