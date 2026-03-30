<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quiz_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['multiple_choice', 'true_false', 'short_answer', 'fill_blank', 'matching', 'ordering'])->default('multiple_choice');
            $table->text('question');
            $table->json('options')->nullable(); // For multiple choice, matching, etc.
            $table->json('correct_answer'); // Can be string, array, or object
            $table->text('explanation')->nullable();
            $table->integer('points')->default(1);
            $table->integer('order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_questions');
    }
};
