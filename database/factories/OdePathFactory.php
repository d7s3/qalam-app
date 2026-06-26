<?php

namespace Database\Factories;

use App\Models\Ode;
use App\Models\OdePath;
use Illuminate\Database\Eloquent\Factories\Factory;

class OdePathFactory extends Factory
{
    protected $model = OdePath::class;

    public function definition(): array
    {
        return [
            'ode_id' => Ode::factory(),
            'name' => $this->faker->words(3, true),
            'start_date' => now()->format('Y-m-d'),
        ];
    }
}
