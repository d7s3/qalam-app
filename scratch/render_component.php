<?php

use App\Models\Student;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Livewire\Livewire;

// Boot Laravel
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';

// Bootstrap the application kernel
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$request = Request::capture();
$app->instance('request', $request);

// Let's authenticate student عبدالوهاب فوزي
$student = Student::where('name', 'like', '%عبدالوهاب%')->first();
Auth::guard('student')->login($student);

// Render the Livewire component directly using Livewire engine
$html = Livewire::mount('student.dashboard');

file_put_contents('/Users/aaa/.gemini/antigravity-ide/scratch/rendered.html', $html);

echo 'Rendered HTML written to scratch/rendered.html. Length: '.strlen($html)."\n";
