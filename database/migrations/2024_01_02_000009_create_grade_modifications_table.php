<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Log all grade modifications for audit
        Schema::create('grade_modifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grade_id')->constrained()->onDelete('cascade');
            $table->foreignId('modified_by')->constrained('users')->onDelete('cascade');
            $table->decimal('old_value', 5, 2);
            $table->decimal('new_value', 5, 2);
            $table->text('reason');
            $table->string('ip_address')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grade_modifications');
    }
};
