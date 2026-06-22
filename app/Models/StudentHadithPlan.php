<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudentHadithPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'hadith_id',
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

    /** @return BelongsTo<Hadith, $this> */
    public function hadith(): BelongsTo
    {
        return $this->belongsTo(Hadith::class);
    }

    /** @return HasMany<StudentHadithPlanDay, $this> */
    public function days(): HasMany
    {
        return $this->hasMany(StudentHadithPlanDay::class, 'student_hadith_plan_id');
    }
}
