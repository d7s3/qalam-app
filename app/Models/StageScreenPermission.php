<?php

namespace App\Models;

use App\Support\Access;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What one role may see inside one programme.
 *
 * The central grant says what the job needs everywhere; this says what it needs
 * here, and it is consulted before the central grant is. Only differences are
 * stored — see the migration for why absence is the useful state.
 */
class StageScreenPermission extends Model
{
    protected $fillable = [
        'stage_id',
        'role_id',
        'screen_id',
        'is_allowed',
        'set_by',
    ];

    protected $casts = [
        'is_allowed' => 'boolean',
    ];

    protected static function booted(): void
    {
        // What was read is let go on every write, so a screen that changes a
        // programme's grant and reads the table straight back sees the change
        // rather than the state before it.
        $forget = fn () => Access::forget();

        static::saved($forget);
        static::deleted($forget);
    }

    /** @return BelongsTo<Stage, $this> */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class);
    }

    /** @return BelongsTo<Role, $this> */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
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
