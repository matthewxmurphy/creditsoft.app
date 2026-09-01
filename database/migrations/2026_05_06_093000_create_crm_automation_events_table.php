<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_automation_events', function (Blueprint $table): void {
            $table->id();
            $table->string('provider')->default('twenty')->index();
            $table->string('external_event_id')->nullable()->index();
            $table->string('idempotency_key', 64)->unique();
            $table->string('event_type')->index();
            $table->string('object_type')->nullable()->index();
            $table->string('object_id')->nullable()->index();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('received')->index();
            $table->string('priority')->default('normal')->index();
            $table->json('payload');
            $table->json('signals')->nullable();
            $table->json('decision')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_automation_events');
    }
};
