<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    protected $fillable = [
        'title',
        'description',
        'due_date',
        'status',
        'task_category_id',
        'created_by_id',
        'created_by_type',
        'assigned_to_id',
        'assigned_to_type',
        'completed_at',
        'completed_by',
        'remind_days_before',
        'reminded_at',
    ];

    protected $casts = [
        'due_date' => 'date',
        'completed_at' => 'datetime',
        'reminded_at' => 'datetime',
        'remind_days_before' => 'integer',
    ];

    /** Finished, however the status word for it is spelled. */
    public const DONE = ['completed', 'done'];

    protected static function booted(): void
    {
        // The moment of finishing is stamped from the status rather than left to
        // every screen that toggles it to remember — and cleared when a task is
        // reopened, so a reopened task does not keep a completion date it no
        // longer has.
        static::saving(function (self $task) {
            $isDone = in_array($task->status, self::DONE, true);

            if ($isDone && ! $task->completed_at) {
                $task->completed_at = now();
                $task->completed_by ??= auth()->id();
            }

            if (! $isDone && $task->completed_at) {
                $task->completed_at = null;
                $task->completed_by = null;
            }
        });
    }

    public function isDone(): bool
    {
        return in_array($this->status, self::DONE, true);
    }

    /**
     * Whether it was finished after its due date.
     *
     * A task with no due date can never be late: nothing was promised.
     */
    public function wasLate(): bool
    {
        return $this->isDone()
            && $this->due_date !== null
            && $this->completed_at !== null
            && $this->completed_at->startOfDay()->gt($this->due_date);
    }

    /**
     * Open tasks whose warning is due and has not been given.
     *
     * @param  Builder<self>  $query
     */
    public function scopeAwaitingReminder($query, ?CarbonInterface $on = null)
    {
        $today = ($on ?? now())->startOfDay();

        return $query->whereNotIn('status', self::DONE)
            ->whereNull('reminded_at')
            ->whereNotNull('due_date')
            ->whereNotNull('remind_days_before')
            ->whereNotNull('assigned_to_id')
            // Due within the warning window, and not yet past — a task already
            // overdue is not warned about, it is chased.
            ->whereDate('due_date', '>=', $today->toDateString())
            ->whereRaw("date(due_date, '-' || remind_days_before || ' day') <= ?", [$today->toDateString()]);
    }

    /** Still open, and its day has passed. */
    public function isOverdue(): bool
    {
        return ! $this->isDone()
            && $this->due_date !== null
            && $this->due_date->lt(now()->startOfDay());
    }

    public function createdBy()
    {
        return $this->morphTo();
    }

    public function assignedTo()
    {
        return $this->morphTo();
    }

    public function category()
    {
        return $this->belongsTo(TaskCategory::class, 'task_category_id');
    }

    public function events()
    {
        return $this->belongsToMany(AcademicCalendarEvent::class, 'academic_calendar_event_task');
    }
}
