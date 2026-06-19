<?php

namespace Database\Seeders;

use App\Models\AcademicCalendarEvent;
use Illuminate\Database\Seeder;

class AcademicCalendarSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $events = [
            // Year 1447-1448
            ['event_name' => 'عودة الهيئة الإدارية والمشرفين التربويين', 'start_date' => '2025-08-12', 'color' => 'indigo'],
            ['event_name' => 'عودة المعلمين الممارسين للتدريس', 'start_date' => '2025-08-17', 'color' => 'blue'],
            ['event_name' => 'بداية العام الدراسي', 'start_date' => '2025-08-24', 'color' => 'green'],
            ['event_name' => 'إجازة اليوم الوطني', 'start_date' => '2025-09-23', 'color' => 'emerald'],
            ['event_name' => 'إجازة إضافية', 'start_date' => '2025-10-12', 'color' => 'zinc'],
            ['event_name' => 'إجازة الخريف', 'start_date' => '2025-11-21', 'end_date' => '2025-11-29', 'color' => 'orange'],
            ['event_name' => 'إجازة إضافية', 'start_date' => '2025-12-11', 'end_date' => '2025-12-14', 'color' => 'zinc'],
            ['event_name' => 'إجازة منتصف العام الدراسي', 'start_date' => '2026-01-09', 'end_date' => '2026-01-17', 'color' => 'sky'],
            ['event_name' => 'إجازة يوم التأسيس', 'start_date' => '2026-02-22', 'color' => 'rose'],
            ['event_name' => 'إجازة عيد الفطر', 'start_date' => '2026-03-06', 'end_date' => '2026-03-28', 'color' => 'amber'],
            ['event_name' => 'إجازة عيد الأضحى', 'start_date' => '2026-05-22', 'end_date' => '2026-06-01', 'color' => 'teal'],
            ['event_name' => 'بداية إجازة نهاية العام الدراسي', 'start_date' => '2026-06-25', 'color' => 'red'],
            ['event_name' => 'بداية العام الدراسي القادم 1448-1449هـ', 'start_date' => '2026-08-23', 'color' => 'lime'],

            // Year 1448-1449
            ['event_name' => 'عودة الهيئة الإدارية والمشرفين التربويين', 'start_date' => '2026-08-11', 'color' => 'indigo'],
            ['event_name' => 'عودة المعلمين الممارسين للتدريس', 'start_date' => '2026-08-16', 'color' => 'blue'],
            ['event_name' => 'بداية العام الدراسي', 'start_date' => '2026-08-23', 'color' => 'green'],
            ['event_name' => 'إجازة اليوم الوطني', 'start_date' => '2026-09-23', 'end_date' => '2026-09-26', 'color' => 'emerald'],
            ['event_name' => 'إجازة الخريف', 'start_date' => '2026-11-20', 'end_date' => '2026-11-28', 'color' => 'orange'],
            ['event_name' => 'إجازة منتصف العام الدراسي', 'start_date' => '2027-01-08', 'end_date' => '2027-01-16', 'color' => 'sky'],
            ['event_name' => 'إجازة يوم التأسيس', 'start_date' => '2027-02-19', 'end_date' => '2027-02-22', 'color' => 'rose'],
            ['event_name' => 'إجازة عيد الفطر', 'start_date' => '2027-02-26', 'end_date' => '2027-03-13', 'color' => 'amber'],
            ['event_name' => 'إجازة عيد الأضحى', 'start_date' => '2027-05-07', 'end_date' => '2027-05-22', 'color' => 'teal'],
            ['event_name' => 'بداية إجازة نهاية العام الدراسي', 'start_date' => '2027-06-24', 'color' => 'red'],
            ['event_name' => 'بداية العام الدراسي القادم 1449-1450هـ', 'start_date' => '2027-08-22', 'color' => 'lime'],
        ];

        foreach ($events as $event) {
            AcademicCalendarEvent::updateOrCreate(
                [
                    'event_name' => $event['event_name'],
                    'start_date' => $event['start_date'],
                ],
                [
                    'end_date' => $event['end_date'] ?? $event['start_date'],
                    'color' => $event['color'] ?? 'indigo',
                ]
            );
        }
    }
}
