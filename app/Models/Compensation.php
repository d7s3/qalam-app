<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A debt from a day that has passed.
 *
 * It does not get rescheduled onto a particular week and then missed again on
 * that one too — it simply stays open and travels with him. See the migration
 * for why it is kept out of the current week's reckoning.
 */
class Compensation extends Model
{
    /**
     * Named outright: the inflector reads "compensation" as uncountable and
     * would look for a table by that name.
     */
    protected $table = 'compensations';

    public const OPEN = 'open';

    public const DONE = 'done';

    public const FORMATIVE = 'formative';

    public const SCIENTIFIC = 'scientific';

    protected $fillable = [
        'user_id',
        'kind',
        'label',
        'detail',
        'source_key',
        'original_date',
        'status',
        'completed_at',
        'completed_by',
        'note',
    ];

    protected $casts = [
        'original_date' => 'date',
        'completed_at' => 'datetime',
    ];

    /** @param  Builder<$this>  $query */
    public function scopeOpen(Builder $query): void
    {
        $query->where('status', self::OPEN);
    }

    /** How many weeks it has been carried — what makes a debt visible as old. */
    public function weeksCarried(): int
    {
        return (int) $this->original_date->diffInWeeks(now());
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}
