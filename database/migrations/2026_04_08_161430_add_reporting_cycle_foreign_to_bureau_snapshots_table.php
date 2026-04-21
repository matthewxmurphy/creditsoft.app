<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite' || ! Schema::hasTable('bureau_snapshots') || ! Schema::hasTable('reporting_cycles')) {
            return;
        }

        Schema::table('bureau_snapshots', function (Blueprint $table): void {
            $table->foreign('reporting_cycle_id')->references('id')->on('reporting_cycles')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite' || ! Schema::hasTable('bureau_snapshots')) {
            return;
        }

        Schema::table('bureau_snapshots', function (Blueprint $table): void {
            $table->dropForeign(['reporting_cycle_id']);
        });
    }
};
