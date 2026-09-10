<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A repeating task, held as its pattern rather than as a thousand rows.
 *
 * «A weekly report every Thursday» written out for a year is fifty-two rows
 * nobody can change afterwards without editing fifty-two things. Held as a
 * pattern, the rows are made as their days come round: a year of Thursdays
 * stays out of the table until the year has happened, and moving the day moves
 * the ones still to come without touching the ones already answered for.
 */
class TaskSeries extends Model
{
    public const DAILY = 'daily';

    public const WEEKLY = 'weekly';

    public const MONTHLY = 'monthly';

    protected $fillable = [
        'title', 'description', 'task_category_id',
        'assigned_to_type', 'assigned_to_id',
        'assign_to_role', 'assign_scope_type', 'assign_scope_ids',
        'every', 'on_weekday', 'on_day',
        'starts_on', 'ends_on', 'remind_days_before', 'is_active',
        'created_by_type', 'created_by_id',
    ];

    protected function casts(): array
    {
        return [
            'assign_scope_ids' => 'array',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_active' => 'boolean',
            'on_weekday' => 'integer',
            'on_day' => 'integer',
            'remind_days_before' => 'integer',
        ];
    }

    /** @return HasMany<Task, $this> */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /** How the repetition reads, for the screen that lists them. */
    public function say(): string
    {
        $days = [1 => 'الأحد', 2 => 'الاثنين', 3 => 'الثلاثاء', 4 => 'الأربعاء', 5 => 'الخميس', 6 => 'الجمعة', 7 => 'السبت'];

        return match ($this->every) {
            self::DAILY => __('كل يوم'),
            self::WEEKLY => __('كل :day', ['day' => $days[$this->on_weekday] ?? '—']),
            self::MONTHLY => __('يوم :n من كل شهر', ['n' => $this->on_day ?? 1]),
            default => $this->every,
        };
    }

    /**
     * Whether this series is due on a given day.
     *
     * Asked per day rather than computed forward, so a series whose pattern was
     * changed answers for today by today's pattern and not by the one that was
     * in force when somebody generated a year ahead.
     */
    public function fallsOn(CarbonInterface $day): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($day->lt($this->starts_on) || ($this->ends_on && $day->gt($this->ends_on))) {
            return false;
        }

        return match ($this->every) {
            self::DAILY => true,
            // Sunday is one here, as the academy's calendar counts it.
            self::WEEKLY => ($day->dayOfWeek + 1) === $this->on_weekday,
            self::MONTHLY => $day->day === ($this->on_day ?? 1),
            default => false,
        };
    }
}
