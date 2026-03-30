<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_materials', function (Blueprint $table) {
            if (!Schema::hasColumn('course_materials', 'class_id')) {
                $table->foreignId('class_id')->nullable()->constrained('classes')->onDelete('cascade');
            }
            if (!Schema::hasColumn('course_materials', 'external_url')) {
                $table->string('external_url')->nullable()->after('file_path');
            }
            // make file_path nullable to allow link-only materials
            if (Schema::hasColumn('course_materials', 'file_path')) {
                $table->string('file_path')->nullable()->change();
            }
        });

        Schema::table('assignments', function (Blueprint $table) {
            if (!Schema::hasColumn('assignments', 'class_id')) {
                $table->foreignId('class_id')->nullable()->constrained('classes')->onDelete('cascade');
            }
        });
    }

    public function down(): void
    {
        Schema::table('course_materials', function (Blueprint $table) {
            if (Schema::hasColumn('course_materials', 'class_id')) {
                $table->dropForeign(['class_id']);
                $table->dropColumn('class_id');
            }
            if (Schema::hasColumn('course_materials', 'external_url')) {
                $table->dropColumn('external_url');
            }
        });

        Schema::table('assignments', function (Blueprint $table) {
            if (Schema::hasColumn('assignments', 'class_id')) {
                $table->dropForeign(['class_id']);
                $table->dropColumn('class_id');
            }
        });
    }
};
