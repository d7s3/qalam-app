<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ode extends Model
{
    // OdeFactory has existed since the odes did, and OdePathFactory calls
    // Ode::factory() — which threw, because the trait that makes that method
    // real was never put on the model.
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
    ];

    public function verses()
    {
        return $this->hasMany(OdeVerse::class)->orderBy('verse_number', 'asc');
    }

    public function paths()
    {
        return $this->hasMany(OdePath::class);
    }
}
