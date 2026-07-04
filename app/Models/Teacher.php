<?php

namespace App\Models;

use App\Models\Concerns\HasProfile;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;

class Teacher extends Authenticatable
{
    use HasApiTokens, HasFactory, HasProfile, Notifiable, TwoFactorAuthenticatable;

    /** @return BelongsToMany<Circle, $this> */
    public function circles(): BelongsToMany
    {
        return $this->belongsToMany(Circle::class, 'circle_teacher', 'teacher_id', 'circle_id');
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

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'is_approved',
        'approved_by',
        'access_token',
        'is_data_completed',
        'permissions',
    ];

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
            'permissions' => 'array',
        ];
    }

    /**
     * The canonical set of teacher permission keys with their default (enabled) state.
     * New keys added here are enabled by default, even for teachers whose stored
     * override predates the key, because effectivePermissions() merges over these.
     *
     * @return array<string, bool>
     */
    public static function defaultPermissions(): array
    {
        return [
            'can_manage_students' => true,
            'can_change_student_status' => true,
            'can_create_students' => true,
            'can_manage_hadith_paths' => true,
            'can_manage_ode_paths' => true,
            'can_manage_gamification_tracks' => true,
        ];
    }

    /**
     * Returns the effective permissions for this teacher.
     *
     * Layered so that any key missing from an older stored override falls back to
     * the global default, and any key missing from both falls back to the canonical
     * default. This keeps newly-introduced permissions "enabled by default" without
     * having to backfill every teacher row.
     *
     * @return array<string, bool>
     */
    public function effectivePermissions(): array
    {
        $defaults = self::defaultPermissions();

        $global = Setting::getVal('default_teacher_permissions');
        if (is_string($global)) {
            $global = json_decode($global, true);
        }
        $base = is_array($global) ? array_merge($defaults, $global) : $defaults;

        if ($this->permissions !== null) {
            return array_merge($base, $this->permissions);
        }

        return $base;
    }

    /**
     * Whether this teacher has a custom permission override (vs inheriting global defaults).
     */
    public function hasOverriddenPermissions(): bool
    {
        return $this->permissions !== null;
    }
}
