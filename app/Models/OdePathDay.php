<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OdePathDay extends Model
{
    use HasFactory;

    protected $table = 'ode_path_days';

    protected $fillable = [
        'ode_path_id',
        'day_number',
        'date',
        'day_name',
        'from_verse_number',
        'to_verse_number',
        'review_from_verse_number',
        'review_to_verse_number',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    /** @return BelongsTo<OdePath, $this> */
    public function path(): BelongsTo
    {
        return $this->belongsTo(OdePath::class, 'ode_path_id');
    }

    /** @return HasMany<StudentOdeAchievement, $this> */
    public function achievements(): HasMany
    {
        return $this->hasMany(StudentOdeAchievement::class, 'ode_path_day_id');
    }

    public function formatOdeRange(string $type = 'hifz'): ?string
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
