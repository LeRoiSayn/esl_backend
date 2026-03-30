<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Alter the enum column to include 'paypal'
        DB::statement("ALTER TABLE transactions MODIFY COLUMN payment_method ENUM('card', 'paypal', 'airtel_money', 'moov_money', 'bank_transfer', 'cash') DEFAULT 'cash'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE transactions MODIFY COLUMN payment_method ENUM('card', 'airtel_money', 'moov_money', 'bank_transfer', 'cash') DEFAULT 'cash'");
    }
};
