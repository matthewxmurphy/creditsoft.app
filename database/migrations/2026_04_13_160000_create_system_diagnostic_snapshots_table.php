<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_diagnostic_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->timestamp('captured_at')->index();
            $table->string('hostname')->nullable();
            $table->unsignedInteger('cpu_cores')->nullable();
            $table->decimal('load_one', 8, 2)->nullable();
            $table->decimal('load_five', 8, 2)->nullable();
            $table->decimal('load_fifteen', 8, 2)->nullable();
            $table->unsignedBigInteger('memory_total_bytes')->nullable();
            $table->unsignedBigInteger('memory_used_bytes')->nullable();
            $table->unsignedBigInteger('memory_free_bytes')->nullable();
            $table->unsignedBigInteger('swap_total_bytes')->nullable();
            $table->unsignedBigInteger('swap_used_bytes')->nullable();
            $table->unsignedBigInteger('swap_free_bytes')->nullable();
            $table->unsignedBigInteger('disk_total_bytes')->nullable();
            $table->unsignedBigInteger('disk_used_bytes')->nullable();
            $table->unsignedBigInteger('disk_free_bytes')->nullable();
            $table->unsignedBigInteger('network_rx_bytes')->nullable();
            $table->unsignedBigInteger('network_tx_bytes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_diagnostic_snapshots');
    }
};
