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
        'hadith_path_id',
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

    /** @return BelongsTo<HadithPath, $this> */
    public function path(): BelongsTo
    {
        return $this->belongsTo(HadithPath::class, 'hadith_path_id');
    }

    /** @return HasMany<StudentHadithAchievement, $this> */
    public function achievements(): HasMany
    {
        return $this->hasMany(StudentHadithAchievement::class, 'student_hadith_plan_id');
    }
}
