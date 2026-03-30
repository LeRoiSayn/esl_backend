<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE enrollments MODIFY COLUMN status ENUM('enrolled','dropped','completed','transfer') DEFAULT 'enrolled'");
    }

    public function down(): void
    {
        // Nullify any transfer rows before removing the value
        DB::table('enrollments')->where('status', 'transfer')->update(['status' => 'completed']);
        DB::statement("ALTER TABLE enrollments MODIFY COLUMN status ENUM('enrolled','dropped','completed') DEFAULT 'enrolled'");
    }
};
