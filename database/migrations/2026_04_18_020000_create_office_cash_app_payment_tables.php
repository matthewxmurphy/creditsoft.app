<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('office_cash_app_settings', function (Blueprint $table): void {
            $table->id();
            $table->boolean('enabled')->default(false);
            $table->string('environment', 24)->default('sandbox');
            $table->string('api_base_url')->default('https://sandbox.api.cash.app');
            $table->string('client_id')->nullable();
            $table->text('api_key_id')->nullable();
            $table->text('api_secret')->nullable();
            $table->string('region', 24)->default('PDX');
            $table->string('scope_id')->nullable();
            $table->string('merchant_id')->nullable();
            $table->string('redirect_url', 2048)->nullable();
            $table->string('user_agent')->default('CreditSoft Intranet');
            $table->boolean('auto_capture')->default(false);
            $table->timestamp('last_checked_at')->nullable();
            $table->text('last_error')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('cash_app_payment_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('office_cash_app_setting_id')->nullable()->constrained('office_cash_app_settings')->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('client_payment_id')->nullable()->constrained('client_payments')->nullOnDelete();
            $table->string('idempotency_key', 64)->unique();
            $table->string('cash_app_request_id')->nullable()->index();
            $table->string('cash_app_payment_id')->nullable()->index();
            $table->string('grant_id')->nullable();
            $table->string('reference_id')->unique();
            $table->string('status')->default('pending');
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->string('action_type')->default('ONE_TIME_PAYMENT');
            $table->string('channel')->default('ONLINE');
            $table->string('scope_id')->nullable();
            $table->string('merchant_id')->nullable();
            $table->string('redirect_url', 2048)->nullable();
            $table->text('qr_code_image_url')->nullable();
            $table->text('qr_code_svg_url')->nullable();
            $table->text('mobile_url')->nullable();
            $table->text('desktop_url')->nullable();
            $table->timestamp('refreshes_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('raw_response')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['client_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_app_payment_requests');
        Schema::dropIfExists('office_cash_app_settings');
    }
};
