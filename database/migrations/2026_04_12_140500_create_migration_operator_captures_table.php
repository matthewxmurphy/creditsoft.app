<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('migration_operator_captures', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('source_system', 120);
            $table->string('capture_type', 120);
            $table->string('page_title')->nullable();
            $table->string('page_url', 2048)->nullable();
            $table->text('operator_note')->nullable();
            $table->longText('content_html')->nullable();
            $table->longText('extracted_text')->nullable();
            $table->string('status', 40)->default('staged');
            $table->json('metadata')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('migration_operator_captures');
    }
};
