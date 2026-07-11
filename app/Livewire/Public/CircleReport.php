<?php

namespace App\Livewire\Public;

use App\Models\Circle;
use App\Services\CircleReportService;
use Illuminate\Http\Request;
use Livewire\Component;

class CircleReport extends Component
{
    public int $circleId;

    public string $scope = 'circle';

    public string $studentId = '';

    public string $fromDate = '';

    public string $toDate = '';

    public function mount(Request $request): void
    {
        // The route is protected by the `signed` middleware; here we just read
        // the frozen report parameters out of the shared link.
        $this->circleId = (int) $request->query('circle');
        $this->scope = $request->query('scope') === 'stage' ? 'stage' : 'circle';
        $this->studentId = (string) $request->query('student', '');
        $this->fromDate = (string) $request->query('from', '');
        $this->toDate = (string) $request->query('to', '');

        Circle::findOrFail($this->circleId);
    }

    public function render()
    {
        $circle = Circle::with('stage')->findOrFail($this->circleId);

        [$from, $to] = CircleReportService::resolveRange('custom', $this->fromDate, $this->toDate);

        $students = $this->scope === 'stage'
            ? CircleReportService::studentsForStage($circle->stage)
            : CircleReportService::studentsForCircle($circle);

        $selectedStudent = $this->studentId !== ''
            ? $students->firstWhere('id', (int) $this->studentId)
            : null;

        $reportStudents = $selectedStudent
            ? $students->filter(fn ($s) => $s->id === $selectedStudent->id)->values()
            : $students;

        $report = CircleReportService::build($reportStudents, $from, $to);

        return view('livewire.public.circle-report', [
            'circle' => $circle,
            'selectedStudent' => $selectedStudent,
            'report' => $report,
            'from' => $from,
            'to' => $to,
        ])->layout('layouts.blank');
    }
}
