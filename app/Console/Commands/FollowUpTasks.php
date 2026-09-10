<?php

namespace App\Console\Commands;

use App\Services\TaskFollowUpService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * The night's work on the academy's tasks.
 *
 * Two things, run together because they are the two halves of the same idea:
 * raise whatever the patterns say falls due today, and tell somebody senior
 * about whatever has gone past its date without being answered.
 *
 * Both are safe to run twice. The first is keyed by the day it stands for, the
 * second marks what it has already reported — so a night the scheduler missed
 * is caught up by simply running it again in the morning.
 */
class FollowUpTasks extends Command
{
    protected $signature = 'tasks:follow-up {--on= : اليوم الذي يُحسب له، افتراضه اليوم}';

    protected $description = 'يُنشئ المهام المتكرّرة لليوم، ويُبلّغ عن المتأخّرة';

    public function handle(TaskFollowUpService $service): int
    {
        $on = $this->option('on');

        $raised = $service->raiseDue($on ? Carbon::parse($on) : null);
        $escalated = $service->escalateOverdue($on ? Carbon::parse($on) : null);

        $this->info("أُنشئت {$raised} مهمة، وأُبلغ عن {$escalated} متأخّرة.");

        return self::SUCCESS;
    }
}
