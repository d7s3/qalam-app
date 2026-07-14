<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRole;

class Teacher extends User
{
    use BelongsToRole;

    public const ROLE = 'teacher';
}
