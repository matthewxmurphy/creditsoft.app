<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table): void {
            if (! Schema::hasColumn('clients', 'middle_name')) {
                $table->string('middle_name')->nullable()->after('first_name');
            }

            if (! Schema::hasColumn('clients', 'name_suffix')) {
                $table->string('name_suffix', 40)->nullable()->after('last_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table): void {
            if (Schema::hasColumn('clients', 'name_suffix')) {
                $table->dropColumn('name_suffix');
            }

            if (Schema::hasColumn('clients', 'middle_name')) {
                $table->dropColumn('middle_name');
            }
        });
    }
};
