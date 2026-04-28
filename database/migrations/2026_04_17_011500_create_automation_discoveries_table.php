<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_discoveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('last_seen_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source_system', 120);
            $table->string('source_product', 120)->nullable();
            $table->string('page_kind', 80)->nullable();
            $table->string('source_identifier', 255)->nullable();
            $table->string('source_signature', 64)->unique();
            $table->string('name')->nullable();
            $table->string('status', 80)->nullable();
            $table->string('category', 120)->nullable();
            $table->string('workflow_type', 120)->nullable();
            $table->string('start_condition', 120)->nullable();
            $table->unsignedInteger('condition_count')->default(0);
            $table->unsignedInteger('action_count')->default(0);
            $table->unsignedInteger('step_count')->default(0);
            $table->unsignedInteger('seen_count')->default(1);
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('promoted_at')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['source_system', 'source_product']);
            $table->index(['last_seen_at', 'source_system']);
            $table->index('promoted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_discoveries');
    }
};
