<?php

namespace App\Models;

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
    ];

    protected $casts = [
        'due_date' => 'date',
    ];

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
