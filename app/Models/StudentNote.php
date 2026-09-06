<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One educator's note about one student on one day.
 *
 * Private unless its author says otherwise. See the migration for why that is
 * the default rather than a setting somebody remembered to turn on.
 */
class StudentNote extends Model
{
    public const PRIVATE = 'private';

    public const SHARED = 'shared';

    protected $fillable = [
        'student_id',
        'author_id',
        'author_role',
        'body',
        'noted_on',
        'visibility',
    ];

    protected $casts = ['noted_on' => 'date'];

    public function isShared(): bool
    {
        return $this->visibility === self::SHARED;
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /** @return HasMany<StudentNoteShare, $this> */
    public function shares(): HasMany
    {
        return $this->hasMany(StudentNoteShare::class);
    }
}
