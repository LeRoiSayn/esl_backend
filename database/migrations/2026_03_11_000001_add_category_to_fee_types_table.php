<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fee_types', function (Blueprint $table) {
            $table->enum('category', ['registration', 'tuition', 'other'])
                  ->default('tuition')
                  ->after('level');
        });

        // Mark existing registration-type fees by name
        DB::table('fee_types')
            ->whereRaw('LOWER(name) LIKE ?', ['%registration%'])
            ->update(['category' => 'registration']);
    }

    public function down(): void
    {
        Schema::table('fee_types', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }
};
