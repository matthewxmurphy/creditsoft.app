<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('office_social_settings', function (Blueprint $table) {
            $table->id();
            $table->json('payload')->nullable();
            $table->timestamps();
        });

        $legacyPath = storage_path('app/private/office-social-settings.json');

        if (! is_file($legacyPath)) {
            return;
        }

        $decoded = json_decode((string) file_get_contents($legacyPath), true);

        if (! is_array($decoded)) {
            return;
        }

        DB::table('office_social_settings')->insert([
            'payload' => json_encode($decoded, JSON_UNESCAPED_SLASHES),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('office_social_settings');
    }
};
