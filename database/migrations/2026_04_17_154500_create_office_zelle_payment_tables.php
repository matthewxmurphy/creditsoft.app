<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('office_zelle_settings', function (Blueprint $table): void {
            $table->id();
            $table->boolean('enabled')->default(false);
            $table->string('bank_name')->nullable();
            $table->string('imap_host')->nullable();
            $table->unsignedSmallInteger('imap_port')->default(993);
            $table->string('imap_encryption', 24)->default('ssl');
            $table->string('imap_username')->nullable();
            $table->text('imap_password')->nullable();
            $table->string('imap_folder')->default('INBOX');
            $table->string('expected_subject')->default('You received money with Zelle®');
            $table->text('trusted_domains')->nullable();
            $table->boolean('delete_after_import')->default(true);
            $table->timestamp('last_checked_at')->nullable();
            $table->text('last_error')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('zelle_payment_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('office_zelle_setting_id')->nullable()->constrained('office_zelle_settings')->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('client_payment_id')->nullable()->constrained('client_payments')->nullOnDelete();
            $table->string('mailbox')->nullable();
            $table->string('message_uid');
            $table->string('message_id')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->date('sent_on')->nullable();
            $table->string('from_name')->nullable();
            $table->string('from_email')->nullable();
            $table->string('subject')->nullable();
            $table->text('body_excerpt')->nullable();
            $table->decimal('amount', 10, 2)->nullable();
            $table->string('currency', 3)->default('USD');
            $table->string('sender_name')->nullable();
            $table->string('memo_email')->nullable();
            $table->text('memo_text')->nullable();
            $table->string('transaction_id')->nullable();
            $table->string('status')->default('needs_review');
            $table->string('match_type')->nullable();
            $table->string('header_status')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('deleted_from_mailbox_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['office_zelle_setting_id', 'mailbox', 'message_uid'], 'zelle_messages_setting_mailbox_uid_unique');
            $table->index(['status', 'received_at']);
            $table->index(['client_id', 'received_at']);
            $table->index('client_payment_id');
            $table->index('memo_email');
            $table->index('transaction_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zelle_payment_messages');
        Schema::dropIfExists('office_zelle_settings');
    }
};
