<?php

namespace Database\Factories;

use App\Models\Ode;
use App\Models\Student;
use App\Models\StudentOdePlan;
use Illuminate\Database\Eloquent\Factories\Factory;

class StudentOdePlanFactory extends Factory
{
    protected $model = StudentOdePlan::class;

    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'ode_id' => Ode::factory(),
            'start_date' => now()->format('Y-m-d'),
            'status' => 'active',
            'created_by_role' => 'teacher',
        ];
    }
}
