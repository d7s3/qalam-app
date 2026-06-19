<?php

namespace App\Services;

use App\Models\Leaderboard;

class GamificationThemeService
{
    /**
     * Get all available gamification themes.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function getThemes(): array
    {
        return [
            'space' => [
                'name' => 'الفضاء والمجرات',
                'currency_name' => 'بلورة فضائية',
                'xp_name' => 'طاقة الخبرة',
                'team_name' => 'سفينة فضائية',
                'team_plural' => 'سفن فضائية',
                'default_levels' => [
                    1 => ['name' => 'نجم مبتدئ', 'xp_required' => 0, 'icon' => 'sparkles'],
                    2 => ['name' => 'مستكشف الكواكب', 'xp_required' => 100, 'icon' => 'globe-alt'],
                    3 => ['name' => 'ملاح المجرة', 'xp_required' => 300, 'icon' => 'paper-airplane'],
                    4 => ['name' => 'قائد المركبة', 'xp_required' => 600, 'icon' => 'rocket'],
                    5 => ['name' => 'أمير الفضاء', 'xp_required' => 1000, 'icon' => 'academic-cap'],
                ],
            ],
            'ocean' => [
                'name' => 'أعماق البحار',
                'currency_name' => 'لؤلؤة المحيط',
                'xp_name' => 'قوة الإبحار',
                'team_name' => 'سفينة بحرية',
                'team_plural' => 'سفن بحرية',
                'default_levels' => [
                    1 => ['name' => 'بحار مبتدئ', 'xp_required' => 0, 'icon' => 'map'],
                    2 => ['name' => 'غواص الأعماق', 'xp_required' => 100, 'icon' => 'eye'],
                    3 => ['name' => 'صياد الكنوز', 'xp_required' => 300, 'icon' => 'key'],
                    4 => ['name' => 'قبطان البحار', 'xp_required' => 600, 'icon' => 'flag'],
                    5 => ['name' => 'ملك المحيط', 'xp_required' => 1000, 'icon' => 'trophy'],
                ],
            ],
            'heroes' => [
                'name' => 'أبطال وتاريخ',
                'currency_name' => 'دينار ذهبي',
                'xp_name' => 'الهيبة والمجد',
                'team_name' => 'كتيبة الفرسان',
                'team_plural' => 'كتائب الفرسان',
                'default_levels' => [
                    1 => ['name' => 'فارس متدرب', 'xp_required' => 0, 'icon' => 'user'],
                    2 => ['name' => 'محارب شجاع', 'xp_required' => 100, 'icon' => 'shield-check'],
                    3 => ['name' => 'قائد فرسان', 'xp_required' => 300, 'icon' => 'bolt'],
                    4 => ['name' => 'حارس القلعة', 'xp_required' => 600, 'icon' => 'home'],
                    5 => ['name' => 'الفارس الأسطوري', 'xp_required' => 1000, 'icon' => 'crown'],
                ],
            ],
        ];
    }

    /**
     * Get theme details by key or leaderboard settings.
     *
     * @param  string|Leaderboard  $themeKeyOrLeaderboard
     * @return array<string, mixed>
     */
    public static function getTheme($themeKeyOrLeaderboard): array
    {
        if ($themeKeyOrLeaderboard instanceof Leaderboard) {
            $settings = $themeKeyOrLeaderboard->settings ?? [];
            if (isset($settings['theme'])) {
                $customTheme = $settings['theme'];

                return [
                    'name' => $customTheme['name'] ?? 'طابع مخصص',
                    'color' => $customTheme['color'] ?? '#4f46e5',
                    'currency_name' => $customTheme['currency_name'] ?? 'بلورة فضائية',
                    'coin_emoji' => $customTheme['coin_emoji'] ?? '💎',
                    'xp_name' => $customTheme['xp_name'] ?? 'طاقة الخبرة',
                    'xp_emoji' => $customTheme['xp_emoji'] ?? '🌌',
                    'team_name' => $customTheme['team_name'] ?? 'سفينة فضائية',
                    'team_plural' => $customTheme['team_plural'] ?? 'سفن فضائية',
                    'team_emoji' => $customTheme['team_emoji'] ?? '🚀',
                    'team_possessive_your' => $customTheme['team_possessive_your'] ?? 'فريقك',
                    'team_possessive_my' => $customTheme['team_possessive_my'] ?? 'فريقي',
                    'default_levels' => [
                        1 => ['name' => 'مبتدئ', 'xp_required' => 0, 'icon' => 'sparkles'],
                        2 => ['name' => 'مشارك', 'xp_required' => 100, 'icon' => 'user'],
                        3 => ['name' => 'متميز', 'xp_required' => 300, 'icon' => 'star'],
                        4 => ['name' => 'قائد', 'xp_required' => 600, 'icon' => 'trophy'],
                        5 => ['name' => 'أسطوري', 'xp_required' => 1000, 'icon' => 'crown'],
                    ],
                ];
            }
            $themeKey = $themeKeyOrLeaderboard->theme_key ?? 'space';
        } else {
            $themeKey = (string) $themeKeyOrLeaderboard;
        }

        $themes = self::getThemes();
        $theme = $themes[$themeKey] ?? $themes['space'];

        // Add compatibility keys for old themes
        $theme['color'] = $themeKey === 'ocean' ? '#0d9488' : ($themeKey === 'heroes' ? '#d97706' : '#4f46e5');
        $theme['coin_emoji'] = $themeKey === 'ocean' ? '🦪' : ($themeKey === 'heroes' ? '🪙' : '💎');
        $theme['xp_emoji'] = $themeKey === 'ocean' ? '🌊' : ($themeKey === 'heroes' ? '⚔️' : '🌌');
        $theme['team_emoji'] = $themeKey === 'ocean' ? '⛵' : ($themeKey === 'heroes' ? '🛡️' : '🚀');
        $theme['team_possessive_your'] = 'فريقك';
        $theme['team_possessive_my'] = 'فريقي';

        return $theme;
    }
}
