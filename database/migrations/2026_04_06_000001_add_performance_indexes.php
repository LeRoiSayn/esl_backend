<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ── payments ──────────────────────────────────────────────────────
        // payment_date is used in every finance dashboard query (today, monthly, trends)
        $this->addIndexIfMissing('payments', ['payment_date']);
        $this->addIndexIfMissing('payments', ['student_fee_id', 'payment_date'], 'payments_fee_date_index');

        // ── student_fees ──────────────────────────────────────────────────
        // status is filtered in financeStats (pending), student fee lists, dashboards
        $this->addIndexIfMissing('student_fees', ['status']);
        $this->addIndexIfMissing('student_fees', ['academic_year']);
        $this->addIndexIfMissing('student_fees', ['student_id', 'status'], 'student_fees_student_status_index');
        $this->addIndexIfMissing('student_fees', ['student_id', 'academic_year'], 'student_fees_student_year_index');

        // ── enrollments ───────────────────────────────────────────────────
        // status is filtered in every student dashboard & teacher stats
        $this->addIndexIfMissing('enrollments', ['status']);
        $this->addIndexIfMissing('enrollments', ['enrollment_date']);
        $this->addIndexIfMissing('enrollments', ['student_id', 'status'], 'enrollments_student_status_index');

        // ── students ──────────────────────────────────────────────────────
        // status and level are used in adminStats, registrarStats, report generation
        $this->addIndexIfMissing('students', ['status']);
        $this->addIndexIfMissing('students', ['level']);
        $this->addIndexIfMissing('students', ['enrollment_date']);
        $this->addIndexIfMissing('students', ['status', 'level'], 'students_status_level_index');

        // ── courses ───────────────────────────────────────────────────────
        // is_active and level are filtered in every course listing & report
        $this->addIndexIfMissing('courses', ['is_active']);
        $this->addIndexIfMissing('courses', ['level']);
        $this->addIndexIfMissing('courses', ['department_id', 'is_active'], 'courses_dept_active_index');
        $this->addIndexIfMissing('courses', ['department_id', 'level'], 'courses_dept_level_index');

        // ── users ─────────────────────────────────────────────────────────
        // status and role are used in registrar user listings
        $this->addIndexIfMissing('users', ['status']);
        $this->addIndexIfMissing('users', ['role']);

        // ── transactions ──────────────────────────────────────────────────
        $this->addIndexIfMissing('transactions', ['status']);
        $this->addIndexIfMissing('transactions', ['student_id', 'status'], 'transactions_student_status_index');
        $this->addIndexIfMissing('transactions', ['created_at']);

        // ── grades ────────────────────────────────────────────────────────
        $this->addIndexIfMissing('grades', ['enrollment_id']);
        $this->addIndexIfMissing('grades', ['validated_at']);
    }

    public function down(): void
    {
        $drops = [
            'payments'      => ['payments_payment_date_index', 'payments_fee_date_index'],
            'student_fees'  => ['student_fees_status_index', 'student_fees_academic_year_index', 'student_fees_student_status_index', 'student_fees_student_year_index'],
            'enrollments'   => ['enrollments_status_index', 'enrollments_enrollment_date_index', 'enrollments_student_status_index'],
            'students'      => ['students_status_index', 'students_level_index', 'students_enrollment_date_index', 'students_status_level_index'],
            'courses'       => ['courses_is_active_index', 'courses_level_index', 'courses_dept_active_index', 'courses_dept_level_index'],
            'users'         => ['users_status_index', 'users_role_index'],
            'transactions'  => ['transactions_status_index', 'transactions_student_status_index', 'transactions_created_at_index'],
            'grades'        => ['grades_enrollment_id_index', 'grades_validated_at_index'],
        ];

        foreach ($drops as $table => $indexes) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $t) use ($indexes) {
                    foreach ($indexes as $idx) {
                        try { $t->dropIndex($idx); } catch (\Throwable $e) { /* already gone */ }
                    }
                });
            }
        }
    }

    /**
     * Add an index only if it doesn't already exist, to avoid duplicate-index errors
     * when running on a DB that already has some of these columns indexed.
     */
    private function addIndexIfMissing(string $table, array $columns, ?string $name = null): void
    {
        if (!Schema::hasTable($table)) return;

        $name ??= $table . '_' . implode('_', $columns) . '_index';

        $exists = collect(DB::select("
            SELECT indexname FROM pg_indexes
            WHERE tablename = ? AND indexname = ?
        ", [$table, $name]))->isNotEmpty();

        if ($exists) return;

        Schema::table($table, function (Blueprint $t) use ($columns, $name) {
            $t->index($columns, $name);
        });
    }
};
