<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A word announced to an office and those beneath it.
 *
 * See the migration for why this is not the conversation the application
 * already has.
 */
class PortalMessage extends Model
{
    protected $fillable = [
        'sender_id',
        'sender_role',
        'title',
        'body',
        'show_sender',
        'starts_on',
        'ends_on',
    ];

    protected $casts = [
        'show_sender' => 'boolean',
        'starts_on' => 'date',
        'ends_on' => 'date',
    ];

    /**
     * Messages whose day has come and not gone.
     *
     * Compared as dates: the cast writes `Y-m-d H:i:s`, and text comparison
     * would lose the first and last day of the message's own life.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeLive(Builder $query, ?string $on = null): void
    {
        $on ??= now('Asia/Riyadh')->format('Y-m-d');

        $query->where(fn (Builder $q) => $q->whereNull('starts_on')->orWhereDate('starts_on', '<=', $on))
            ->where(fn (Builder $q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', $on));
    }

    /** Who it is from, or nobody, as the sender chose. */
    public function attribution(): ?string
    {
        return $this->show_sender ? $this->sender?->name : null;
    }

    /** @return BelongsTo<User, $this> */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /** @return HasMany<PortalMessageAudience, $this> */
    public function audiences(): HasMany
    {
        return $this->hasMany(PortalMessageAudience::class);
    }

    /** @return HasMany<PortalMessageRead, $this> */
    public function reads(): HasMany
    {
        return $this->hasMany(PortalMessageRead::class);
    }
}
