<?php

namespace App\Models;

use App\Models\Concerns\HasProfile;
use Database\Factories\StudentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\TwoFactorAuthenticatable;

class Student extends Authenticatable
{
    /** @use HasFactory<StudentFactory> */
    use HasFactory, HasProfile, Notifiable, TwoFactorAuthenticatable;

    protected static function booted(): void
    {
        // When a student is deleted, release any form responses linked to them so
        // they return to the unprocessed pool (and can be re-created or re-linked).
        static::deleting(function (Student $student) {
            FormResponse::where('student_id', $student->id)
                ->update(['student_id' => null, 'is_processed' => false]);
        });
    }

    /** @return BelongsTo<Circle, $this> */
    public function circle(): BelongsTo
    {
        return $this->belongsTo(Circle::class);
    }

    /** @return BelongsTo<Stage, $this> */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class);
    }

    /**
     * The student's effective stage id. The circle's stage always wins when the
     * student is assigned to a circle; the direct stage_id only applies to
     * circle-less (still registering) students.
     */
    public function getEffectiveStageIdAttribute(): ?int
    {
        return $this->circle?->stage_id ?? $this->stage_id;
    }

    /** @return BelongsTo<Guardian, $this> */
    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class, 'guardian_id');
    }

    /** @return HasMany<Attendance, $this> */
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    /** @return HasMany<StudentPlan, $this> */
    public function plans(): HasMany
    {
        return $this->hasMany(StudentPlan::class);
    }

    /** @return HasMany<StudentOdePlan, $this> */
    public function odePlans(): HasMany
    {
        return $this->hasMany(StudentOdePlan::class);
    }

    /** @return HasMany<FormResponse, $this> */
    public function formResponses(): HasMany
    {
        return $this->hasMany(FormResponse::class);
    }

    /** @return HasMany<StudentStatusHistory, $this> */
    public function statusHistories(): HasMany
    {
        return $this->hasMany(StudentStatusHistory::class)->orderBy('start_date', 'desc');
    }

    /** @return BelongsToMany<GamificationTeam, $this> */
    public function gamificationTeams(): BelongsToMany
    {
        return $this->belongsToMany(GamificationTeam::class, 'gamification_team_student', 'student_id', 'team_id')
            ->withPivot('role');
    }

    /** @return BelongsToMany<GamificationTrack, $this> */
    public function gamificationTracks(): BelongsToMany
    {
        return $this->belongsToMany(GamificationTrack::class, 'gamification_track_student', 'student_id', 'track_id');
    }

    /** @return HasMany<GamificationTransaction, $this> */
    public function gamificationTransactions(): HasMany
    {
        return $this->hasMany(GamificationTransaction::class);
    }

    /** @return BelongsToMany<GamificationBadge, $this> */
    public function gamificationBadges(): BelongsToMany
    {
        return $this->belongsToMany(GamificationBadge::class, 'gamification_badge_student', 'student_id', 'badge_id');
    }

    /** @return HasMany<GamificationStudentState, $this> */
    public function gamificationStates(): HasMany
    {
        return $this->hasMany(GamificationStudentState::class);
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'national_id',
        'nationality',
        'password',
        'is_approved',
        'approved_by',
        'circle_id',
        'stage_id',
        'guardian_id',
        'access_token',
        'is_data_completed',
        'birth_date',
        'joined_at',
        'status',
        'avatar_path',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_approved' => 'boolean',
            'is_data_completed' => 'boolean',
            'birth_date' => 'date',
            'joined_at' => 'date',
        ];
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    public function getAbsencesInPeriodCount($date = null): int
    {
        $date = $date ? Carbon::parse($date) : now();
        $days = (int) Setting::getVal('calculation_period_days', 30);

        return $this->attendances()
            ->where('status', 'absent')
            ->whereBetween('date', [$date->copy()->subDays($days), $date])
            ->count();
    }

    public function getLatenessInPeriodCount($date = null): int
    {
        $date = $date ? Carbon::parse($date) : now();
        $days = (int) Setting::getVal('calculation_period_days', 30);

        return $this->attendances()
            ->where('status', 'late')
            ->whereBetween('date', [$date->copy()->subDays($days), $date])
            ->count();
    }

    public function hasExceededAbsenceLimit($date = null): bool
    {
        $limit = (int) Setting::getVal('absence_limit', 3);

        return $this->getAbsencesInPeriodCount($date) >= $limit;
    }

    public function hasExceededLatenessLimit($date = null): bool
    {
        $limit = (int) Setting::getVal('lateness_limit', 5);

        return $this->getLatenessInPeriodCount($date) >= $limit;
    }

    public function getAbsencesInLast30DaysCount($date = null): int
    {
        return $this->getAbsencesInPeriodCount($date);
    }

    public function getGuardianPhoneAttribute()
    {
        return $this->guardian?->phone;
    }

    public function getMemorizedRange(): ?array
    {
        $latestPlan = StudentPlan::where('student_id', $this->id)
            ->where('is_approved', true)
            ->latest('start_date')
            ->first();

        if (! $latestPlan) {
            return null;
        }

        $direction = $latestPlan->direction;

        $days = StudentPlanDay::whereHas('plan', fn ($q) => $q->where('student_id', $this->id)->where('is_approved', true))
            ->where(function ($q) {
                $q->where(function ($sq) {
                    $sq->where('hifz_achievement', '>=', 2)
                        ->whereNotNull('from_ayah_id')
                        ->whereNotNull('to_ayah_id');
                })->orWhere(function ($sq) {
                    $sq->where('review_achievement', '>=', 2)
                        ->whereNotNull('review_from_ayah_id')
                        ->whereNotNull('review_to_ayah_id');
                });
            })
            ->get();

        if ($days->isEmpty()) {
            return null;
        }

        $furthestAyah = $direction === 'reverse' ? 6236 : 1;

        foreach ($days as $day) {
            if ($day->hifz_achievement >= 2 && $day->from_ayah_id && $day->to_ayah_id) {
                if ($direction === 'reverse') {
                    $furthestAyah = min($furthestAyah, $day->from_ayah_id, $day->to_ayah_id);
                } else {
                    $furthestAyah = max($furthestAyah, $day->from_ayah_id, $day->to_ayah_id);
                }
            }
            if ($day->review_achievement >= 2 && $day->review_from_ayah_id && $day->review_to_ayah_id) {
                if ($direction === 'reverse') {
                    $furthestAyah = min($furthestAyah, $day->review_from_ayah_id, $day->review_to_ayah_id);
                } else {
                    $furthestAyah = max($furthestAyah, $day->review_from_ayah_id, $day->review_to_ayah_id);
                }
            }
        }

        if ($direction === 'reverse') {
            return ['min' => $furthestAyah, 'max' => 6236];
        }

        return ['min' => 1, 'max' => $furthestAyah];
    }

    public function memorizedPagesCount(): int
    {
        $range = $this->getMemorizedRange();
        if (! $range) {
            return 0;
        }

        if ($range['max'] === 6236 && $range['min'] !== 1) {
            $page = Ayah::find($range['min'])?->page_number ?? 604;

            return 604 - $page + 1;
        }

        $page = Ayah::find($range['max'])?->page_number ?? 1;

        return $page;
    }

    public function memorizationPercentage(): float
    {
        return round($this->memorizedPagesCount() / 604 * 100, 1);
    }

    public function memorizationText(): string
    {
        $range = $this->getMemorizedRange();
        if (! $range) {
            return 'لا يوجد سجل محفوظ';
        }

        if ($range['min'] === 1 && $range['max'] === 6236) {
            return 'القرآن كاملاً';
        }

        $startAyah = Ayah::with('surah')->find($range['min']);
        $endAyah = Ayah::with('surah')->find($range['max']);

        if (! $startAyah || ! $endAyah) {
            return 'لا يوجد سجل محفوظ';
        }

        if ($startAyah->surah_id === $endAyah->surah_id) {
            return 'سورة '.$startAyah->surah->name_arabic;
        }

        return 'من سورة '.$startAyah->surah->name_arabic.' إلى '.$endAyah->surah->name_arabic;
    }
}
