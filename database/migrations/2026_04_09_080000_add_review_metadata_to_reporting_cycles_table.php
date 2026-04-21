<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reporting_cycles', function (Blueprint $table): void {
            $table->json('review_metadata')->nullable()->after('public_summary');
        });
    }

    public function down(): void
    {
        Schema::table('reporting_cycles', function (Blueprint $table): void {
            $table->dropColumn('review_metadata');
        });
    }
};
