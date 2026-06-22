<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HadithChapter extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    /** @return HasMany<Hadith, $this> */
    public function hadiths(): HasMany
    {
        return $this->hasMany(Hadith::class);
    }
}
