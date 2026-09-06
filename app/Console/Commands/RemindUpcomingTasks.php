<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Services\NotificationService;
use App\Support\HijriDate;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('tasks:remind')]
#[Description('Warn whoever holds a task that its day is approaching.')]
class RemindUpcomingTasks extends Command
{
    /**
     * A word before the day arrives, once.
     *
     * `reminded_at` is stamped as each is sent, so a run every night does not
     * repeat itself every night until the day comes — a reminder that arrives
     * daily is one nobody reads.
     */
    public function handle(): int
    {
        $sent = 0;

        Task::awaitingReminder()
            ->with('category')
            ->chunkById(200, function ($tasks) use (&$sent) {
                foreach ($tasks as $task) {
                    $days = (int) now()->startOfDay()->diffInDays($task->due_date, false);

                    NotificationService::notify(
                        recipientType: $task->assigned_to_type ?? '',
                        recipientId: (int) $task->assigned_to_id,
                        type: 'task_due_soon',
                        title: $days === 0 ? 'مهمة تستحق اليوم' : "مهمة تستحق بعد {$days} يوم",
                        body: $task->title.' — '.HijriDate::withGregorian($task->due_date),
                        data: ['task_id' => $task->id, 'due_date' => $task->due_date?->toDateString()],
                    );

                    $task->forceFill(['reminded_at' => now()])->saveQuietly();
                    $sent++;
                }
            });

        $this->info("أُرسل {$sent} تنبيهاً.");

        return self::SUCCESS;
    }
}
