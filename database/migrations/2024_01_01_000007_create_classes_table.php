<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->onDelete('cascade');
            $table->foreignId('teacher_id')->nullable()->constrained()->onDelete('set null');
            $table->string('section')->default('A');
            $table->string('room')->nullable();
            $table->integer('capacity')->default(50);
            $table->string('academic_year');
            $table->enum('semester', ['1', '2']);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->unique(['course_id', 'section', 'academic_year', 'semester']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classes');
    }
};
