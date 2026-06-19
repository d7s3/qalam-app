<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaskCategory extends Model
{
    protected $fillable = [
        'name',
        'color',
        'icon',
        'event_id',
        'created_by_id',
        'created_by_type',
    ];

    public function createdBy()
    {
        return $this->morphTo();
    }

    public function tasks()
    {
        return $this->hasMany(Task::class, 'task_category_id');
    }
}
