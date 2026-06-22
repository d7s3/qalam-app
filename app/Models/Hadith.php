<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Hadith extends Model
{
    use HasFactory;

    protected $fillable = [
        'hadith_chapter_id',
        'name',
        'sanad',
        'ruling',
    ];

    /** @return BelongsTo<HadithChapter, $this> */
    public function chapter(): BelongsTo
    {
        return $this->belongsTo(HadithChapter::class, 'hadith_chapter_id');
    }

    /** @return HasMany<HadithLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(HadithLine::class)->orderBy('line_number');
    }
}
