<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ExamLevel extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'direction',
        'start_ayah_id',
        'end_ayah_id',
        'previous_level_id',
    ];

    public function startAyah(): BelongsTo
    {
        return $this->belongsTo(Ayah::class, 'start_ayah_id');
    }

    public function endAyah(): BelongsTo
    {
        return $this->belongsTo(Ayah::class, 'end_ayah_id');
    }

    public function studentExams(): HasMany
    {
        return $this->hasMany(StudentExam::class);
    }

    public function previousLevel(): BelongsTo
    {
        return $this->belongsTo(ExamLevel::class, 'previous_level_id');
    }

    public function nextLevel(): HasOne
    {
        return $this->hasOne(ExamLevel::class, 'previous_level_id');
    }
}
