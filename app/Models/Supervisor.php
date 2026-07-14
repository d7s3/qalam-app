<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRole;

class Supervisor extends User
{
    use BelongsToRole;

    public const ROLE = 'supervisor';
}
