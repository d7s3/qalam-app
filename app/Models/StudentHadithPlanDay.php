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
        'hadith_path_day_id',
        'date',
        'day_name',
        'memorize_type',
        'memorize_amount',
        'from_hadith_id',
        'to_hadith_id',
        'from_line_number',
        'to_line_number',
        'review_from_hadith_id',
        'review_to_hadith_id',
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

    public function fromHadith(): BelongsTo
    {
        return $this->belongsTo(Hadith::class, 'from_hadith_id');
    }

    public function toHadith(): BelongsTo
    {
        return $this->belongsTo(Hadith::class, 'to_hadith_id');
    }

    public function reviewFromHadith(): BelongsTo
    {
        return $this->belongsTo(Hadith::class, 'review_from_hadith_id');
    }

    public function reviewToHadith(): BelongsTo
    {
        return $this->belongsTo(Hadith::class, 'review_to_hadith_id');
    }

    public function formatHadithRange($type = 'hifz'): ?string
    {
        $memorizeType = $this->memorize_type;
        if ($memorizeType === 'hadiths') {
            $fromHadith = $type === 'review' ? $this->reviewFromHadith : $this->fromHadith;
            $toHadith = $type === 'review' ? $this->reviewToHadith : $this->toHadith;

            if (! $fromHadith) {
                return null;
            }
            if (! $toHadith || $fromHadith->id === $toHadith->id) {
                return 'حديث: '.$fromHadith->name;
            }

            return 'من حديث: '.$fromHadith->name.' إلى: '.$toHadith->name;
        } else {
            $from = $type === 'review' ? $this->review_from_line_number : $this->from_line_number;
            $to = $type === 'review' ? $this->review_to_line_number : $this->to_line_number;
            $hadith = $type === 'review' ? $this->reviewFromHadith : $this->fromHadith;

            if (! $hadith || ! $from || ! $to) {
                return null;
            }

            $hadithName = 'حديث: '.$hadith->name;
            if ($from === $to) {
                return "{$hadithName} (السطر {$from})";
            }

            return "{$hadithName} (الأسطر {$from} - {$to})";
        }
    }
}
