<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            // JSON array of course IDs that the student must retake in future semesters.
            // Populated automatically when a student is promoted to the next academic level.
            $table->json('retake_courses')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('retake_courses');
        });
    }
};
