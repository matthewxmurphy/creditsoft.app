<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite' || ! Schema::hasTable('sop_runs') || ! Schema::hasTable('sop_templates')) {
            return;
        }

        Schema::table('sop_runs', function (Blueprint $table): void {
            $table->foreign('sop_template_id')->references('id')->on('sop_templates')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite' || ! Schema::hasTable('sop_runs')) {
            return;
        }

        Schema::table('sop_runs', function (Blueprint $table): void {
            $table->dropForeign(['sop_template_id']);
        });
    }
};
