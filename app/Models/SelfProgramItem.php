<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SelfProgramItem extends Model
{
    protected $fillable = [
        'self_program_week_id',
        'track',
        'content_url',
        'content_label',
        'description',
        'target_amount',
        'unit',
    ];

    protected $casts = [
        'target_amount' => 'decimal:2',
    ];

    /** @return BelongsTo<SelfProgramWeek, $this> */
    public function week(): BelongsTo
    {
        return $this->belongsTo(SelfProgramWeek::class, 'self_program_week_id');
    }

    /** @return HasMany<StudentSelfProgramEntry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(StudentSelfProgramEntry::class, 'self_program_item_id');
    }

    /** @return HasMany<SelfProgramDayOverride, $this> */
    public function dayOverrides(): HasMany
    {
        return $this->hasMany(SelfProgramDayOverride::class, 'self_program_item_id');
    }

    /**
     * The field this item belongs to.
     *
     * The column holds the key and always has, so every week ever written still
     * reads. Resolving it here keeps `$item->track->label()` working exactly as
     * it did when the five were an enum — the callers never learned the change.
     */
    protected function track(): Attribute
    {
        return Attribute::make(
            get: fn (?string $key) => SelfProgramTrack::findByKey($key),
            set: fn ($value) => ['track' => $value instanceof SelfProgramTrack ? $value->key : $value],
        );
    }

    /**
     * Whether this track carries a link to the thing itself.
     *
     * The Quran is in the application; every other track points outward at a
     * text the academy holds somewhere else.
     */
    public function carriesContentLink(): bool
    {
        return ! $this->track?->isQuranWird();
    }

    /** What to call the link when nobody named it. */
    public function contentLinkLabel(): string
    {
        return $this->content_label ?: __('افتح المحتوى');
    }

    public function displayUnit(): string
    {
        return $this->track->fixedUnit() ?? ($this->unit ?: $this->track->defaultUnit());
    }
}
