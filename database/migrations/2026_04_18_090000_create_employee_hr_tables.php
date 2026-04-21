<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('legal_name')->nullable();
            $table->string('preferred_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone')->nullable();
            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country')->nullable();
            $table->string('timezone')->nullable();
            $table->string('employment_type')->nullable();
            $table->string('department')->nullable();
            $table->string('title')->nullable();
            $table->string('onboarding_status')->default('not_started');
            $table->timestamp('onboarding_started_at')->nullable();
            $table->timestamp('onboarding_completed_at')->nullable();
            $table->string('pay_method')->nullable();
            $table->string('pay_destination')->nullable();
            $table->string('pay_currency', 3)->default('USD');
            $table->text('payroll_notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['department', 'onboarding_status']);
            $table->index('pay_method');
        });

        Schema::create('employee_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('review_type')->default('review');
            $table->string('title');
            $table->text('body')->nullable();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->string('status')->default('open');
            $table->date('occurred_on')->nullable();
            $table->date('due_on')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'review_type', 'status']);
            $table->index(['due_on', 'status']);
        });

        Schema::create('payroll_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('USD');
            $table->string('pay_method')->nullable();
            $table->string('pay_destination')->nullable();
            $table->string('reference')->nullable();
            $table->string('status')->default('draft');
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['period_start', 'period_end']);
            $table->index('reference');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_records');
        Schema::dropIfExists('employee_reviews');
        Schema::dropIfExists('employee_profiles');
    }
};
