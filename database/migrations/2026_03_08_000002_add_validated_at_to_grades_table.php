<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grades', function (Blueprint $table) {
            $table->timestamp('validated_at')->nullable()->after('graded_at');
            $table->foreignId('validated_by')->nullable()->constrained('users')->onDelete('set null')->after('validated_at');
        });
    }

    public function down(): void
    {
        Schema::table('grades', function (Blueprint $table) {
            $table->dropForeign(['validated_by']);
            $table->dropColumn(['validated_at', 'validated_by']);
        });
    }
};
