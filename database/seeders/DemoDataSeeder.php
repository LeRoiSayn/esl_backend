<?php

namespace Database\Seeders;

use App\Models\Student;
use App\Models\FeeType;
use App\Models\StudentFee;
use App\Models\Schedule;
use App\Models\ClassModel;
use App\Models\Enrollment;
use Illuminate\Database\Seeder;

class DemoDataSeeder extends Seeder
{
    /**
     * Seed demo data for student fees, schedules, and other notification-relevant data.
     * This seeder is additive and can be run on an existing database without breaking anything.
     */
    public function run(): void
    {
        $this->assignStudentFees();
        $this->createSchedules();
    }

    /**
     * Assign fees to all active students
     */
    private function assignStudentFees(): void
    {
        $feeTypes = FeeType::where('is_active', true)->get();
        $students = Student::where('status', 'active')->get();
        $academicYear = '2025-2026';

        foreach ($students as $student) {
            foreach ($feeTypes as $feeType) {
                // Skip if fee already assigned
                $exists = StudentFee::where('student_id', $student->id)
                    ->where('fee_type_id', $feeType->id)
                    ->where('academic_year', $academicYear)
                    ->exists();

                if ($exists) continue;

                // Randomize payment status for demo
                $rand = rand(0, 100);
                if ($rand < 30) {
                    // Fully paid
                    $paidAmount = $feeType->amount;
                    $status = 'paid';
                } elseif ($rand < 60) {
                    // Partially paid
                    $paidAmount = round($feeType->amount * (rand(20, 80) / 100), 2);
                    $status = 'partial';
                } elseif ($rand < 80) {
                    // Pending (not yet due or just due)
                    $paidAmount = 0;
                    $status = 'pending';
                } else {
                    // Overdue
                    $paidAmount = 0;
                    $status = 'overdue';
                }

                // Generate realistic due dates
                $dueDateOffset = match($feeType->name) {
                    'Registration Fee' => -30,   // Was due 30 days ago
                    'Tuition Fee' => 15,          // Due in 15 days
                    'Laboratory Fee' => 30,       // Due in 30 days
                    'Library Fee' => 45,          // Due in 45 days
                    'Sports Fee' => 60,           // Due in 60 days
                    default => rand(7, 60),
                };

                StudentFee::create([
                    'student_id' => $student->id,
                    'fee_type_id' => $feeType->id,
                    'amount' => $feeType->amount,
                    'paid_amount' => $paidAmount,
                    'due_date' => now()->addDays($dueDateOffset),
                    'status' => $status,
                    'academic_year' => $academicYear,
                ]);
            }
        }

        $this->command->info('✅ Student fees assigned to ' . $students->count() . ' students.');
    }

    /**
     * Create weekly schedules for all active classes
     */
    private function createSchedules(): void
    {
        $classes = ClassModel::where('is_active', true)->with('course')->get();
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
        $timeSlots = [
            ['08:00', '10:00'],
            ['10:15', '12:15'],
            ['13:00', '15:00'],
            ['15:15', '17:15'],
        ];
        $rooms = ['Amphi A', 'Amphi B', 'Salle 101', 'Salle 102', 'Salle 103', 'Salle 201', 'Lab Bio 1', 'Lab Bio 2', 'Lab Imm 1', 'Salle Info'];

        $schedulesCreated = 0;

        foreach ($classes as $class) {
            // Skip if class already has schedules
            if (Schedule::where('class_id', $class->id)->exists()) {
                continue;
            }

            // Each class gets 1-2 time slots per week
            $sessionCount = rand(1, 2);
            $usedDays = [];

            for ($s = 0; $s < $sessionCount; $s++) {
                // Pick a random day not already used for this class
                $availableDays = array_diff($days, $usedDays);
                if (empty($availableDays)) break;
                
                $day = $availableDays[array_rand($availableDays)];
                $usedDays[] = $day;

                // Pick a random time slot
                $timeSlot = $timeSlots[array_rand($timeSlots)];

                // Check for conflicts (same day/time/room)
                $room = $rooms[array_rand($rooms)];
                $conflict = Schedule::where('day_of_week', $day)
                    ->where('room', $room)
                    ->where(function ($q) use ($timeSlot) {
                        $q->whereBetween('start_time', [$timeSlot[0], $timeSlot[1]])
                            ->orWhereBetween('end_time', [$timeSlot[0], $timeSlot[1]]);
                    })->exists();

                if ($conflict) {
                    $room = $rooms[array_rand($rooms)] . '-' . rand(1, 9); // Make unique
                }

                Schedule::create([
                    'class_id' => $class->id,
                    'day_of_week' => $day,
                    'start_time' => $timeSlot[0],
                    'end_time' => $timeSlot[1],
                    'room' => $room,
                ]);

                $schedulesCreated++;
            }
        }

        $this->command->info('✅ ' . $schedulesCreated . ' schedules created for ' . $classes->count() . ' classes.');
    }
}
