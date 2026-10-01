<?php

namespace Database\Seeders;

use App\Models\Circle;
use App\Models\Guardian;
use App\Models\Manager;
use App\Models\Stage;
use App\Models\Student;
use App\Models\Supervisor;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Accounts and a small academy for walking every role's screens by hand.
 *
 * Two stages, each with its own supervisor and teacher, so a supervisor of
 * one can be tried against the other's circles and students. The first circle
 * holds enough students to page. Every account shares the one password below;
 * it is for the local database only and is never to be seeded anywhere else.
 *
 * Run with: php artisan db:seed --class=QaAuditSeeder
 */
class QaAuditSeeder extends Seeder
{
    public const PASSWORD = 'QaAudit#2026';

    /**
     * @var array<string, string>
     */
    public const ACCOUNTS = [
        'manager' => 'qa-manager@qalam.test',
        'supervisor' => 'qa-supervisor@qalam.test',
        'supervisor_other' => 'qa-supervisor-b@qalam.test',
        'teacher' => 'qa-teacher@qalam.test',
        'teacher_other' => 'qa-teacher-b@qalam.test',
        'student' => 'qa-student@qalam.test',
        'guardian' => 'qa-guardian@qalam.test',
    ];

    public function run(): void
    {
        if (app()->isProduction()) {
            return;
        }

        $stage = Stage::firstOrCreate(['name' => 'QA مرحلة أ'], ['description' => 'مرحلة فحص الجودة']);
        $otherStage = Stage::firstOrCreate(['name' => 'QA مرحلة ب'], ['description' => 'مرحلة فحص الجودة الثانية']);

        $circle = Circle::firstOrCreate(['name' => 'QA حلقة الفجر'], ['stage_id' => $stage->id]);
        $secondCircle = Circle::firstOrCreate(['name' => 'QA حلقة العصر'], ['stage_id' => $stage->id]);
        $otherCircle = Circle::firstOrCreate(['name' => 'QA حلقة المرحلة ب'], ['stage_id' => $otherStage->id]);

        $this->account(Manager::class, 'manager', 'مدير الفحص');

        $supervisor = $this->account(Supervisor::class, 'supervisor', 'مشرف الفحص');
        $supervisor->stages()->syncWithoutDetaching([$stage->id]);

        $otherSupervisor = $this->account(Supervisor::class, 'supervisor_other', 'مشرف المرحلة ب');
        $otherSupervisor->stages()->syncWithoutDetaching([$otherStage->id]);

        $teacher = $this->account(Teacher::class, 'teacher', 'معلم الفحص');
        $teacher->circles()->syncWithoutDetaching([$circle->id, $secondCircle->id]);

        $otherTeacher = $this->account(Teacher::class, 'teacher_other', 'معلم المرحلة ب');
        $otherTeacher->circles()->syncWithoutDetaching([$otherCircle->id]);

        $guardian = $this->account(Guardian::class, 'guardian', 'ولي أمر الفحص');

        $this->account(Student::class, 'student', 'طالب الفحص', [
            'circle_id' => $circle->id,
            'stage_id' => $stage->id,
            'guardian_id' => $guardian->id,
            'status' => 'active',
        ]);

        $this->students($circle, 24, 'طالب الفجر');
        $this->students($secondCircle, 5, 'طالب العصر');
        $this->students($otherCircle, 3, 'طالب المرحلة ب');
    }

    /**
     * @param  class-string<User>  $model
     * @param  array<string, mixed>  $attributes
     */
    private function account(string $model, string $key, string $name, array $attributes = []): User
    {
        $user = $model::firstOrNew(['email' => self::ACCOUNTS[$key]]);

        $user->fill([
            'name' => $name,
            'password' => self::PASSWORD,
            'must_change_password' => false,
            'is_approved' => true,
            ...$attributes,
        ])->save();

        return $user;
    }

    private function students(Circle $circle, int $count, string $prefix): void
    {
        foreach (range(1, $count) as $number) {
            $student = Student::firstOrNew(['email' => 'qa-'.$circle->id.'-'.$number.'@qalam.test']);

            $student->fill([
                'name' => $prefix.' '.$number,
                'password' => self::PASSWORD,
                'must_change_password' => false,
                'is_approved' => true,
                'circle_id' => $circle->id,
                'stage_id' => $circle->stage_id,
                'status' => 'active',
            ])->save();
        }
    }
}
