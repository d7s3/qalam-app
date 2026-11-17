<?php

namespace Database\Factories;

use App\Models\CommitmentAlert;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommitmentAlert>
 */
class CommitmentAlertFactory extends Factory
{
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'week_starts_on' => '2026-11-08',
            'week_ends_on' => '2026-11-14',
            'plan_percent' => 50,
            'self_percent' => 80,
            'notified_at' => now(),
        ];
    }
}
