<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HadithText extends Model
{
    use HasFactory;

    protected $table = 'hadith_texts';

    protected $fillable = [
        'name',
        'description',
    ];

    /** @return HasMany<HadithChapter, $this> */
    public function chapters(): HasMany
    {
        return $this->hasMany(HadithChapter::class, 'hadith_text_id');
    }

    /** @return HasMany<HadithPath, $this> */
    public function paths(): HasMany
    {
        return $this->hasMany(HadithPath::class, 'hadith_text_id');
    }

    /** @return HasMany<Hadith, $this> */
    public function hadiths(): HasMany
    {
        return $this->hasMany(Hadith::class, 'hadith_text_id');
    }
}
