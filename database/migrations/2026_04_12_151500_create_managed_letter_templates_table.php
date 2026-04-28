<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('managed_letter_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->string('version', 40)->default('1');
            $table->string('label');
            $table->string('letter_type', 40)->default('dispute');
            $table->json('legal_basis')->nullable();
            $table->text('ai_focus')->nullable();
            $table->text('operator_notes')->nullable();
            $table->longText('content_template')->nullable();
            $table->string('source_system', 120)->nullable();
            $table->string('source_page_url', 2048)->nullable();
            $table->json('metadata')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('managed_letter_templates');
    }
};
