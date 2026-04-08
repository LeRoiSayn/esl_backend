<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fee_types', function (Blueprint $table) {
            $table->string('academic_year', 10)->nullable()
                ->after('category')
                ->comment('Academic year this fee applies to, e.g. 2025-2026. NULL = applies to all years.');
        });
    }

    public function down(): void
    {
        Schema::table('fee_types', function (Blueprint $table) {
            $table->dropColumn('academic_year');
        });
    }
};
