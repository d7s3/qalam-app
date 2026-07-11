<?php

namespace App\Livewire\Supervisor;

use App\Models\Circle;
use App\Services\CircleReportService;
use Illuminate\Support\Facades\URL;
use Livewire\Attributes\Url as UrlParam;
use Livewire\Component;

class CircleReport extends Component
{
    public Circle $circle;

    #[UrlParam]
    public string $scope = 'circle';

    #[UrlParam]
    public string $preset = 'this_week';

    #[UrlParam]
    public string $fromDate = '';

    #[UrlParam]
    public string $toDate = '';

    #[UrlParam]
    public string $studentId = '';

    public function mount($circleId): void
    {
        $supervisor = auth()->guard('supervisor')->user();

        $this->circle = Circle::with('stage')
            ->whereIn('stage_id', $supervisor->stages()->pluck('stages.id'))
            ->findOrFail($circleId);

        if (! in_array($this->scope, ['circle', 'stage'], true)) {
            $this->scope = 'circle';
        }
        if (! array_key_exists($this->preset, CircleReportService::PRESETS)) {
            $this->preset = 'this_week';
        }
    }

    public function updatedPreset(): void
    {
        if ($this->preset === 'custom') {
            [$from, $to] = CircleReportService::resolveRange('last_week');
            $this->fromDate = $this->fromDate ?: $from->toDateString();
            $this->toDate = $this->toDate ?: $to->toDateString();
        } else {
            $this->fromDate = '';
            $this->toDate = '';
        }
    }

    public function updatedScope(): void
    {
        $this->studentId = '';
    }

    public function render()
    {
        [$from, $to] = CircleReportService::resolveRange($this->preset, $this->fromDate, $this->toDate);

        $students = $this->scope === 'stage'
            ? CircleReportService::studentsForStage($this->circle->stage)
            : CircleReportService::studentsForCircle($this->circle);

        $selectedStudent = null;
        if ($this->studentId !== '') {
            $selectedStudent = $students->firstWhere('id', (int) $this->studentId);
            if (! $selectedStudent) {
                $this->studentId = '';
            }
        }

        $reportStudents = $selectedStudent
            ? $students->filter(fn ($s) => $s->id === $selectedStudent->id)->values()
            : $students;

        $report = CircleReportService::build($reportStudents, $from, $to);

        $shareUrl = URL::signedRoute('reports.circle', array_filter([
            'circle' => $this->circle->id,
            'scope' => $this->scope,
            'student' => $this->studentId ?: null,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
        ]));

        return view('livewire.supervisor.circle-report', [
            'students' => $students,
            'selectedStudent' => $selectedStudent,
            'report' => $report,
            'from' => $from,
            'to' => $to,
            'shareUrl' => $shareUrl,
            'presets' => CircleReportService::PRESETS,
        ]);
    }
}
