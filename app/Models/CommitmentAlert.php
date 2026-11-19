<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A student who fell below the line in a week, and what his teacher did.
 *
 * Asked for by Faisal, 17 Nov 2026: below 70% of his Quran plan or his self
 * programme in a week, his teacher is told and asked to act; two days without
 * a word from the teacher and it goes to the supervisor.
 */
class CommitmentAlert extends Model
{
    use HasFactory;

    /** What a teacher can say he did, as the buttons say it. */
    public const ACTIONS = [
        'guardian' => 'تواصلت مع ولي الأمر',
        'student' => 'جلست مع الطالب',
        'makeup' => 'حددت له جلسة تعويض',
        'excused' => 'عذر مقبول',
        'other' => 'إجراء آخر',
    ];

    /** Days a teacher has before the supervisor is told. */
    public const DAYS_TO_ACT = 2;

    protected $fillable = [
        'student_id', 'circle_id', 'week_starts_on', 'week_ends_on',
        'plan_percent', 'self_percent', 'notified_at',
        'action', 'note', 'acted_at', 'acted_by', 'escalated_at',
    ];

    protected function casts(): array
    {
        return [
            'week_starts_on' => 'date',
            'week_ends_on' => 'date',
            'plan_percent' => 'float',
            'self_percent' => 'float',
            'notified_at' => 'datetime',
            'acted_at' => 'datetime',
            'escalated_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function circle(): BelongsTo
    {
        return $this->belongsTo(Circle::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acted_by');
    }

    /** @param  Builder<self>  $query */
    public function scopeAwaiting(Builder $query): Builder
    {
        return $query->whereNull('acted_at');
    }

    public function actionLabel(): ?string
    {
        return self::ACTIONS[$this->action] ?? null;
    }
}
