<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One office letting another see an event.
 *
 * See the migration for why a grant is checked against its grantor rather than
 * deleted when the grantor loses sight.
 */
class CalendarEventGrant extends Model
{
    protected $fillable = [
        'academic_calendar_event_id',
        'role_id',
        'granted_by_role_id',
        'granted_by_id',
    ];

    /** @return BelongsTo<AcademicCalendarEvent, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(AcademicCalendarEvent::class, 'academic_calendar_event_id');
    }

    /** @return BelongsTo<Role, $this> */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /** @return BelongsTo<Role, $this> */
    public function grantedByRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'granted_by_role_id');
    }
}
