<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('office_crm_user_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('crm_email')->index();
            $table->text('crm_password')->nullable();
            $table->string('crm_workspace_id')->nullable()->index();
            $table->string('crm_workspace_url', 2048)->nullable();
            $table->timestamp('last_launched_at')->nullable();
            $table->text('last_error')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('office_crm_user_links');
    }
};
