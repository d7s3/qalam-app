<?php

namespace App\Console\Commands;

use App\Models\AcademicCalendarEvent;
use App\Models\Manager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportCalendarEventsCommand extends Command
{
    protected $signature = 'calendar:import
                            {--file=database/data/calendar_events.json : مسار ملف JSON}
                            {--manager-id=1 : معرف المدير الذي سيُنسب إليه الإنشاء}
                            {--clear : احذف الأحداث الموجودة قبل الاستيراد}';

    protected $description = 'استيراد أحداث التقويم من ملف JSON إلى قاعدة البيانات';

    public function handle(): int
    {
        $filePath = base_path($this->option('file'));
        $managerId = (int) $this->option('manager-id');
        $clear = $this->option('clear');

        // التحقق من وجود الملف
        if (! file_exists($filePath)) {
            $this->error("الملف غير موجود: {$filePath}");

            return self::FAILURE;
        }

        // التحقق من وجود المدير
        $manager = Manager::find($managerId);
        if (! $manager) {
            $this->error("لا يوجد مدير بالمعرف: {$managerId}");

            return self::FAILURE;
        }

        $json = file_get_contents($filePath);
        $events = json_decode($json, true);

        if (! is_array($events) || empty($events)) {
            $this->error('الملف فارغ أو تنسيقه غير صحيح.');

            return self::FAILURE;
        }

        if ($clear) {
            if (! $this->confirm('سيتم حذف جميع الأحداث الموجودة. هل أنت متأكد؟')) {
                $this->info('تم إلغاء العملية.');

                return self::SUCCESS;
            }
            DB::table('academic_calendar_events')->truncate();
            $this->warn('تم حذف الأحداث القديمة.');
        }

        $count = count($events);
        $this->info("جاري استيراد {$count} حدث...");
        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $imported = 0;
        $skipped = 0;

        foreach ($events as $event) {
            try {
                AcademicCalendarEvent::create([
                    'event_name' => $event['event_name'],
                    'start_date' => $event['start_date'],
                    'end_date' => $event['end_date'],
                    'color' => $event['color'] ?? 'indigo',
                    'is_attendance_period' => $event['is_attendance_period'] ?? false,
                    'weekdays' => $event['weekdays'] ?? null,
                    'description' => $event['description'] ?? null,
                    'day_count' => $event['day_count'] ?? null,
                    'is_visible' => $event['is_visible'] ?? true,
                    'has_tasks' => $event['has_tasks'] ?? false,
                    'shared_with' => $event['shared_with'] ?? [],
                    'created_by_id' => $manager->id,
                    'created_by_type' => 'App\Models\Manager',
                ]);
                $imported++;
            } catch (\Exception $e) {
                $this->newLine();
                $this->warn("تخطي حدث بسبب خطأ: {$e->getMessage()}");
                $skipped++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("✅ تم الاستيراد بنجاح: {$imported} حدث.");
        if ($skipped > 0) {
            $this->warn("⚠️ تم تخطي: {$skipped} حدث.");
        }

        return self::SUCCESS;
    }
}
