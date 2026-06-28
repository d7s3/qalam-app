<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicCalendarEvent extends Model
{
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
