<?php

namespace App\Observers;

use App\Models\Client;
use App\Models\ClientPayment;
use App\Services\ClientHealthSignalService;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class ClientPaymentObserver implements ShouldHandleEventsAfterCommit
{
    public function saved(ClientPayment $payment): void
    {
        $this->syncClientIds([
            $payment->client_id,
            $payment->getOriginal('client_id'),
        ]);
    }

    public function deleted(ClientPayment $payment): void
    {
        $this->syncClientIds([
            $payment->client_id,
            $payment->getOriginal('client_id'),
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
