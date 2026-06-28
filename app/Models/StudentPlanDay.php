<?php

namespace App\Models;

use App\Services\GamificationService;
use Illuminate\Database\Eloquent\Model;

class StudentPlanDay extends Model
{
    protected $fillable = [
        'student_plan_id',
        'date',
        'day_name',
        'from_ayah_id',
        'to_ayah_id',
        'review_from_ayah_id',
        'review_to_ayah_id',
        'hifz_achievement',
        'review_achievement',
        'hifz_graded_at',
        'review_graded_at',
    ];

    protected static function booted(): void
    {
        // Drop any gamification points tied to this day when it is deleted, so no
        // orphaned transactions inflate student/team scores.
        static::deleting(function (StudentPlanDay $day) {
            GamificationService::clearTransactionsForReference(self::class, $day->id);
        });
    }

    protected $casts = [
        'date' => 'date',
        'hifz_graded_at' => 'datetime',
        'review_graded_at' => 'datetime',
    ];

    public function plan()
    {
        return $this->belongsTo(StudentPlan::class, 'student_plan_id');
    }

    public function fromAyah()
    {
        return $this->belongsTo(Ayah::class, 'from_ayah_id');
    }

    public function toAyah()
    {
        return $this->belongsTo(Ayah::class, 'to_ayah_id');
    }

    public function reviewFromAyah()
    {
        return $this->belongsTo(Ayah::class, 'review_from_ayah_id');
    }

    public function reviewToAyah()
    {
        return $this->belongsTo(Ayah::class, 'review_to_ayah_id');
    }

    public function formatRange($type = 'hifz', $reverseFill = true)
    {
        $from = $type === 'review' ? $this->reviewFromAyah : $this->fromAyah;
        $to = $type === 'review' ? $this->reviewToAyah : $this->toAyah;

        if (! $from || ! $to) {
            return null;
        }

        $fromSurah = $from->surah;
        $toSurah = $to->surah;

        $vFrom = $from->verse_number;
        $vTo = $to->verse_number;

        if ($fromSurah->id === $toSurah->id) {
            if ($vFrom == 1 && $vTo == $fromSurah->verses_count) {
                return $fromSurah->name_arabic;
            }

            return $fromSurah->name_arabic.' '.$vFrom.'-'.$vTo;
        }

        // Multiple surahs
        $isFromFullStart = ($vFrom == 1);
        $isToFullEnd = ($vTo == $toSurah->verses_count);

        if ($isFromFullStart && $isToFullEnd) {
            return $fromSurah->name_arabic.' - '.$toSurah->name_arabic;
        }

        $firstPart = $fromSurah->name_arabic;
        if (! $isFromFullStart) {
            $firstPart .= ' '.$vFrom;
        }

        $endPart = $toSurah->name_arabic;
        if (! $isToFullEnd) {
            $endPart .= ' '.$vTo;
        }

        return $firstPart.' الى '.$endPart;
    }
}
