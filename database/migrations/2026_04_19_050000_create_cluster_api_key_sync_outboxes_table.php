<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cluster_api_key_sync_outboxes')) {
            return;
        }

        Schema::create('cluster_api_key_sync_outboxes', function (Blueprint $table): void {
            $table->id();
            $table->string('peer_label')->nullable();
            $table->string('peer_base_url');
            $table->string('key_name');
            $table->string('token_suffix', 12);
            $table->text('payload');
            $table->string('status', 24)->default('queued');
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('next_attempt_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'next_attempt_at']);
            $table->index(['peer_base_url', 'key_name', 'status'], 'cluster_api_key_outbox_peer_name_status_index');
            $table->index('token_suffix');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cluster_api_key_sync_outboxes');
    }
};
