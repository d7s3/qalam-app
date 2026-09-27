<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class FormResponse extends Model
{
    use HasFactory;

    protected $fillable = [
        'form_id',
        'answers',
        'student_id',
        'is_processed',
        // An applicant has no user row, so his answers carry the details the
        // academy reaches him by until he has an account of his own.
        'respondent_name',
        'respondent_phone',
        'respondent_email',
        'score',
        'manual_scores',
        'graded_at',
        'graded_by_id',
        'graded_by_type',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'answers' => 'array',
            'is_processed' => 'boolean',
            'score' => 'array',
            'manual_scores' => 'array',
            'graded_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Form, $this> */
    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return MorphTo<Model, $this> */
    public function gradedBy(): MorphTo
    {
        return $this->morphTo('graded_by');
    }
}
