<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * One field of the self programme.
 *
 * This was an enum of five, and the reasoning beside it was that the programme
 * is defined as those five. The academy decided otherwise: a month may run on
 * three, a sixth may be wanted, one may be set aside for a term. So the list is
 * a table now — but the five keep their keys, and the interface the rest of the
 * application already speaks (`->value`, `label()`, `fixedUnit()`, `ordered()`)
 * is kept exactly, so nothing had to learn a new way of asking.
 */
class SelfProgramTrack extends Model
{
    /**
     * The five the programme began as, by key.
     *
     * Kept as constants so code that means a particular field says so without
     * a database round trip — the wird especially, which the recitation bridge
     * writes into by name.
     */
    public const QURAN_WIRD = 'quran_wird';

    public const MAQROU = 'maqrou';

    public const MASMOU = 'masmou';

    public const TAHDHEER = 'tahdheer';

    public const MAHFOUDH = 'mahfoudh';

    protected $fillable = [
        'key',
        'label',
        'default_unit',
        'fixed_unit',
        'icon',
        'is_system',
        'sort_order',
    ];

    protected $casts = [
        'is_system' => 'boolean',
        'sort_order' => 'integer',
    ];

    /** The key, under the name the application has always called it by. */
    protected function value(): Attribute
    {
        return Attribute::get(fn (): string => $this->key);
    }

    public function label(): string
    {
        return $this->attributes['label'];
    }

    /**
     * The unit no supervisor may override.
     *
     * The wird is written in mushaf pages by the recitation bridge, so choosing
     * another unit for it would break the arithmetic silently.
     */
    public function fixedUnit(): ?string
    {
        return $this->attributes['fixed_unit'] ?: null;
    }

    public function defaultUnit(): string
    {
        return $this->fixedUnit() ?? ($this->attributes['default_unit'] ?: 'وحدة');
    }

    public function icon(): string
    {
        return $this->attributes['icon'] ?: 'sparkles';
    }

    public function isQuranWird(): bool
    {
        return $this->key === self::QURAN_WIRD;
    }

    /**
     * Every field, in the order the programme reads them.
     *
     * @return Collection<int, self>
     */
    public static function ordered(): Collection
    {
        return static::orderBy('sort_order')->orderBy('id')->get();
    }

    /**
     * The fields running for a programme on a date.
     *
     * A field runs unless something set it aside, so the ordinary case costs
     * nothing to express and a lapsed exclusion restores it without anybody
     * remembering to.
     *
     * @return Collection<int, self>
     */
    public static function orderedFor(?int $stageId = null, ?string $date = null): Collection
    {
        $date ??= now('Asia/Riyadh')->format('Y-m-d');

        $setAside = SelfProgramTrackExclusion::query()
            ->where(fn ($q) => $q->whereNull('stage_id')->when($stageId, fn ($s) => $s->orWhere('stage_id', $stageId)))
            ->where(fn ($q) => $q->whereNull('starts_on')->orWhereDate('starts_on', '<=', $date))
            ->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', $date))
            ->pluck('self_program_track_id')
            ->all();

        return static::ordered()->reject(fn (self $track) => in_array($track->id, $setAside, true))->values();
    }

    /** The wird, by the one name the bridge knows it by. */
    public static function quranWird(): ?self
    {
        return static::where('key', self::QURAN_WIRD)->first();
    }

    public static function findByKey(?string $key): ?self
    {
        return $key ? static::where('key', $key)->first() : null;
    }

    /** @return HasMany<SelfProgramTrackExclusion, $this> */
    public function exclusions(): HasMany
    {
        return $this->hasMany(SelfProgramTrackExclusion::class);
    }
}
