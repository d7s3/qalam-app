<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRole;

class Guardian extends User
{
    use BelongsToRole;

    public const ROLE = 'guardian';
}
