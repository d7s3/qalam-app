<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentStatusHistory extends Model
{
    protected $fillable = [
        'student_id',
        'status',
        'start_date',
        'end_date',
        'notes',
        'changed_by_role',
        'changed_by_name',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}
