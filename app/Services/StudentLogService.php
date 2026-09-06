<?php

namespace App\Services;

use App\Models\Student;
use App\Models\StudentNote;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * The record of upbringing, and who may read it.
 *
 * One rule, asked in one place: a note is the author's, and reaches anyone else
 * only because he said so — by opening that note, or by opening what he writes
 * to an office. Seniority does not open it. A teacher writes honestly only if
 * he is the one who decides who reads it, and a supervisor who can read
 * everything by virtue of his office reads a diary rather than a record.
 *
 * The super administrator is the exception, as he is everywhere else.
 */
class StudentLogService
{
    /**
     * The notes on a student that this reader may see.
     *
     * @return Collection<int, StudentNote>
     */
    public static function visibleTo(Student $student, User $reader, string $readerRole): Collection
    {
        return StudentNote::with(['author', 'shares'])
            ->where('student_id', $student->id)
            ->orderByDesc('noted_on')
            ->orderByDesc('id')
            ->get()
            ->filter(fn (StudentNote $note) => self::mayRead($note, $reader, $readerRole))
            ->values();
    }

    /** Whether one note is open to one reader. */
    public static function mayRead(StudentNote $note, User $reader, string $readerRole): bool
    {
        if ($reader->is_super_admin) {
            return true;
        }

        if ($note->author_id === $reader->id) {
            return true;
        }

        if ($note->isShared()) {
            return true;
        }

        return $note->shares->contains(
            fn ($share) => ($share->role_key !== null && $share->role_key === $readerRole)
                || ($share->user_id !== null && $share->user_id === $reader->id)
        );
    }

    public static function write(
        Student $student,
        User $author,
        string $authorRole,
        string $body,
        ?string $notedOn = null,
        string $visibility = StudentNote::PRIVATE,
    ): StudentNote {
        return StudentNote::create([
            'student_id' => $student->id,
            'author_id' => $author->id,
            'author_role' => $authorRole,
            'body' => $body,
            'noted_on' => $notedOn ?: now('Asia/Riyadh')->format('Y-m-d'),
            'visibility' => $visibility === StudentNote::SHARED ? StudentNote::SHARED : StudentNote::PRIVATE,
        ]);
    }

    /**
     * Open one note to one office.
     *
     * Only its author may, and that is the whole point of the thing.
     */
    public static function openTo(StudentNote $note, User $author, string $roleKey): bool
    {
        if ($note->author_id !== $author->id) {
            return false;
        }

        $note->shares()->firstOrCreate(['role_key' => $roleKey]);
        $note->unsetRelation('shares');

        return true;
    }

    public static function closeTo(StudentNote $note, User $author, string $roleKey): bool
    {
        if ($note->author_id !== $author->id) {
            return false;
        }

        $note->shares()->where('role_key', $roleKey)->delete();
        $note->unsetRelation('shares');

        return true;
    }

    /** Open, or close, everything this author writes about anybody. */
    public static function setVisibility(StudentNote $note, User $author, string $visibility): bool
    {
        if ($note->author_id !== $author->id) {
            return false;
        }

        $note->update([
            'visibility' => $visibility === StudentNote::SHARED ? StudentNote::SHARED : StudentNote::PRIVATE,
        ]);

        return true;
    }
}
