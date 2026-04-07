<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // attendance: the existing unique(enrollment_id, date) covers enrollment_id lookups,
        // but a dedicated composite index on (enrollment_id, status) avoids row-level status
        // filtering after the index scan — critical for attendance_rate queries.
        $this->addIndexIfMissing('attendance', ['enrollment_id', 'status'], 'attendance_enrollment_status_index');

        // enrollments: student_id is the JOIN key for the new attendance_rate query.
        // Also used in studentStats() and teacher dashboard.
        $this->addIndexIfMissing('enrollments', ['student_id']);
        $this->addIndexIfMissing('enrollments', ['class_id']);

        // attendance: date filter used in per-date attendance reports
        $this->addIndexIfMissing('attendance', ['date']);
    }

    public function down(): void
    {
        if (Schema::hasTable('attendance')) {
            Schema::table('attendance', function (Blueprint $t) {
                foreach (['attendance_enrollment_status_index', 'attendance_date_index'] as $idx) {
                    try { $t->dropIndex($idx); } catch (\Throwable) {}
                }
            });
        }
        if (Schema::hasTable('enrollments')) {
            Schema::table('enrollments', function (Blueprint $t) {
                foreach (['enrollments_student_id_index', 'enrollments_class_id_index'] as $idx) {
                    try { $t->dropIndex($idx); } catch (\Throwable) {}
                }
            });
        }
    }

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
