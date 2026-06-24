<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HadithChapter extends Model
{
    use HasFactory;

    protected $fillable = [
        'hadith_text_id',
        'name',
    ];

    /** @return BelongsTo<HadithText, $this> */
    public function text(): BelongsTo
    {
        return $this->belongsTo(HadithText::class, 'hadith_text_id');
    }

    /** @return HasMany<Hadith, $this> */
    public function hadiths(): HasMany
    {
        return $this->hasMany(Hadith::class);
    }
}
