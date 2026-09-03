<?php

namespace App\Models;

use App\Support\Access;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserScreenOverride extends Model
{
    protected $fillable = [
        'user_id',
        'screen_id',
        'is_allowed',
        'set_by',
    ];

    protected $casts = [
        'is_allowed' => 'boolean',
    ];

    protected static function booted(): void
    {
        // What was read is let go on every write, so a screen that changes an
        // exception and reads the table straight back sees the change rather
        // than the state before it.
        $forget = fn () => Access::forget();

        static::saved($forget);
        static::deleted($forget);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Screen, $this> */
    public function screen(): BelongsTo
    {
        return $this->belongsTo(Screen::class);
    }

    /** @return BelongsTo<User, $this> */
    public function setBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'set_by');
    }
}
