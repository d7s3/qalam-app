<?php

namespace App\Models;

use App\Support\SelfProgramUnit;
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
        'books',
        'target_amount',
        'unit',
    ];

    protected $casts = [
        'target_amount' => 'decimal:2',
        'books' => 'array',
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

    /**
     * The unit this item was actually written in.
     *
     * The field's own unit used to win over the item's, which quietly reread
     * every week written before the vocabulary existed: an item asking for two
     * lessons of listening became two minutes, and ninety minutes of listening
     * then reported as a hundred and forty-five per cent of it.
     *
     * What was written is what is shown. A field settles the unit of everything
     * written from now on — that happens where it is written — and says nothing
     * about what is already on the page.
     */
    public function displayUnit(): string
    {
        return $this->unit ?: $this->track->defaultUnit();
    }

    /**
     * Whether this item is measured in a word its field no longer accepts.
     *
     * True only of weeks written before the field had a vocabulary. The amount
     * beside it means what it meant when somebody wrote it, and nobody but that
     * person can say what it is in the new unit — so it is flagged, not
     * converted.
     */
    public function hasStaleUnit(): bool
    {
        return $this->unit !== null
            && $this->unit !== ''
            && ! $this->track->allowsUnit($this->unit);
    }

    /**
     * Each book the field asks for, with its pages: «العواصم: من ص 265 إلى ص 395».
     *
     * @return list<string>
     */
    public function bookRanges(): array
    {
        return collect($this->books ?? [])
            ->filter(fn ($book) => filled($book['title'] ?? null))
            ->map(fn (array $book) => self::bookRange($book))
            ->values()
            ->all();
    }

    /**
     * @param  array{title?: string, from?: int|null, to?: int|null}  $book
     */
    public static function bookRange(array $book): string
    {
        $pages = trim(
            (filled($book['from'] ?? null) ? 'من ص '.$book['from'] : '')
            .' '.(filled($book['to'] ?? null) ? 'إلى ص '.$book['to'] : '')
        );

        return trim($book['title']).($pages !== '' ? ': '.$pages : '');
    }

    /** What the field asks for, in words: its books and pages, else its description. */
    public function wanted(): string
    {
        return $this->bookRanges() !== [] ? implode('، ', $this->bookRanges()) : (string) $this->description;
    }

    /** Whether this item is a length of time rather than a count of things. */
    public function isDuration(): bool
    {
        return SelfProgramUnit::isDuration($this->displayUnit());
    }

    /**
     * An amount of this item, said the way it would be read aloud — «ساعة و٣٠
     * دقيقة», «3 أبيات», «صفحتان».
     */
    public function say(?float $amount): string
    {
        return SelfProgramUnit::say((float) $amount, $this->displayUnit());
    }
}
