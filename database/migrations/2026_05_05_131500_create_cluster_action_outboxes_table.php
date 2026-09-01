<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cluster_action_outboxes', function (Blueprint $table): void {
            $table->id();
            $table->uuid('action_uuid');
            $table->string('source_node')->nullable();
            $table->string('peer_label');
            $table->string('peer_base_url', 1024);
            $table->string('action', 160);
            $table->text('payload');
            $table->string('status', 30)->default('queued');
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('next_attempt_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->unique(['action_uuid', 'peer_base_url'], 'cluster_action_outbox_uuid_peer_unique');
            $table->index(['status', 'next_attempt_at']);
            $table->index(['peer_base_url', 'action', 'status']);
        });

        Schema::create('cluster_action_receipts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('action_uuid')->unique();
            $table->string('source_node')->nullable();
            $table->string('action', 160);
            $table->text('result')->nullable();
            $table->timestamp('received_at');
            $table->timestamps();

            $table->index(['action', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cluster_action_receipts');
        Schema::dropIfExists('cluster_action_outboxes');
    }
};
