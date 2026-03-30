<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->enum('semester', ['1', '2', '3'])->default('1');
        });

        // PostgreSQL: update CHECK constraint to support 3 semesters
        DB::statement("ALTER TABLE classes DROP CONSTRAINT IF EXISTS classes_semester_check");
        DB::statement("ALTER TABLE classes ADD CONSTRAINT classes_semester_check CHECK (semester IN ('1', '2', '3'))");
        DB::statement("ALTER TABLE classes ALTER COLUMN semester SET DEFAULT '1'");
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn('semester');
        });

        DB::statement("ALTER TABLE classes DROP CONSTRAINT IF EXISTS classes_semester_check");
        DB::statement("ALTER TABLE classes ADD CONSTRAINT classes_semester_check CHECK (semester IN ('1', '2'))");
    }
};
