<?php

namespace Database\Factories;

use App\Models\Ode;
use Illuminate\Database\Eloquent\Factories\Factory;

class OdeFactory extends Factory
{
    protected $model = Ode::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
        ];
    }
}
