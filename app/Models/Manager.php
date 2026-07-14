<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRole;

class Manager extends User
{
    use BelongsToRole;

    public const ROLE = 'manager';
}
