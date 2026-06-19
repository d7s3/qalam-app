<?php

namespace Database\Factories;

use App\Models\Ode;
use App\Models\OdeVerse;
use Illuminate\Database\Eloquent\Factories\Factory;

class OdeVerseFactory extends Factory
{
    protected $model = OdeVerse::class;

    public function definition(): array
    {
        return [
            'ode_id' => Ode::factory(),
            'verse_number' => 1,
            'sadr' => 'الصدر الأول من البيت الشعري',
            'ajuz' => 'العجز الثاني من البيت الشعري',
        ];
    }
}
