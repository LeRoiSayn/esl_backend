<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Change courses.level from ENUM to VARCHAR so it accepts dynamic values
        DB::statement("ALTER TABLE courses MODIFY COLUMN level VARCHAR(10) NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE courses MODIFY COLUMN level ENUM('L1','L2','L3','M1','M2','D1','D2','D3') NOT NULL");
    }
};
