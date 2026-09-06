<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Something worth meeting on opening the application.
 *
 * Nothing here judges whether a hadith is sound — no program can. What it does
 * is refuse to show anything nobody has said may be shown, and carry the
 * attribution and the grading as fields rather than as a promise.
 */
class Motivation extends Model
{
    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    public const AYAH = 'ayah';

    public const HADITH = 'hadith';

    public const ATHAR = 'athar';

    public const POETRY = 'poetry';

    /** The only gradings a hadith may be shown under. */
    public const ACCEPTED_GRADES = ['صحيح', 'حسن'];

    protected $fillable = [
        'kind',
        'text',
        'source',
        'grade',
        'contributed_by',
        'contributor_role',
        'status',
        'reviewed_by',
        'reviewed_at',
        'review_note',
    ];

    protected $casts = ['reviewed_at' => 'datetime'];

    /** @param  Builder<$this>  $query */
    public function scopeShowable(Builder $query): void
    {
        $query->where('status', self::APPROVED);
    }

    /** Whether a hadith carries a grading the academy accepts. */
    public function gradeIsAcceptable(): bool
    {
        if ($this->kind !== self::HADITH) {
            return true;
        }

        return in_array(trim((string) $this->grade), self::ACCEPTED_GRADES, true);
    }

    public function kindLabel(): string
    {
        return match ($this->kind) {
            self::AYAH => __('آية'),
            self::HADITH => __('حديث'),
            self::ATHAR => __('أثر'),
            self::POETRY => __('بيت'),
            default => __('شاهد'),
        };
    }

    /** @return BelongsTo<User, $this> */
    public function contributor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'contributed_by');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
