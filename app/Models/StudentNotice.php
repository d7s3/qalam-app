<?php

namespace App\Models;

use Database\Factories\StudentNoticeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A message written to one student by name, waiting for him when he next
 * opens the platform.
 *
 * With an unlock code it also holds his account shut until he types the code
 * he was told in person; without one he reads it and carries on. Either way it
 * stands between him and his pages until it is opened.
 */
class StudentNotice extends Model
{
    /** @use HasFactory<StudentNoticeFactory> */
    use HasFactory;

    /** Where a message says the student's name. */
    public const NAME = '{الاسم}';

    protected $fillable = ['student_id', 'body', 'unlock_code', 'sent_by_type', 'sent_by_id', 'opened_at'];

    protected function casts(): array
    {
        return ['opened_at' => 'datetime'];
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function sentBy(): MorphTo
    {
        return $this->morphTo();
    }

    /** @param  Builder<self>  $query */
    public function scopeWaiting(Builder $query): void
    {
        $query->whereNull('opened_at');
    }

    /** The oldest message a student has not opened yet, if any. */
    public static function waitingFor(int $studentId): ?self
    {
        return static::waiting()->where('student_id', $studentId)->oldest('id')->first();
    }

    public function locks(): bool
    {
        return filled($this->unlock_code);
    }

    /** The message as he reads it, with his name where it was asked for. */
    public function bodyFor(string $name): string
    {
        return str_replace(self::NAME, $name, $this->body);
    }

    /**
     * Whether a typed code is this message's.
     *
     * Read as a person types it: Arabic-Indic digits are digits, and spaces
     * and letter case do not matter.
     */
    public function acceptsCode(string $typed): bool
    {
        return $this->locks() && hash_equals(self::normalise($this->unlock_code), self::normalise($typed));
    }

    private static function normalise(string $code): string
    {
        return mb_strtolower(preg_replace('/\s+/u', '', strtr($code, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ])));
    }
}
