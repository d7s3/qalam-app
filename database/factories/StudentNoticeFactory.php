<?php

namespace Database\Factories;

use App\Models\Student;
use App\Models\StudentNotice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentNotice>
 */
class StudentNoticeFactory extends Factory
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
            'body' => 'السلام عليكم يا '.StudentNotice::NAME.'، '.fake()->sentence(),
            'unlock_code' => null,
        ];
    }

    /** Held shut until the student types this code. */
    public function locked(string $code = '1448'): static
    {
        return $this->state(['unlock_code' => $code]);
    }

    public function opened(): static
    {
        return $this->state(['opened_at' => now()]);
    }
}
