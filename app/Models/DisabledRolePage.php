<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisabledRolePage extends Model
{
    protected $fillable = [
        'role',
        'route',
        'disabled_by',
    ];

    /** @return BelongsTo<Manager, $this> */
    public function disabledBy(): BelongsTo
    {
        return $this->belongsTo(Manager::class, 'disabled_by');
    }
}
