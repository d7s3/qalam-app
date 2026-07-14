<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRole;

class Staff extends User
{
    use BelongsToRole;

    public const ROLE = 'staff';

    /**
     * The manager who approved this staff account. Not an Eloquent relation
     * (approved_by lives on the per-role `user_roles` row, not `users`), so it
     * can't be eager-loaded — nothing in the app currently does.
     */
    public function approvedBy(): ?User
    {
        return $this->roleRecord?->approvedBy;
    }
}
