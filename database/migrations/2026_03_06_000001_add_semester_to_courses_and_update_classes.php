<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Add semester column to courses table (which semester in the year this course belongs to)
        Schema::table('courses', function (Blueprint $table) {
            $table->enum('semester', ['1', '2', '3'])->default('1')->after('level');
        });

        // Update classes table: change semester enum to support 3 semesters
        // MySQL doesn't support ALTER ENUM easily, so we modify the column
        DB::statement("ALTER TABLE classes MODIFY COLUMN semester ENUM('1', '2', '3') NOT NULL DEFAULT '1'");
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn('semester');
        });

        DB::statement("ALTER TABLE classes MODIFY COLUMN semester ENUM('1', '2') NOT NULL DEFAULT '1'");
    }
};
