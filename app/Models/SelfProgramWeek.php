<?php

namespace App\Models;

use App\Services\GamificationService;
use App\Support\SelfProgramTrack;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SelfProgramWeek extends Model
{
    public const TYPE_SELF = 'self';

    public const TYPE_ENRICHMENT = 'enrichment';

    protected $fillable = [
        'stage_id',
        'circle_id',
        'program_type',
        'week_number',
        'starts_on',
        'ends_on',
        'created_by_id',
        'created_by_type',
    ];

    protected static function booted(): void
    {
        // Drop the milestone points a week earned when the week itself goes, so
        // no orphaned transaction keeps inflating a leaderboard.
        static::deleting(function (self $week) {
            GamificationService::clearTransactionsForReference(self::class, $week->id);
        });
    }

    protected $casts = [
        'starts_on' => 'date',
        'ends_on' => 'date',
        'week_number' => 'integer',
    ];

    /** @return BelongsTo<Stage, $this> */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class);
    }

    /** @return BelongsTo<Circle, $this> */
    public function circle(): BelongsTo
    {
        return $this->belongsTo(Circle::class);
    }

    /** @return HasMany<SelfProgramItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(SelfProgramItem::class);
    }

    /** @return MorphTo<Model, $this> */
    public function createdBy(): MorphTo
    {
        return $this->morphTo('created_by');
    }

    /**
     * The stage whose calendar lays this week out. An enrichment week belongs to
     * a circle, and takes its working days from the stage that circle sits in.
     */
    public function effectiveStageId(): ?int
    {
        return $this->stage_id ?? $this->circle?->stage_id;
    }

    /**
     * Fill in the five tracks this week is missing, so a week always presents
     * as the whole programme rather than however much of it has been written.
     */
    public function ensureAllTracks(): void
    {
        foreach (SelfProgramTrack::ordered() as $track) {
            $this->items()->firstOrCreate(
                ['track' => $track->value],
                ['target_amount' => 0, 'unit' => $track->defaultUnit()],
            );
        }
    }

    /** @param  Builder<$this>  $query */
    public function scopeSelf($query): Builder
    {
        return $query->where('program_type', self::TYPE_SELF);
    }

    /** @param  Builder<$this>  $query */
    public function scopeEnrichment($query): Builder
    {
        return $query->where('program_type', self::TYPE_ENRICHMENT);
    }
}
