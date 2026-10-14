<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserRole extends Model
{
    /** The reach follows the role, as it did before any of this. */
    public const SCOPE_ROLE = null;

    /** The whole academy, whatever the role would have given. */
    public const SCOPE_ALL = 'all';

    /** Named programmes only. */
    public const SCOPE_STAGES = 'stages';

    /** Named cohorts only. */
    public const SCOPE_CIRCLES = 'circles';

    protected $fillable = [
        'user_id',
        'role',
        'is_approved',
        'approved_by',
        'is_rejected',
        'rejected_at',
        'rejected_by',
        'is_data_completed',
        'scope_type',
        'scope_ids',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_approved' => 'boolean',
            'is_rejected' => 'boolean',
            'rejected_at' => 'datetime',
            'is_data_completed' => 'boolean',
            'scope_ids' => 'array',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** @return BelongsTo<User, $this> */
    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    /**
     * How many registrations wait for the manager's answer: students,
     * teachers, supervisors and guardians neither approved nor turned down.
     *
     * Asked of the roles because that is where approval lives; asking the
     * users table for it found nobody, so the count read nought.
     */
    public static function awaitingApprovalCount(): int
    {
        return static::query()
            ->whereIn('role', ['student', 'teacher', 'supervisor', 'guardian'])
            ->where('is_approved', false)
            ->where(fn ($query) => $query->whereNull('is_rejected')->orWhere('is_rejected', false))
            ->count();
    }
}
