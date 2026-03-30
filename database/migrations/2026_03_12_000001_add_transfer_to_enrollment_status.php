<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE enrollments DROP CONSTRAINT IF EXISTS enrollments_status_check");
        DB::statement("ALTER TABLE enrollments ADD CONSTRAINT enrollments_status_check CHECK (status IN ('enrolled','dropped','completed','transfer'))");
    }

    public function down(): void
    {
        DB::table('enrollments')->where('status', 'transfer')->update(['status' => 'completed']);
        DB::statement("ALTER TABLE enrollments DROP CONSTRAINT IF EXISTS enrollments_status_check");
        DB::statement("ALTER TABLE enrollments ADD CONSTRAINT enrollments_status_check CHECK (status IN ('enrolled','dropped','completed'))");
    }
};
