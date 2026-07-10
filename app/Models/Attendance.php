<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'student_id',
        'teacher_id',
        'circle_id',
        'date',
        'status',
        'notes',
        'duration_minutes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<Teacher, $this> */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    /** @return BelongsTo<Circle, $this> */
    public function circle(): BelongsTo
    {
        return $this->belongsTo(Circle::class);
    }

    /**
     * Raw SQL predicate that excludes attendance records taken while the student
     * was "registering" per their status history on the record's date. Students
     * with no history rows at or before the date are treated as active, matching
     * the fallback used when reading a student's status on a given date.
     */
    public static function registeringExclusionSql(string $table = 'attendances'): string
    {
        return "coalesce((
            select h.status
            from student_status_histories h
            where h.student_id = {$table}.student_id
              and date(h.start_date) <= date({$table}.date)
            order by h.start_date desc, h.id desc
            limit 1
        ), 'active') != 'registering'";
    }
}
