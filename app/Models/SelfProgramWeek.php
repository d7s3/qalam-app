<?php

namespace App\Models;

use App\Services\GamificationService;
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
        'merged_days',
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
        'merged_days' => 'array',
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
    /**
     * A week already covering any of these days, for the same readers.
     *
     * Weeks are numbered, and the unique index holds the numbers apart — which
     * says nothing about their dates. Two weeks numbered 2 and 3 could both run
     * from the sixth to the twelfth, and a student asking «what is my week»
     * would be handed whichever the database returned first. He would see one
     * programme, do it, and be marked against the other.
     *
     * A cohort's week and its programme's are not rivals: the cohort's is the
     * more particular of the two and the reading rules already prefer it. So
     * only weeks with the same reach are compared.
     *
     * @return Builder<self>
     */
    public static function overlapping(
        ?int $stageId,
        ?int $circleId,
        string $programType,
        string $from,
        string $to,
        ?int $exceptId = null,
    ) {
        return static::query()
            ->when($stageId, fn ($q) => $q->where('stage_id', $stageId), fn ($q) => $q->whereNull('stage_id'))
            ->where('program_type', $programType)
            ->when($circleId, fn ($q) => $q->where('circle_id', $circleId), fn ($q) => $q->whereNull('circle_id'))
            ->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))
            // Matched as dates: the cast writes `Y-m-d H:i:s`, and a plain
            // comparison against `Y-m-d` would find nothing at all.
            ->whereDate('starts_on', '<=', $to)
            ->whereDate('ends_on', '>=', $from);
    }

    /** The week already covering these days, if there is one. */
    public static function clashOn(
        ?int $stageId,
        ?int $circleId,
        string $programType,
        string $from,
        string $to,
        ?int $exceptId = null,
    ): ?self {
        return static::overlapping($stageId, $circleId, $programType, $from, $to, $exceptId)->first();
    }

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
        // Only the fields running for this programme now: one set aside for the
        // term is not created empty and then shown as unmet.
        foreach (SelfProgramTrack::orderedFor($this->stage_id, $this->starts_on?->format('Y-m-d')) as $track) {
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
