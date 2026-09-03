<?php

namespace App\Models;

use App\Support\SelfProgramTrack;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SelfProgramItem extends Model
{
    protected $fillable = [
        'self_program_week_id',
        'track',
        'description',
        'target_amount',
        'unit',
    ];

    protected $casts = [
        'track' => SelfProgramTrack::class,
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

    public function displayUnit(): string
    {
        return $this->track->fixedUnit() ?? ($this->unit ?: $this->track->defaultUnit());
    }
}
