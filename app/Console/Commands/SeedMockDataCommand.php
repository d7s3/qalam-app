<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use App\Models\Ayah;
use App\Models\Circle;
use App\Models\Student;
use App\Models\StudentPlan;
use App\Models\StudentPlanDay;
use App\Models\Teacher;
use Carbon\Carbon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:seed-mock {--students=5 : Number of students to seed}')]
#[Description('Seed mock data for testing including attendance and tasmeeh plans')]
class SeedMockDataCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting mock data seeding...');

        $numStudents = (int) $this->option('students');

        // Ensure we have at least one circle and teacher
        $circle = Circle::first();
        if (! $circle) {
            $circle = Circle::factory()->create(['name' => 'دفعة التجربة']);
        }

        $teacher = Teacher::first();
        if (! $teacher) {
            $teacher = Teacher::factory()->create(['name' => 'معلم التجربة']);
            $teacher->circles()->attach($circle->id);
        } else {
            if (! $teacher->circles()->where('circle_id', $circle->id)->exists()) {
                $teacher->circles()->attach($circle->id);
            }
        }

        // Get or create students
        $students = Student::inRandomOrder()->take($numStudents)->get();
        if ($students->count() < $numStudents) {
            $needed = $numStudents - $students->count();
            $newStudents = Student::factory()->count($needed)->create([
                'circle_id' => $circle->id,
            ]);
            $students = $students->concat($newStudents);
        }

        $ayahs = Ayah::take(100)->get();
        if ($ayahs->isEmpty()) {
            $this->error('No ayahs found in the database. Please seed the Quran data first.');

            return;
        }

        $achievements = ['excellent', 'good', 'acceptable', 'needs_improvement', 'not_memorized'];
        $attendanceStatuses = ['present', 'absent', 'late', 'excused'];

        $bar = $this->output->createProgressBar(count($students));
        $bar->start();

        $daysToGenerate = 300;

        foreach ($students as $student) {
            $hasPlan = (rand(1, 100) > 20); // 80% chance to have a plan

            $plan = null;
            if ($hasPlan) {
                // 1. Create a Student Plan
                $plan = StudentPlan::create([
                    'student_id' => $student->id,
                    'teacher_id' => $teacher->id,
                    'start_date' => Carbon::now()->subDays($daysToGenerate)->toDateString(),
                    'days_count' => $daysToGenerate,
                    'active_days' => ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday'],
                    'description' => 'خطة تجريبية مولدة تلقائياً (300 يوم)',
                    'status' => 'active',
                    'plan_type' => 'hifz_and_review',
                    'direction' => 'forward',
                    'is_approved' => true,
                    'created_by_role' => 'teacher',
                ]);
            }

            // Iterate over 300 days for attendance and tasmeeh
            for ($i = 0; $i < $daysToGenerate; $i++) {
                $date = Carbon::now()->subDays($daysToGenerate - $i);

                // Skip Fridays and Saturdays for Tasmeeh and usually Attendance
                if ($date->isFriday() || $date->isSaturday()) {
                    continue;
                }

                // 2. Tasmeeh Achievements (if has plan)
                if ($hasPlan) {
                    $didTasmeeh = (rand(1, 100) > 30); // 70% chance they did tasmeeh today

                    $hifzAchieve = $didTasmeeh ? collect($achievements)->random() : null;
                    $reviewAchieve = $didTasmeeh ? collect($achievements)->random() : null;
                    $gradedAtHifz = $didTasmeeh ? $date->copy()->addHours(rand(8, 12)) : null;
                    $gradedAtReview = $didTasmeeh ? $date->copy()->addHours(rand(8, 12))->addMinutes(rand(10, 50)) : null;

                    StudentPlanDay::create([
                        'student_plan_id' => $plan->id,
                        'date' => $date->toDateString(),
                        'day_name' => $date->englishDayOfWeek,
                        'from_ayah_id' => $ayahs->random()->id,
                        'to_ayah_id' => $ayahs->random()->id,
                        'review_from_ayah_id' => $ayahs->random()->id,
                        'review_to_ayah_id' => $ayahs->random()->id,
                        'hifz_achievement' => $hifzAchieve,
                        'review_achievement' => $reviewAchieve,
                        'hifz_graded_at' => $gradedAtHifz,
                        'review_graded_at' => $gradedAtReview,
                    ]);
                }

                // 3. Create Attendance
                $wasMarkedForAttendance = (rand(1, 100) > 15); // 85% chance attendance was recorded

                if ($wasMarkedForAttendance) {
                    $attendanceDate = $date->format('Y-m-d 00:00:00');
                    $existing = Attendance::where('student_id', $student->id)
                        ->whereDate('date', $date->toDateString())
                        ->first();

                    if (! $existing) {
                        Attendance::create([
                            'student_id' => $student->id,
                            'date' => $attendanceDate,
                            'teacher_id' => $teacher->id,
                            'circle_id' => $student->circle_id ?? $circle->id,
                            'status' => collect($attendanceStatuses)->random(),
                            'notes' => 'تحضير تجريبي',
                        ]);
                    } else {
                        $existing->update([
                            'status' => collect($attendanceStatuses)->random(),
                            'notes' => 'تحضير تجريبي',
                        ]);
                    }
                }
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info('Mock data seeded successfully!');
    }
}
