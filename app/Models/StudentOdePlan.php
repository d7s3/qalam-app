<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudentOdePlan extends Model
{
    protected $fillable = [
        'student_id',
        'ode_path_id',
        'start_date',
        'status',
        'created_by_role',
    ];

    protected $casts = [
        'start_date' => 'date',
    ];

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<OdePath, $this> */
    public function path(): BelongsTo
    {
        return $this->belongsTo(OdePath::class, 'ode_path_id');
    }

    /** @return HasMany<StudentOdeAchievement, $this> */
    public function achievements(): HasMany
    {
        return $this->hasMany(StudentOdeAchievement::class, 'student_ode_plan_id');
    }
}
