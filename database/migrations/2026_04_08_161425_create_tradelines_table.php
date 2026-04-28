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
        Schema::create('tradelines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bureau_snapshot_id')->constrained()->cascadeOnDelete();
            $table->string('normalized_key')->index();
            $table->string('creditor_name');
            $table->string('account_name')->nullable();
            $table->string('account_type')->nullable();
            $table->string('bureau_account_reference')->nullable();
            $table->boolean('is_revolving')->default(false);
            $table->boolean('is_open')->default(true);
            $table->decimal('balance', 12, 2)->nullable();
            $table->decimal('credit_limit', 12, 2)->nullable();
            $table->decimal('utilization_percent', 5, 2)->nullable();
            $table->string('payment_status')->nullable();
            $table->string('account_status')->nullable();
            $table->date('date_opened')->nullable();
            $table->date('date_last_payment')->nullable();
            $table->date('date_reported')->nullable();
            $table->boolean('positive_classification')->nullable();
            $table->string('provenance')->default('manual');
            $table->text('remarks')->nullable();
            $table->json('data')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tradelines');
    }
};
