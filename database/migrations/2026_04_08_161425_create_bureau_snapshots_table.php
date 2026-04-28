<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bureau_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporting_cycle_id')->index();
            $table->string('bureau');
            $table->string('source')->default('manual');
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('imported_at');
            $table->string('file_name')->nullable();
            $table->string('snapshot_hash');
            $table->json('raw_summary')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bureau_snapshots');
    }
};
