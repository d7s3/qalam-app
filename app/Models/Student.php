<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRole;

class Student extends User
{
    use BelongsToRole;

    public const ROLE = 'student';
}
