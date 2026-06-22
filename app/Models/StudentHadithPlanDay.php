<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentHadithPlanDay extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_hadith_plan_id',
        'date',
        'day_name',
        'from_line_number',
        'to_line_number',
        'review_from_line_number',
        'review_to_line_number',
        'hifz_achievement',
        'review_achievement',
        'hifz_graded_at',
        'review_graded_at',
    ];

    protected $casts = [
        'date' => 'date',
        'hifz_graded_at' => 'datetime',
        'review_graded_at' => 'datetime',
    ];

    /** @return BelongsTo<StudentHadithPlan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(StudentHadithPlan::class, 'student_hadith_plan_id');
    }

    public function formatHadithRange($type = 'hifz'): ?string
    {
        $from = $type === 'review' ? $this->review_from_line_number : $this->from_line_number;
        $to = $type === 'review' ? $this->review_to_line_number : $this->to_line_number;

        if (! $from || ! $to) {
            return null;
        }

        if ($from === $to) {
            return "السطر {$from}";
        }

        return "الأسطر {$from} - {$to}";
    }
}
