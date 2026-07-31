<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class AcademicCalendarEvent extends Model
{
    /**
     * The circle's working days between two dates, as Y-m-d strings in order.
     *
     * A working day is one covered by an attendance-period event whose weekdays
     * include it; an event with no weekdays set covers every day it spans. When
     * no attendance period reaches the range at all the calendar has nothing to
     * say, so the academy's ordinary Sunday-to-Thursday week stands in.
     *
     * This is the one definition of a working day: streak badges count runs of
     * them, and the circle report measures attendance against them.
     *
     * @return array<int, string>
     */
    public static function workingDaysBetween(Carbon|string $from, Carbon|string $to): array
    {
        $start = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->startOfDay();

        if ($start->gt($end)) {
            return [];
        }

        $periods = static::where('is_attendance_period', true)
            ->whereDate('start_date', '<=', $end->toDateString())
            ->where(function ($query) use ($start) {
                $query->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', $start->toDateString());
            })
            ->get()
            ->map(fn (self $event) => [
                'start' => Carbon::parse($event->start_date)->format('Y-m-d'),
                'end' => $event->end_date ? Carbon::parse($event->end_date)->format('Y-m-d') : null,
                // Stored weekdays mix integers and strings, so compare as integers.
                'weekdays' => array_map('intval', $event->weekdays ?? []),
            ]);

        $days = [];

        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            $dayString = $day->format('Y-m-d');
            $weekday = $day->dayOfWeek + 1; // 1=Sunday … 7=Saturday

            $working = $periods->isEmpty()
                ? in_array($weekday, [1, 2, 3, 4, 5], true)
                : $periods->contains(fn (array $period) => $dayString >= $period['start']
                    && (! $period['end'] || $dayString <= $period['end'])
                    && ($period['weekdays'] === [] || in_array($weekday, $period['weekdays'], true)));

            if ($working) {
                $days[] = $dayString;
            }
        }

        return $days;
    }

    protected $fillable = [
        'event_name',
        'start_date',
        'end_date',
        'color',
        'is_attendance_period',
        'weekdays',
        'description',
        'day_count',
        'created_by_id',
        'created_by_type',
        'shared_with',
        'is_visible',
        'has_tasks',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_attendance_period' => 'boolean',
        'weekdays' => 'array',
        'day_count' => 'integer',
        'shared_with' => 'array',
        'is_visible' => 'boolean',
        'has_tasks' => 'boolean',
    ];

    public function createdBy()
    {
        return $this->morphTo();
    }

    public function tasks()
    {
        return $this->belongsToMany(Task::class, 'academic_calendar_event_task');
    }

    public function taskCategories()
    {
        return $this->hasMany(TaskCategory::class, 'event_id');
    }

    public function scopeVisibleTo($query, $user)
    {
        $userType = get_class($user);

        return $query->where(function ($q) use ($user, $userType) {
            // 1. Is Creator
            $q->where(function ($sq) use ($user, $userType) {
                $sq->where('created_by_id', $user->id)
                    ->where('created_by_type', $userType);
            })
            // 2. Or is explicitly shared with them
                ->orWhere(function ($sq) use ($user, $userType) {
                    $sq->whereNotNull('shared_with')
                        ->where(function ($jsonQuery) use ($user, $userType) {
                            if ($userType === Teacher::class) {
                                $jsonQuery->whereJsonContains('shared_with->all_teachers', true)
                                    ->orWhereJsonContains('shared_with->teacher_ids', $user->id);

                                if ($user->relationLoaded('circles') || $user->circles()->exists()) {
                                    foreach ($user->circles->pluck('id') as $cId) {
                                        $jsonQuery->orWhereJsonContains('shared_with->circle_ids', $cId);
                                    }
                                    foreach ($user->circles->pluck('stage_id')->unique() as $sId) {
                                        $jsonQuery->orWhereJsonContains('shared_with->stage_ids_for_teachers', $sId);
                                    }
                                }
                            } elseif ($userType === Supervisor::class) {
                                $jsonQuery->whereJsonContains('shared_with->all_supervisors', true)
                                    ->orWhereJsonContains('shared_with->supervisor_ids', $user->id);

                                if ($user->relationLoaded('stages') || $user->stages()->exists()) {
                                    foreach ($user->stages->pluck('id') as $sId) {
                                        $jsonQuery->orWhereJsonContains('shared_with->stage_ids_for_supervisors', $sId);
                                    }
                                }
                            } elseif ($userType === Student::class) {
                                $jsonQuery->whereJsonContains('shared_with->all_students', true)
                                    ->orWhereJsonContains('shared_with->student_ids', $user->id);
                                if ($user->circle_id) {
                                    $jsonQuery->orWhereJsonContains('shared_with->circle_ids', $user->circle_id);
                                }
                                $effectiveStageId = $user->circle?->stage_id ?? $user->stage_id;
                                if ($effectiveStageId) {
                                    $jsonQuery->orWhereJsonContains('shared_with->stage_ids_for_students', $effectiveStageId);
                                }
                            } elseif ($userType === Manager::class) {
                                $jsonQuery->whereJsonContains('shared_with->all_managers', true)
                                    ->orWhereJsonContains('shared_with->manager_ids', $user->id);
                            }
                        });
                });
        })->where('is_visible', true);
    }
}
