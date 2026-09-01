<?php

use App\Models\Client;
use App\Support\ClientName;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('clients')) {
            return;
        }

        $columns = ['id', 'first_name', 'last_name'];
        $nameColumns = ['first_name', 'last_name'];

        if (Schema::hasColumn('clients', 'middle_name')) {
            $columns[] = 'middle_name';
            $nameColumns[] = 'middle_name';
        }

        if (Schema::hasColumn('clients', 'name_suffix')) {
            $columns[] = 'name_suffix';
            $nameColumns[] = 'name_suffix';
        }

        Client::query()
            ->select($columns)
            ->orderBy('id')
            ->chunkById(200, function ($clients) use ($nameColumns): void {
                foreach ($clients as $client) {
                    $fields = ClientName::normalizeFields([
                        'first_name' => $client->getRawOriginal('first_name'),
                        'middle_name' => $client->getRawOriginal('middle_name'),
                        'last_name' => $client->getRawOriginal('last_name'),
                        'name_suffix' => $client->getRawOriginal('name_suffix'),
                    ]);

                    $client->forceFill(array_intersect_key($fields, array_flip($nameColumns)));

                    if ($client->isDirty($nameColumns)) {
                        $client->save();
                    }
                }
            });
    }

    public function down(): void
    {
        //
    }
};
