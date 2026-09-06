<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Screen extends Model
{
    protected $fillable = [
        'owner_role_id',
        'group_label',
        'route_name',
        'label',
        'icon',
        'sort_order',
        'is_protected',
        'view',
        'view_data',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_protected' => 'boolean',
            'view_data' => 'array',
        ];
    }

    /** @return BelongsTo<Role, $this> */
    public function ownerRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'owner_role_id');
    }

    /** @return HasMany<RoleScreenPermission, $this> */
    public function permissions(): HasMany
    {
        return $this->hasMany(RoleScreenPermission::class);
    }
}
