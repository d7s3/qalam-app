<?php

namespace Database\Factories;

use App\Models\Student;
use App\Models\StudentBook;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentBook>
 */
class StudentBookFactory extends Factory
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
            'title' => 'العواصم من القواصم',
            'author' => 'ابن العربي',
            'total_pages' => 300,
            'current_page' => 0,
            'shelf' => StudentBook::READING,
        ];
    }
}
