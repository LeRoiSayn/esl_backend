<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->onDelete('cascade');
            $table->foreignId('teacher_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->text('description');
            $table->text('instructions')->nullable();
            $table->integer('total_points')->default(20);
            $table->dateTime('due_date');
            $table->boolean('allow_late_submission')->default(false);
            $table->integer('late_penalty_percent')->default(10);
            $table->boolean('allow_multiple_submissions')->default(false);
            $table->json('allowed_file_types')->nullable(); // ['pdf', 'doc', 'docx', 'zip']
            $table->integer('max_file_size_mb')->default(10);
            $table->enum('status', ['draft', 'published', 'closed'])->default('draft');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignments');
    }
};
