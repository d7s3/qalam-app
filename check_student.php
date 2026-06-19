<?php

use App\Models\Student;
use App\Models\StudentPlanDay;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$student = Student::where('name', 'like', '%عبيد علي عاطف%')->first();
if ($student) {
    echo 'Student ID: '.$student->id."\n";
    $days = StudentPlanDay::whereHas('plan', function ($q) use ($student) {
        $q->where('student_id', $student->id);
    })->orderBy('date', 'desc')->take(10)->get();

    foreach ($days as $day) {
        echo 'ID: '.$day->id.' | Date: '.$day->date.' | Hifz: '.$day->hifz_achievement.' | HifzGradedAt: '.$day->hifz_graded_at."\n";
    }
} else {
    echo "Student not found.\n";
}
