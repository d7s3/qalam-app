<?php

namespace App\Models;

use Database\Factories\CircleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'description', 'stage_id', 'is_quranic', 'self_program_unlock_on_completion'])]
class Circle extends Model
{
    /** @use HasFactory<CircleFactory> */
    use HasFactory;

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'is_quranic' => 'boolean',
        'self_program_unlock_on_completion' => 'boolean',
    ];

    /**
     * What to call this one.
     *
     * A cohort is a دفعة; a cohort whose content is Quranic is a حلقة. The two
     * sit at the same level under a programme and differ only in that, so the
     * word follows the cohort rather than the screen — and a list holding both
     * reads correctly without the screen knowing which it holds.
     *
     * @return Attribute<string, never>
     */
    protected function noun(): Attribute
    {
        return Attribute::get(fn (): string => $this->is_quranic ? 'حلقة' : 'دفعة');
    }

    /** @return BelongsTo<Stage, $this> */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class);
    }

    /** @return BelongsToMany<Teacher, $this> */
    public function teachers(): BelongsToMany
    {
        return $this->belongsToMany(Teacher::class, 'circle_teacher', 'circle_id', 'teacher_id');
    }

    /** @return HasMany<Attendance, $this> */
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    /** @return HasMany<Student, $this> */
    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }
}
