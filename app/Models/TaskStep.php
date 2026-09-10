<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One step inside a task.
 *
 * «In progress» says nothing on its own. A task with four steps and two ticked
 * says something to the man who has to answer for it, and to the man who has to
 * decide whether to worry.
 */
class TaskStep extends Model
{
    protected $fillable = ['task_id', 'title', 'is_done', 'sort_order', 'done_at'];

    protected function casts(): array
    {
        return ['is_done' => 'boolean', 'done_at' => 'datetime', 'sort_order' => 'integer'];
    }

    protected static function booted(): void
    {
        // The moment of ticking is stamped from the tick, so no screen has to
        // remember to — and cleared when it is unticked, so a step does not
        // keep a date for something that has been undone.
        static::saving(function (self $step) {
            if ($step->is_done && ! $step->done_at) {
                $step->done_at = now();
            }

            if (! $step->is_done) {
                $step->done_at = null;
            }
        });
    }

    /** @return BelongsTo<Task, $this> */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
