<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentOdePlanDay extends Model
{
    protected $fillable = [
        'student_ode_plan_id',
        'date',
        'day_name',
        'from_verse_number',
        'to_verse_number',
        'review_from_verse_number',
        'review_to_verse_number',
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

    public function plan()
    {
        return $this->belongsTo(StudentOdePlan::class, 'student_ode_plan_id');
    }

    public function formatOdeRange($type = 'hifz')
    {
        $from = $type === 'review' ? $this->review_from_verse_number : $this->from_verse_number;
        $to = $type === 'review' ? $this->review_to_verse_number : $this->to_verse_number;

        if (! $from || ! $to) {
            return null;
        }

        if ($from === $to) {
            return "البيت {$from}";
        }

        return "الأبيات {$from} - {$to}";
    }
}
