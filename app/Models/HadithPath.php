<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HadithPath extends Model
{
    use HasFactory;

    protected $table = 'hadith_paths';

    protected $fillable = [
        'hadith_text_id',
        'name',
        'memorize_type',
        'memorize_amount',
        'start_date',
    ];

    protected $casts = [
        'start_date' => 'date',
    ];

    /** @return BelongsTo<HadithText, $this> */
    public function text(): BelongsTo
    {
        return $this->belongsTo(HadithText::class, 'hadith_text_id');
    }

    /** @return HasMany<HadithPathDay, $this> */
    public function days(): HasMany
    {
        return $this->hasMany(HadithPathDay::class, 'hadith_path_id')->orderBy('day_number', 'asc');
    }
}
