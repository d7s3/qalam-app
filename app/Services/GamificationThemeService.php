<?php

namespace App\Services;

use App\Models\Leaderboard;

class GamificationThemeService
{
    /**
     * Resolve the custom theme for a gamification competition.
     *
     * Every gamification competition stores its theme under settings['theme'].
     * Missing keys fall back to the default "فرسان الحفظ" palette.
     *
     * @return array<string, mixed>
     */
    public static function getTheme(?Leaderboard $leaderboard = null): array
    {
        $settings = $leaderboard?->settings ?? [];
        $custom = $settings['theme'] ?? [];

        return [
            'name' => $custom['name'] ?? 'طابع مخصص',
            'color' => $custom['color'] ?? '#4f46e5',
            'currency_name' => $custom['currency_name'] ?? 'جوهرة',
            'coin_emoji' => $custom['coin_emoji'] ?? '💎',
            'xp_name' => $custom['xp_name'] ?? 'طاقة الخبرة',
            'xp_emoji' => $custom['xp_emoji'] ?? '✨',
            'team_name' => $custom['team_name'] ?? 'كتيبة',
            'team_plural' => $custom['team_plural'] ?? 'كتائب',
            'team_emoji' => $custom['team_emoji'] ?? '🛡️',
            'team_possessive_your' => $custom['team_possessive_your'] ?? 'كتيبتك',
            'team_possessive_my' => $custom['team_possessive_my'] ?? 'كتيبتي',
            'default_levels' => [
                1 => ['name' => 'مبتدئ', 'xp_required' => 0, 'icon' => 'sparkles'],
                2 => ['name' => 'مشارك', 'xp_required' => 100, 'icon' => 'user'],
                3 => ['name' => 'متميز', 'xp_required' => 300, 'icon' => 'star'],
                4 => ['name' => 'قائد', 'xp_required' => 600, 'icon' => 'trophy'],
                5 => ['name' => 'أسطوري', 'xp_required' => 1000, 'icon' => 'crown'],
            ],
        ];
    }
}
