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
        DB::statement("ALTER TABLE course_materials DROP CONSTRAINT IF EXISTS course_materials_type_check");
        DB::statement("ALTER TABLE course_materials ALTER COLUMN type TYPE VARCHAR(50)");
        DB::statement("ALTER TABLE course_materials ALTER COLUMN type SET DEFAULT 'pdf'");

        // Make file_name nullable (link-type materials have no file)
        DB::statement("ALTER TABLE course_materials ALTER COLUMN file_name DROP NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE course_materials ADD CONSTRAINT course_materials_type_check CHECK (type IN ('pdf','video','document','presentation','image','other'))");
        DB::statement("ALTER TABLE course_materials ALTER COLUMN file_name SET NOT NULL");
    }
};
