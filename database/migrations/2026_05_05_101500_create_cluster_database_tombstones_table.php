<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cluster_database_tombstones')) {
            return;
        }

        Schema::create('cluster_database_tombstones', function (Blueprint $table): void {
            $table->id();
            $table->uuid('event_uuid')->nullable();
            $table->string('source_node')->nullable();
            $table->string('model_type');
            $table->string('table_name');
            $table->string('record_key', 120);
            $table->timestamp('deleted_at');
            $table->timestamps();

            $table->unique(['table_name', 'record_key'], 'cluster_db_tombstone_record_unique');
            $table->index(['model_type', 'record_key'], 'cluster_db_tombstone_model_record_index');
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cluster_database_tombstones');
    }
};
