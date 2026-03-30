<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // PostgreSQL uses CHECK constraints for enums
        DB::statement("ALTER TABLE transactions DROP CONSTRAINT IF EXISTS transactions_payment_method_check");
        DB::statement("ALTER TABLE transactions ADD CONSTRAINT transactions_payment_method_check CHECK (payment_method IN ('card', 'paypal', 'airtel_money', 'moov_money', 'bank_transfer', 'cash'))");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE transactions DROP CONSTRAINT IF EXISTS transactions_payment_method_check");
        DB::statement("ALTER TABLE transactions ADD CONSTRAINT transactions_payment_method_check CHECK (payment_method IN ('card', 'airtel_money', 'moov_money', 'bank_transfer', 'cash'))");
    }
};
