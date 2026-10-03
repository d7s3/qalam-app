<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class TurnReservationSession extends Model
{
    protected $fillable = [
        'teacher_id',
        'start_date',
        'end_date',
        'days_of_week',
        'start_time',
        'end_time',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'days_of_week' => 'array',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
    ];

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'teacher_id');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(TurnReservation::class);
    }

    /**
     * Sessions a student may book a turn in: those run by a teacher of his
     * own cohort. A student in no cohort has none.
     */
    public function scopeOpenTo(Builder $query, Student $student): void
    {
        $query->whereIn('teacher_id', DB::table('circle_teacher')
            ->where('circle_id', $student->circle_id ?? 0)
            ->select('teacher_id'));
    }

    public function isActiveToday(): bool
    {
        $now = Carbon::now('Asia/Riyadh');
        $todayStr = $now->format('Y-m-d');

        // Check date range
        if ($todayStr < $this->start_date->format('Y-m-d') || $todayStr > $this->end_date->format('Y-m-d')) {
            return false;
        }

        // Check days of week (Carbon uses 0 for Sunday, 6 for Saturday)
        // Ensure days_of_week contains strings or ints that match the current day of week
        $currentDayOfWeek = (string) $now->dayOfWeek;
        $allowedDays = array_map('strval', $this->days_of_week ?? []);

        if (! in_array($currentDayOfWeek, $allowedDays)) {
            return false;
        }

        return true;
    }

    public function isActiveNow(): bool
    {
        if (! $this->isActiveToday()) {
            return false;
        }

        $now = Carbon::now('Asia/Riyadh');
        $currentTime = $now->format('H:i:s');
        $startTime = $this->start_time->format('H:i:s');
        $endTime = $this->end_time->format('H:i:s');

        if ($startTime <= $endTime) {
            return $currentTime >= $startTime && $currentTime <= $endTime;
        } else {
            // Spans across midnight
            return $currentTime >= $startTime || $currentTime <= $endTime;
        }
    }
}
