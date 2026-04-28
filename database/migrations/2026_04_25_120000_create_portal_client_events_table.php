<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portal_client_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('source')->default('client_portal');
            $table->string('source_event_id')->nullable();
            $table->string('event_type')->default('tool_answer');
            $table->string('tool_key')->nullable();
            $table->string('title')->nullable();
            $table->text('summary')->nullable();
            $table->text('message')->nullable();
            $table->integer('score')->nullable();
            $table->string('status')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'event_type']);
            $table->unique(['source', 'source_event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_client_events');
    }
};
