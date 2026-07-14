<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoleScreenPermission extends Model
{
    protected $fillable = [
        'role_id',
        'screen_id',
        'enabled_by',
    ];

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

    /** @return BelongsTo<Manager, $this> */
    public function enabledBy(): BelongsTo
    {
        return $this->belongsTo(Manager::class, 'enabled_by');
    }
}
