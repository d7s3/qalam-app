<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRole;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends User
{
    use BelongsToRole;

    public const ROLE = 'student';

    public function commitmentAlerts(): HasMany
    {
        return $this->hasMany(CommitmentAlert::class, 'student_id');
    }
}
