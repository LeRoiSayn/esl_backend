<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Change type from enum to string so 'link' is accepted
        DB::statement("ALTER TABLE course_materials MODIFY COLUMN type VARCHAR(50) NOT NULL DEFAULT 'pdf'");

        // Make file_name nullable (link-type materials have no file)
        DB::statement("ALTER TABLE course_materials MODIFY COLUMN file_name VARCHAR(255) NULL");
    }

    public function down(): void
    {
        // Revert to original enum (any 'link' rows will need to be cleaned first)
        DB::statement("ALTER TABLE course_materials MODIFY COLUMN type ENUM('pdf','video','document','presentation','image','other') NOT NULL DEFAULT 'pdf'");
        DB::statement("ALTER TABLE course_materials MODIFY COLUMN file_name VARCHAR(255) NOT NULL");
    }
};
