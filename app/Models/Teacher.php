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
     * Returns the effective permissions for this teacher.
     * If the teacher has their own overridden permissions, use those.
     * Otherwise fall back to the global default teacher permissions from settings.
     *
     * @return array{can_manage_students: bool, can_change_student_status: bool}
     */
    public function effectivePermissions(): array
    {
        if ($this->permissions !== null) {
            return $this->permissions;
        }

        $global = Setting::getVal('default_teacher_permissions');

        if (is_string($global)) {
            $global = json_decode($global, true);
        }

        return $global ?? [
            'can_manage_students' => true,
            'can_change_student_status' => true,
            'can_create_students' => true,
        ];
    }

    /**
     * Whether this teacher has a custom permission override (vs inheriting global defaults).
     */
    public function hasOverriddenPermissions(): bool
    {
        return $this->permissions !== null;
    }
}
