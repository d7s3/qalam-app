<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One person's answer for one occurrence.
 *
 * See the migration for why this sits beside the daily register rather than
 * replacing it.
 */
class OccurrenceAttendance extends Model
{
    public const PRESENT = 'present';

    public const ABSENT = 'absent';

    public const LATE = 'late';

    public const EXCUSED = 'excused';

    /** The answers that mean he was there, however late. */
    public const ATTENDED = [self::PRESENT, self::LATE];

    protected $fillable = [
        'academic_calendar_event_id',
        'date',
        'user_id',
        'role',
        'status',
        'self_recorded',
        'recorded_by',
        'note',
    ];

    protected $casts = [
        'date' => 'date',
        'self_recorded' => 'boolean',
    ];

    /** @return BelongsTo<AcademicCalendarEvent, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(AcademicCalendarEvent::class, 'academic_calendar_event_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
