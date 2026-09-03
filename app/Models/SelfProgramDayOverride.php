<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SelfProgramDayOverride extends Model
{
    protected $fillable = [
        'self_program_item_id',
        'circle_id',
        'student_id',
        'day_date',
        'amount',
    ];

    protected static function booted(): void
    {
        // Kept in step with the two nullable columns it stands in for, so the
        // unique index has something non-null to hold on to.
        static::saving(function (self $override) {
            $override->scope_key = $override->student_id
                ? 's:'.$override->student_id
                : 'c:'.$override->circle_id;
        });
    }

    protected $casts = [
        'day_date' => 'date',
        'amount' => 'decimal:2',
    ];

    /** @return BelongsTo<SelfProgramItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(SelfProgramItem::class, 'self_program_item_id');
    }

    /** @return BelongsTo<Circle, $this> */
    public function circle(): BelongsTo
    {
        return $this->belongsTo(Circle::class);
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
