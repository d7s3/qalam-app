<?php

namespace App\Models;

use App\Services\GamificationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StudentSelfProgramEntry extends Model
{
    /**
     * The wird as it was confirmed, rather than read off a graded day.
     *
     * Named for the student because it is his wird and he is usually the one
     * who writes it; on «قرآني إجمالي» his teacher may write the same row
     * instead, and `recorded_by` says which of them did. One row either way —
     * a second source would be a second row, and the pages counted twice.
     */
    public const SOURCE_STUDENT = 'student';

    /** Written for him when his teacher recorded a recitation. */
    public const SOURCE_TASMEEH = 'tasmeeh';

    protected $fillable = [
        'student_id',
        'self_program_item_id',
        'entry_date',
        'amount_done',
        'source',
        'recorded_by_type',
        'recorded_by_id',
    ];

    /**
     * Whose hand wrote it.
     *
     * On «إجمالي» the student and his teacher write the same row, so the row is
     * the only place the answer can live.
     *
     * @return MorphTo<Model, $this>
     */
    public function recordedBy(): MorphTo
    {
        return $this->morphTo();
    }

    protected static function booted(): void
    {
        // A week's milestones are a fact about how much of it is done, so they
        // are re-read whenever that changes — including when an entry is
        // removed, which can take a student back below a threshold he passed.
        $sync = function (self $entry) {
            $week = $entry->item?->week;
            $student = $entry->student;

            if ($week && $student) {
                GamificationService::syncSelfProgramWeekXP($student, $week);
            }
        };

        static::saved($sync);
        static::deleted($sync);
    }

    protected $casts = [
        'entry_date' => 'date',
        'amount_done' => 'decimal:2',
    ];

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<SelfProgramItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(SelfProgramItem::class, 'self_program_item_id');
    }
}
