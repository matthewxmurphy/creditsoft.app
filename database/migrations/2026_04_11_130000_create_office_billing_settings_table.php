<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('office_billing_settings', function (Blueprint $table) {
            $table->id();
            $table->string('gateway_provider')->nullable();
            $table->string('gateway_status')->default('manual');
            $table->string('gateway_account_label')->nullable();
            $table->string('gateway_environment')->nullable();
            $table->string('webhook_status')->nullable();
            $table->timestamp('gateway_connected_at')->nullable();
            $table->string('payment_portal_url')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('office_billing_settings');
    }
};
