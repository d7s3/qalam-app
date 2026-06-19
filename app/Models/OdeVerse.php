<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OdeVerse extends Model
{
    protected $fillable = [
        'ode_id',
        'verse_number',
        'sadr',
        'ajuz',
    ];

    public function ode()
    {
        return $this->belongsTo(Ode::class);
    }
}
