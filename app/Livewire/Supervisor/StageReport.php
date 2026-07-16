<?php

namespace App\Livewire\Supervisor;

use App\Models\Circle;
use App\Models\Stage;
use App\Services\CircleReportService;
use Illuminate\Support\Facades\URL;
use Livewire\Attributes\Url as UrlParam;
use Livewire\Component;

class StageReport extends Component
{
    public Stage $stage;

    #[UrlParam]
    public string $preset = 'this_week';

    #[UrlParam]
    public string $fromDate = '';

    #[UrlParam]
    public string $toDate = '';

    #[UrlParam]
    public string $circleId = '';

    #[UrlParam]
    public string $studentId = '';

    public function mount($stageId): void
    {
        $supervisor = auth()->guard('supervisor')->user();

        $this->stage = $supervisor->stages()->findOrFail($stageId);

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

    public function updatedCircleId(): void
    {
        $this->studentId = '';
    }

    public function render()
    {
        [$from, $to] = CircleReportService::resolveRange($this->preset, $this->fromDate, $this->toDate);

        $circles = Circle::where('stage_id', $this->stage->id)->orderBy('name')->get();

        $selectedCircle = null;
        if ($this->circleId !== '') {
            $selectedCircle = $circles->firstWhere('id', (int) $this->circleId);
            if (! $selectedCircle) {
                $this->circleId = '';
            }
        }

        $students = $selectedCircle
            ? CircleReportService::studentsForCircle($selectedCircle)
            : CircleReportService::studentsForStage($this->stage);

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
            'stage' => $selectedCircle ? null : $this->stage->id,
            'circle' => $selectedCircle?->id,
            'scope' => $selectedCircle ? 'circle' : null,
            'student' => $this->studentId ?: null,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
        ]));

        return view('livewire.supervisor.stage-report', [
            'circles' => $circles,
            'selectedCircle' => $selectedCircle,
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
