<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ode extends Model
{
    protected $fillable = [
        'name',
        'description',
    ];

    public function verses()
    {
        return $this->hasMany(OdeVerse::class)->orderBy('verse_number', 'asc');
    }

    public function plans()
    {
        return $this->hasMany(StudentOdePlan::class);
    }
}
