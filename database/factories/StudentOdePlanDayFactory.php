<?php

namespace Database\Factories;

use App\Models\StudentOdePlan;
use App\Models\StudentOdePlanDay;
use Illuminate\Database\Eloquent\Factories\Factory;

class StudentOdePlanDayFactory extends Factory
{
    protected $model = StudentOdePlanDay::class;

    public function definition(): array
    {
        return [
            'student_ode_plan_id' => StudentOdePlan::factory(),
            'date' => now()->format('Y-m-d'),
            'day_name' => 'الأحد',
            'from_verse_number' => 1,
            'to_verse_number' => 5,
            'review_from_verse_number' => null,
            'review_to_verse_number' => null,
            'hifz_achievement' => null,
            'review_achievement' => null,
            'hifz_graded_at' => null,
            'review_graded_at' => null,
        ];
    }
}
