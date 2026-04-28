<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cluster_license_sync_outboxes')) {
            return;
        }

        Schema::create('cluster_license_sync_outboxes', function (Blueprint $table): void {
            $table->id();
            $table->uuid('event_uuid');
            $table->string('source_node')->nullable();
            $table->string('peer_label')->nullable();
            $table->string('peer_base_url');
            $table->text('payload');
            $table->string('status', 24)->default('queued');
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('next_attempt_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->unique(['event_uuid', 'peer_base_url'], 'cluster_license_sync_event_peer_unique');
            $table->index(['status', 'next_attempt_at']);
            $table->index('peer_base_url');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cluster_license_sync_outboxes');
    }
};
