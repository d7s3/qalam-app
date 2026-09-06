<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One note opened to one office, or to one person, by the one who wrote it. */
class StudentNoteShare extends Model
{
    protected $fillable = ['student_note_id', 'role_key', 'user_id'];

    /** @return BelongsTo<StudentNote, $this> */
    public function note(): BelongsTo
    {
        return $this->belongsTo(StudentNote::class, 'student_note_id');
    }
}
