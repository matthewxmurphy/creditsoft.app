<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table): void {
            $table->string('secondary_email')->nullable()->after('email');
            $table->string('address_line_1')->nullable()->after('phone');
            $table->string('address_line_2')->nullable()->after('address_line_1');
            $table->string('city')->nullable()->after('address_line_2');
            $table->string('state', 40)->nullable()->after('city');
            $table->string('postal_code', 30)->nullable()->after('state');
            $table->date('date_of_birth')->nullable()->after('postal_code');
            $table->text('ssn')->nullable()->after('date_of_birth');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table): void {
            $table->dropColumn([
                'secondary_email',
                'address_line_1',
                'address_line_2',
                'city',
                'state',
                'postal_code',
                'date_of_birth',
                'ssn',
            ]);
        });
    }
};
