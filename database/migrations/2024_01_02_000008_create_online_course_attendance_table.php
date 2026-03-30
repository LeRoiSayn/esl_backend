<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('online_course_attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('online_course_id')->constrained()->onDelete('cascade');
            $table->foreignId('student_id')->constrained()->onDelete('cascade');
            $table->dateTime('joined_at');
            $table->dateTime('left_at')->nullable();
            $table->integer('duration_minutes')->default(0);
            $table->timestamps();
            
            $table->unique(['online_course_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('online_course_attendance');
    }
};
