<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A passage a student kept from one of his books. */
class BookQuote extends Model
{
    protected $fillable = ['student_book_id', 'body', 'page'];

    /** @return BelongsTo<StudentBook, $this> */
    public function book(): BelongsTo
    {
        return $this->belongsTo(StudentBook::class, 'student_book_id');
    }
}
