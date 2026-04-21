<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_provider_accounts', function (Blueprint $table): void {
            $table->text('security_answer')->nullable()->after('login_password');
        });
    }

    public function down(): void
    {
        Schema::table('client_provider_accounts', function (Blueprint $table): void {
            $table->dropColumn('security_answer');
        });
    }
};
