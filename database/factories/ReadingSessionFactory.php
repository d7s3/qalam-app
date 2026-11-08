<?php

namespace Database\Factories;

use App\Models\ReadingSession;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReadingSession>
 */
class ReadingSessionFactory extends Factory
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
            'started_at' => now()->subMinutes(20),
            'ended_at' => now(),
            'minutes' => 20,
            'pages' => 10,
        ];
    }
}
