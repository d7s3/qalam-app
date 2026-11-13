<?php

namespace Database\Factories;

use App\Models\AppComplaint;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AppComplaint>
 */
class AppComplaintFactory extends Factory
{
    public function definition(): array
    {
        return [
            'chat_id' => (string) fake()->numberBetween(100000, 999999),
            'step' => AppComplaint::STEP_DONE,
            'name' => 'عمر محمد',
            'role' => 'طالب',
            'description' => 'لا يحفظ البرنامج الذاتي ما أكتبه',
            'media_type' => 'photo',
            'media_file_id' => 'photo-file',
            'media_message_id' => 10,
            'submitted_at' => now(),
        ];
    }
}
