<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // PostgreSQL syntax: ALTER COLUMN ... TYPE
        DB::statement("ALTER TABLE courses ALTER COLUMN level TYPE VARCHAR(10)");
    }

    public function down(): void
    {
        // Restore as VARCHAR (PostgreSQL has no ENUM in the same way — keep as string)
        DB::statement("ALTER TABLE courses ALTER COLUMN level TYPE VARCHAR(10)");
    }
};
