<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentOdePlan extends Model
{
    protected $fillable = [
        'student_id',
        'ode_id',
        'start_date',
        'status',
        'created_by_role',
    ];

    protected $casts = [
        'start_date' => 'date',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function ode()
    {
        return $this->belongsTo(Ode::class);
    }

    public function days()
    {
        return $this->hasMany(StudentOdePlanDay::class, 'student_ode_plan_id');
    }
}
