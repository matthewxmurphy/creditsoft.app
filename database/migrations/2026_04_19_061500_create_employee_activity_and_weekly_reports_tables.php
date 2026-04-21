<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('employee_activity_samples')) {
            Schema::create('employee_activity_samples', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->timestamp('sampled_at');
                $table->string('route_path')->nullable();
                $table->string('page_title')->nullable();
                $table->string('session_uuid', 64)->nullable();
                $table->unsignedInteger('active_ms')->default(0);
                $table->unsignedInteger('keypress_count')->default(0);
                $table->unsignedInteger('click_count')->default(0);
                $table->unsignedInteger('mouse_move_count')->default(0);
                $table->unsignedInteger('scroll_count')->default(0);
                $table->unsignedInteger('focus_count')->default(0);
                $table->unsignedInteger('form_submit_count')->default(0);
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'sampled_at']);
                $table->index('sampled_at');
                $table->index('session_uuid');
            });
        }

        if (! Schema::hasTable('employee_weekly_reports')) {
            Schema::create('employee_weekly_reports', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->date('period_start');
                $table->date('period_end');
                $table->string('title');
                $table->text('summary')->nullable();
                $table->json('strengths')->nullable();
                $table->json('risks')->nullable();
                $table->text('coaching_notes')->nullable();
                $table->json('next_week_focus')->nullable();
                $table->string('ai_provider')->nullable();
                $table->string('ai_model')->nullable();
                $table->string('status')->default('generated');
                $table->timestamp('generated_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(['user_id', 'period_start', 'period_end'], 'employee_weekly_reports_user_period_unique');
                $table->index(['period_start', 'period_end']);
                $table->index(['user_id', 'generated_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_weekly_reports');
        Schema::dropIfExists('employee_activity_samples');
    }
};
