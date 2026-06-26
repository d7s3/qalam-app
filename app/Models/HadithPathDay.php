<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HadithPathDay extends Model
{
    use HasFactory;

    protected $table = 'hadith_path_days';

    protected $fillable = [
        'hadith_path_id',
        'day_number',
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
    ];

    protected $casts = [
        'date' => 'date',
    ];

    /** @return BelongsTo<HadithPath, $this> */
    public function path(): BelongsTo
    {
        return $this->belongsTo(HadithPath::class, 'hadith_path_id');
    }

    /** @return BelongsTo<Hadith, $this> */
    public function fromHadith(): BelongsTo
    {
        return $this->belongsTo(Hadith::class, 'from_hadith_id');
    }

    /** @return BelongsTo<Hadith, $this> */
    public function toHadith(): BelongsTo
    {
        return $this->belongsTo(Hadith::class, 'to_hadith_id');
    }

    /** @return BelongsTo<Hadith, $this> */
    public function reviewFromHadith(): BelongsTo
    {
        return $this->belongsTo(Hadith::class, 'review_from_hadith_id');
    }

    /** @return BelongsTo<Hadith, $this> */
    public function reviewToHadith(): BelongsTo
    {
        return $this->belongsTo(Hadith::class, 'review_to_hadith_id');
    }

    public function formatHadithRange($type = 'hifz'): ?string
    {
        $isReview = ($type === 'review');
        $fromLine = $isReview ? $this->review_from_line_number : $this->from_line_number;
        $toLine = $isReview ? $this->review_to_line_number : $this->to_line_number;
        $fromHadith = $isReview ? $this->reviewFromHadith : $this->fromHadith;
        $toHadith = $isReview ? $this->reviewToHadith : $this->toHadith;

        if ($fromLine && $toLine) {
            $hadithName = $fromHadith ? $fromHadith->name : '';
            if ($fromLine === $toLine) {
                return ($hadithName ? "{$hadithName}: " : '')."السطر {$fromLine}";
            }

            return ($hadithName ? "{$hadithName}: " : '')."الأسطر {$fromLine} - {$toLine}";
        }

        if ($fromHadith && $toHadith) {
            if ($fromHadith->id === $toHadith->id) {
                return "حديث: {$fromHadith->name}";
            }

            return "الأحاديث: {$fromHadith->name} إلى {$toHadith->name}";
        }

        return null;
    }

    /** @return HasMany<StudentHadithAchievement, $this> */
    public function achievements(): HasMany
    {
        return $this->hasMany(StudentHadithAchievement::class, 'hadith_path_day_id');
    }
}
