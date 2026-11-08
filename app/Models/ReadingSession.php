<?php

namespace App\Models;

use Database\Factories\ReadingSessionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A stretch of reading: timed, when the student read with the focus timer, or
 * a page simply written down. Every streak, figure and weekly table reads
 * these and nothing else.
 */
class ReadingSession extends Model
{
    /** @use HasFactory<ReadingSessionFactory> */
    use HasFactory;

    protected $fillable = ['student_id', 'student_book_id', 'started_at', 'ended_at', 'minutes', 'pages'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'ended_at' => 'datetime'];
    }

    /** @return BelongsTo<StudentBook, $this> */
    public function book(): BelongsTo
    {
        return $this->belongsTo(StudentBook::class, 'student_book_id');
    }

    /** @param  Builder<self>  $query */
    public function scopeFinished(Builder $query): void
    {
        $query->whereNotNull('ended_at');
    }
}
