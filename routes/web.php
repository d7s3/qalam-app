<?php

use App\Livewire\Auth\Manager\Login;
use App\Livewire\Auth\Manager\Register;
use App\Livewire\Manager\PendingApprovals;
use App\Models\Circle;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\StudentPlan;
use App\Models\Supervisor;
use App\Models\Surah;
use App\Models\Teacher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Mccarlosen\LaravelMpdf\Facades\LaravelMpdf;

Route::get('/', function () {
    $stats = [
        'students' => Student::count(),
        'teachers' => Teacher::count(),
        'circles' => Circle::count(),
    ];

    return view('welcome', compact('stats'));
})->name('home');

Route::get('/pending-approval', fn () => view('pending-approval'))
    ->middleware('auth:manager,supervisor,teacher,student,guardian')
    ->name('pending-approval');

Route::post('logout', function (Request $request) {
    $guard = request()->route('guard');

    if ($guard) {
        auth()->guard($guard)->logout();
    } else {
        $guards = ['student', 'manager', 'supervisor', 'teacher', 'guardian', 'web'];

        foreach ($guards as $guard) {
            if (auth()->guard($guard)->check()) {
                auth()->guard($guard)->logout();
            }
        }
    }

    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()->route('home');
})->name('logout');

$roles = [
    'manager' => 'مدير',
    'supervisor' => 'مشرف',
    'teacher' => 'معلم',
    'student' => 'طالب',
    'guardian' => 'ولي أمر',
];

Route::middleware('auth:manager,supervisor,teacher,student,guardian')->group(function () use ($roles) {
    Route::get('dashboard', function () use ($roles) {
        foreach (array_keys($roles) as $roleKey) {
            if (auth()->guard($roleKey)->check()) {
                return redirect()->route("{$roleKey}.dashboard");
            }
        }

        return redirect()->route('home');
    })->name('dashboard');
});

Route::middleware(['auth:manager', 'approved'])->prefix('manager')->name('manager.')->group(function () {
    Route::livewire('/pending-approvals', PendingApprovals::class)->name('pending-approvals');
    Route::view('/stages', 'manager.stages')->name('stages');
    Route::view('/circles', 'manager.circles')->name('circles');
    Route::view('/supervisors', 'manager.supervisors')->name('supervisors');
    Route::view('/teachers', 'manager.teachers')->name('teachers');
    Route::view('/students', 'manager.students')->name('students');
    Route::view('/guardians', 'manager.guardians')->name('guardians');
    Route::view('/attendance-reports', 'manager.attendance-reports')->name('attendance-reports');
    Route::view('/yearly-attendance', 'manager.yearly-attendance')->name('yearly-attendance');
    Route::view('/academic-calendar', 'manager.academic-calendar')->name('academic-calendar');
    Route::view('/quranic-achievement', 'manager.quranic-achievement-report')->name('quranic-achievement');
    Route::view('/attendance/{circleId}/{date}', 'manager.student-attendance-list')->name('attendance-list');
    Route::view('/ai-analysis', 'manager.ai-analysis')->name('ai-analysis');
    Route::view('/quran-editor', 'manager.quran-editor')->name('quran-editor');
    Route::view('/settings', 'manager.settings')->name('settings');
    Route::view('/whatsapp-settings', 'manager.whatsapp-settings')->name('whatsapp-settings');
    Route::view('/exceeded-limits', 'manager.exceeded-limits')->name('exceeded-limits');
    Route::view('/backups/{filename}', 'manager.backup-browser')->name('backup-browser');
    Route::view('/exam-levels', 'manager.exam-levels')->name('exam-levels');
    Route::view('/student-exams', 'manager.student-exams')->name('student-exams');
    Route::view('/tasks', 'manager.tasks')->name('tasks');
});

// القاسم المشترك لمسارات الضيوف (Guest Routes) لكل دور
Route::middleware('guest:manager')->prefix('manager')->name('manager.')->group(function () {
    Route::get('/login', Login::class)->name('login');
    // Route::get('/register', Register::class)->name('register');
});

Route::middleware('guest:supervisor')->prefix('supervisor')->name('supervisor.')->group(function () {
    Route::get('/login', App\Livewire\Auth\Supervisor\Login::class)->name('login');
    // Route::get('/register', App\Livewire\Auth\Supervisor\Register::class)->name('register');
});

Route::middleware('guest:teacher')->prefix('teacher')->name('teacher.')->group(function () {
    Route::get('/login', App\Livewire\Auth\Teacher\Login::class)->name('login');
    // Route::get('/register', App\Livewire\Auth\Teacher\Register::class)->name('register');
});

Route::middleware('guest:student')->prefix('student')->name('student.')->group(function () {
    Route::get('/login', App\Livewire\Auth\Student\Login::class)->name('login');
    // Route::get('/register', App\Livewire\Auth\Student\Register::class)->name('register');
});

Route::middleware('guest:guardian')->prefix('parent')->name('parent.')->group(function () {
    Route::get('/login', App\Livewire\Auth\Guardian\Login::class)->name('login');
    // Route::get('/register', App\Livewire\Auth\Guardian\Register::class)->name('register');
});

// مسارات لوحة التحكم (Dashboard Routes) لكل دور
Route::middleware(['auth:manager', 'approved'])->get('/manager/dashboard', fn () => view('manager.dashboard'))->name('manager.dashboard');
Route::middleware(['auth:supervisor', 'approved'])->prefix('supervisor')->name('supervisor.')->group(function () {
    Route::get('/dashboard', fn () => view('supervisor.dashboard'))->name('dashboard');
    Route::view('/teachers', 'supervisor.teachers')->name('teachers');
    Route::view('/odes', 'supervisor.odes')->name('odes');
    Route::view('/odes/plans', 'supervisor.ode-plans')->name('odes.plans');
    Route::view('/odes/create-plan', 'supervisor.ode-plan-creator')->name('odes.create-plan');
    Route::view('/circles', 'supervisor.circles')->name('circles');
    Route::view('/students', 'supervisor.students')->name('students');
    Route::view('/competitions', 'supervisor.competitions')->name('competitions');
    Route::get('/competitions/{competition}/gamification', fn ($competition) => view('supervisor.gamification', ['competitionId' => $competition]))->name('competitions.gamification');
    Route::view('/exceeded-limits', 'supervisor.exceeded-limits')->name('exceeded-limits');
    Route::view('/academic-calendar', 'supervisor.academic-calendar')->name('academic-calendar');
    Route::view('/yearly-attendance', 'supervisor.yearly-attendance')->name('yearly-attendance');
    Route::view('/tasks', 'supervisor.tasks')->name('tasks');
    Route::view('/whatsapp-settings', 'supervisor.whatsapp-settings')->name('whatsapp-settings');
});
Route::middleware(['auth:teacher', 'approved'])->prefix('teacher')->name('teacher.')->group(function () {
    $appShellRoute = function ($tab) {
        return function () use ($tab) {
            return view('teacher.app-shell', ['initialTab' => $tab]);
        };
    };

    Route::get('/dashboard', fn () => view('teacher.dashboard'))->name('dashboard');

    // SPA Routes (5 Tabs)
    Route::get('/attendance', $appShellRoute('attendance'))->name('attendance');
    Route::get('/students', $appShellRoute('students'))->name('students');
    Route::get('/plan-creator', $appShellRoute('plan-creator'))->name('plan-creator');
    Route::get('/tasmeeh', $appShellRoute('tasmeeh'))->name('tasmeeh');
    Route::get('/leaderboards', $appShellRoute('leaderboards'))->name('leaderboards');
    Route::get('/grade-items', $appShellRoute('grade-items'))->name('grade-items');

    // Standard Routes
    Route::view('/discipline', 'teacher.discipline')->name('discipline');
    Route::view('/quranic-discipline', 'teacher.quranic-discipline')->name('quranic-discipline');
    Route::view('/student-plans', 'teacher.student-plans')->name('student-plans');
    Route::view('/ode-plan-creator', 'teacher.ode-plan-creator')->name('ode-plan-creator');
    Route::view('/ode-plans', 'teacher.ode-plans')->name('ode-plans');
    Route::view('/exceeded-limits', 'teacher.exceeded-limits')->name('exceeded-limits');
    Route::view('/pairs', 'teacher.pairs')->name('pairs');
    Route::view('/student-exams', 'teacher.student-exams')->name('student-exams');

    Route::get('/student-recitation-log/{studentId}', function ($studentId) {
        return view('teacher.student-recitation-log', ['studentId' => $studentId]);
    })->name('student-recitation-log');

    // Grading page route will be mapped to a view wrapper soon, for now just use view
    Route::get('/leaderboards/{id}/grade', function ($id) {
        return view('teacher.leaderboards-grade', ['leaderboardId' => $id]);
    })->name('leaderboards.grade');

    Route::get('/leaderboards/{id}/report', function ($id) {
        return view('teacher.leaderboards-report', ['leaderboardId' => $id]);
    })->name('leaderboards.report');

    Route::get('/student-plans/{id}/print', function ($id) {
        $plan = StudentPlan::with([
            'student.circle',
            'days.fromAyah.surah',
            'days.toAyah.surah',
            'days.reviewFromAyah.surah',
            'days.reviewToAyah.surah',
        ])->findOrFail($id);

        if (! auth()->guard('teacher')->user()->circles->contains($plan->student->circle_id)) {
            abort(403);
        }

        return view('teacher.print-plan', compact('plan'));
    })->name('print-plan');

    Route::get('/student-plans/{id}/download-pdf', function ($id) {
        $plan = StudentPlan::with([
            'student.circle',
            'days.fromAyah.surah',
            'days.toAyah.surah',
            'days.reviewFromAyah.surah',
            'days.reviewToAyah.surah',
        ])->findOrFail($id);

        if (! auth()->guard('teacher')->user()->circles->contains($plan->student->circle_id)) {
            abort(403);
        }

        $pdf = LaravelMpdf::loadView('pdf.student-plan', compact('plan'), [], [
            'format' => 'A4',
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
            'useSubstitutions' => true,
            'useAdobeCJK' => true,
        ]);

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, 'plan_'.$plan->student->name.'.pdf', [
            'Content-Type' => 'application/pdf',
        ]);
    })->name('download-plan-pdf');
});
Route::middleware(['auth:student', 'approved'])->prefix('student')->name('student.')->group(function () {
    Route::get('/dashboard', fn () => view('student.dashboard'))->name('dashboard');
    Route::view('/plan', 'student.my-plan')->name('plan');
    Route::view('/plan/create', 'student.plan-creator')->name('plan-creator');
    Route::view('/plan/show/{id}', 'student.show-plan')->name('show-plan');
    Route::view('/attendance', 'student.attendance')->name('attendance');
    Route::get('/settings', function () {
        return view('student.settings-page');
    })->name('settings');
    Route::post('/logout', function (Request $request) {
        auth()->guard('student')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    })->name('logout');
});
Route::view('/student/complete-profile', 'student.complete-profile')->middleware(['auth:student'])->name('student.complete-profile');
Route::view('/teacher/complete-profile', 'teacher.complete-profile')->middleware(['auth:teacher'])->name('teacher.complete-profile');
Route::middleware(['auth:guardian', 'approved'])->prefix('parent')->name('guardian.')->group(function () {
    Route::get('/dashboard', fn () => view('guardian.dashboard'))->name('dashboard');
    Route::get('/student/{id}', fn ($id) => view('guardian.student', ['studentId' => $id]))->name('student');
    Route::get('/student/{id}/attendance', fn ($id) => view('guardian.student-attendance', ['studentId' => $id]))->name('student.attendance');
    Route::get('/challenges', fn () => view('guardian.challenges'))->name('challenges');
    Route::get('/student/{id}/challenge/create', fn ($id) => view('guardian.create-challenge', ['studentId' => $id]))->name('student.challenge.create');
});

// Magic Link Routes
Route::get('/magic/{token}', function ($token) {
    $student = Student::where('access_token', $token)->firstOrFail();

    auth()->guard('student')->login($student);

    return redirect()->route('student.dashboard');
})->name('magic-link');

Route::get('/teacher-magic/{token}', function ($token) {
    $teacher = Teacher::where('access_token', $token)->firstOrFail();

    auth()->guard('teacher')->login($teacher);

    if (! $teacher->is_data_completed) {
        return redirect()->route('teacher.complete-profile');
    }

    if (request()->has('redirect')) {
        return redirect()->to(request()->query('redirect'));
    }

    return redirect()->route('teacher.dashboard');
})->name('teacher.magic-link');

Route::get('/supervisor-magic/{token}', function ($token) {
    $supervisor = Supervisor::where('access_token', $token)->firstOrFail();

    auth()->guard('supervisor')->login($supervisor);

    return redirect()->route('supervisor.dashboard');
})->name('supervisor.magic-link');

Route::get('/guardian-magic/{token}', function ($token) {
    $guardian = Guardian::where('access_token', $token)->firstOrFail();

    auth()->guard('guardian')->login($guardian);

    // If you add a complete profile step for guardians later, handle it here.
    return redirect()->route('guardian.dashboard');
})->name('guardian.magic-link');

Route::get('/magic/{token}/login-as', function ($token) {
    if (! auth()->guard('teacher')->check()) {
        abort(403);
    }

    $student = Student::where('access_token', $token)->firstOrFail();
    auth()->guard('student')->login($student);

    return redirect()->route('student.dashboard');
})->name('magic-link.login-as');

Route::get('/quran-json', function () {
    return response()->json(Surah::with('ayahs')->get(), 200, [], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
});

Route::get('/test', function () {})->name('test');
require __DIR__.'/settings.php';
