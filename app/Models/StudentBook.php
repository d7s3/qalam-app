<?php

namespace App\Models;

use Database\Factories\StudentBookFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A book on a student's own shelves, and how far into it he is.
 */
class StudentBook extends Model
{
    /** @use HasFactory<StudentBookFactory> */
    use HasFactory;

    public const READING = 'reading';

    public const READ = 'read';

    public const WANT = 'want';

    public const DROPPED = 'dropped';

    /** The shelves, in the order the page shows them. */
    public const SHELVES = [
        self::READING => 'أقرؤه الآن',
        self::WANT => 'أريد قراءته',
        self::READ => 'قرأته',
        self::DROPPED => 'لم أكمله',
    ];

    protected $fillable = [
        'student_id', 'title', 'author', 'total_pages', 'current_page', 'shelf',
        'rating', 'review', 'started_on', 'finished_on',
    ];

    protected function casts(): array
    {
        return ['started_on' => 'date', 'finished_on' => 'date'];
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    /** @return HasMany<BookQuote, $this> */
    public function quotes(): HasMany
    {
        return $this->hasMany(BookQuote::class)->latest('id');
    }

    /** @return HasMany<ReadingSession, $this> */
    public function sessions(): HasMany
    {
        return $this->hasMany(ReadingSession::class);
    }

    /** How far through the book he is, out of a hundred; null when its length is not known. */
    public function percent(): ?int
    {
        return $this->total_pages ? (int) min(100, round($this->current_page / $this->total_pages * 100)) : null;
    }
}
