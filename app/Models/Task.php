<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'stage',
        'batch_key',
        'task_series_id',
        'occurs_on',
        'escalated_at',
    ];

    protected $casts = [
        'due_date' => 'date',
        'occurs_on' => 'date',
        'completed_at' => 'datetime',
        'reminded_at' => 'datetime',
        'escalated_at' => 'datetime',
        'remind_days_before' => 'integer',
    ];

    /**
     * Where a task stands, beyond done and not-done.
     *
     * `status` keeps its old words, so everything that reads it — the report,
     * the day's agenda — goes on reading it unchanged. This says which column
     * of the board the task sits in, and «waiting» is the one that earns its
     * place: a task nobody has touched for a fortnight and a task waiting on
     * somebody else look identical in a list, and they are not the same problem.
     */
    public const TODO = 'todo';

    public const DOING = 'doing';

    public const WAITING = 'waiting';

    public const FINISHED = 'finished';

    /** @var array<string, string> */
    public const STAGES = [
        self::TODO => 'لم تبدأ',
        self::DOING => 'جارية',
        self::WAITING => 'بانتظار غيري',
        self::FINISHED => 'منجزة',
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

    /** @return HasMany<TaskStep, $this> */
    public function steps(): HasMany
    {
        return $this->hasMany(TaskStep::class)->orderBy('sort_order')->orderBy('id');
    }

    /** @return HasMany<TaskComment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class)->oldest();
    }

    /** @return HasMany<TaskActivity, $this> */
    public function activities(): HasMany
    {
        return $this->hasMany(TaskActivity::class)->latest();
    }

    /** @return BelongsTo<TaskSeries, $this> */
    public function series(): BelongsTo
    {
        return $this->belongsTo(TaskSeries::class, 'task_series_id');
    }

    /**
     * How far through its own steps a task is.
     *
     * Null when it has none: a task without steps is not nought per cent done,
     * it simply does not answer this question, and showing it as an empty bar
     * says something untrue about it.
     */
    public function stepProgress(): ?int
    {
        $steps = $this->relationLoaded('steps') ? $this->steps : $this->steps()->get();

        if ($steps->isEmpty()) {
            return null;
        }

        return (int) round($steps->where('is_done', true)->count() / $steps->count() * 100);
    }

    /** The stage as it is written on the board. */
    public function stageLabel(): string
    {
        return self::STAGES[$this->stage] ?? $this->stage;
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
