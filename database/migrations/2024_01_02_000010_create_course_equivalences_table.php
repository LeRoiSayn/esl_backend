<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // For transferred students - course equivalences
        Schema::create('course_equivalences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->onDelete('cascade');
            $table->string('original_course_name');
            $table->string('original_institution');
            $table->foreignId('equivalent_course_id')->nullable()->constrained('courses')->nullOnDelete();
            $table->decimal('original_grade', 5, 2)->nullable();
            $table->integer('original_credits')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_equivalences');
    }
};
