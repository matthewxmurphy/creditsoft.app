<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dispute_plans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('playbook_key', 80);
            $table->unsignedInteger('playbook_version')->default(1);
            $table->string('display_name');
            $table->string('status', 40)->default('active');
            $table->string('execution_mode', 40)->default('review');
            $table->string('mailing_method', 40)->default('certified');
            $table->boolean('letter_review')->default(true);
            $table->unsignedInteger('budget_cap_cents')->nullable();
            $table->unsignedInteger('spent_cents')->default(0);
            $table->unsignedSmallInteger('current_round')->default(1);
            $table->string('consent_name');
            $table->timestamp('consented_at');
            $table->json('consent_payload')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('last_report_imported_at')->nullable();
            $table->timestamp('next_report_due_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'status']);
        });

        Schema::create('dispute_plan_steps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('dispute_plan_id')->constrained()->cascadeOnDelete();
            $table->string('step_key', 120);
            $table->unsignedSmallInteger('sequence');
            $table->unsignedSmallInteger('round')->default(1);
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('action_type', 80);
            $table->string('status', 40)->default('pending');
            $table->timestamp('scheduled_for');
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedSmallInteger('estimated_letter_count')->default(0);
            $table->unsignedInteger('estimated_cost_cents')->default(0);
            $table->unsignedInteger('actual_cost_cents')->nullable();
            $table->boolean('requires_review')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['dispute_plan_id', 'step_key']);
            $table->index(['status', 'scheduled_for']);
        });

        Schema::create('dispute_bureau_clocks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('dispute_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dispute_plan_step_id')->nullable()->constrained()->nullOnDelete();
            $table->string('bureau', 40);
            $table->string('clock_type', 40);
            $table->string('status', 40)->default('running');
            $table->timestamp('sent_at');
            $table->timestamp('due_at');
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('flagged_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['dispute_plan_step_id', 'bureau', 'clock_type'], 'dispute_clock_step_bureau_type_unique');
            $table->index(['status', 'due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispute_bureau_clocks');
        Schema::dropIfExists('dispute_plan_steps');
        Schema::dropIfExists('dispute_plans');
    }
};
