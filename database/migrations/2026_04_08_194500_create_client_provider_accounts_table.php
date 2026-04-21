<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_provider_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('provider_key');
            $table->string('provider_label');
            $table->string('login_email')->nullable();
            $table->string('login_username')->nullable();
            $table->text('login_password')->nullable();
            $table->string('status')->default('connected');
            $table->timestamp('last_imported_at')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['client_id', 'provider_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_provider_accounts');
    }
};
