<?php

namespace Database\Factories;

use App\Models\Student;
use App\Models\StudentEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentEvent>
 */
class StudentEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'kind' => StudentEvent::PAGE,
            'route_name' => 'student.dashboard',
            'status' => 200,
        ];
    }
}
