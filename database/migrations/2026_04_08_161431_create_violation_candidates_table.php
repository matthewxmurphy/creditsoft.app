<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('violation_candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reporting_cycle_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('tradeline_id')->nullable()->constrained()->nullOnDelete();
            $table->string('rule_key');
            $table->string('title');
            $table->string('severity')->default('medium');
            $table->string('status')->default('open');
            $table->string('bureau')->nullable();
            $table->json('evidence')->nullable();
            $table->text('next_action')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('violation_candidates');
    }
};
