<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('letter_drafts', function (Blueprint $table) {
            $table->boolean('generated_by_ai')->default(false)->after('content');
            $table->json('ai_metadata')->nullable()->after('generated_by_ai');
        });

        Schema::table('case_briefs', function (Blueprint $table) {
            $table->boolean('generated_by_ai')->default(false)->after('content');
            $table->json('ai_metadata')->nullable()->after('generated_by_ai');
        });
    }

    public function down(): void
    {
        Schema::table('letter_drafts', function (Blueprint $table) {
            $table->dropColumn(['generated_by_ai', 'ai_metadata']);
        });

        Schema::table('case_briefs', function (Blueprint $table) {
            $table->dropColumn(['generated_by_ai', 'ai_metadata']);
        });
    }
};
