<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('violation_candidates', function (Blueprint $table) {
            $table->unsignedSmallInteger('priority_score')->default(0)->after('severity');
        });

        Schema::table('letter_drafts', function (Blueprint $table) {
            $table->string('template_key')->nullable()->after('letter_type');
            $table->string('template_version')->nullable()->after('template_key');
        });
    }

    public function down(): void
    {
        Schema::table('letter_drafts', function (Blueprint $table) {
            $table->dropColumn(['template_key', 'template_version']);
        });

        Schema::table('violation_candidates', function (Blueprint $table) {
            $table->dropColumn('priority_score');
        });
    }
};
