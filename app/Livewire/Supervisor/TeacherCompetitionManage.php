<?php

namespace App\Livewire\Supervisor;

use App\Models\Circle;
use App\Models\Teacher;
use App\Models\TeacherCompetition;
use App\Models\TeacherCompetitionCriterion;
use App\Models\TeacherCompetitionScore;
use App\Services\TeacherCompetitionService;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;

class TeacherCompetitionManage extends Component
{
    public TeacherCompetition $competition;

    public string $activeTab = 'participants';

    public $teachersList = [];

    public array $selectedParticipants = [];

    public array $criteria = [];

    /** @var array<int, array<int, int|null>> [teacherId][criterionId] => score */
    public array $scores = [];

    public function mount($competitionId): void
    {
        $this->competition = TeacherCompetition::where('supervisor_id', $this->supervisorId())->findOrFail($competitionId);

        $this->loadParticipantsData();
        $this->loadCriteria();
        $this->loadScores();
    }

    private function supervisorId(): int
    {
        return auth()->guard('supervisor')->id();
    }

    private function getSupervisorCircleIds(): array
    {
        $supervisor = auth()->guard('supervisor')->user();

        return Circle::whereIn('stage_id', $supervisor->stages()->pluck('stages.id'))->pluck('id')->toArray();
    }

    public function setActiveTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    // ──────── Participants ────────

    private function loadParticipantsData(): void
    {
        $circleIds = $this->getSupervisorCircleIds();

        $this->teachersList = Teacher::whereHas('circles', function ($q) use ($circleIds) {
            $q->whereIn('circle_id', $circleIds);
        })->orderBy('name')->get();

        $this->selectedParticipants = $this->competition->participants()->pluck('users.id')->toArray();
    }

    public function saveParticipants(): void
    {
        $validTeacherIds = $this->teachersList->pluck('id')->toArray();
        $kept = array_values(array_intersect($this->selectedParticipants, $validTeacherIds));

        $this->competition->participants()->sync($kept);

        $this->loadParticipantsData();
        $this->loadScores();
        Flux::toast(__('تم تحديث قائمة المعلمين المشاركين'), variant: 'success');
    }

    // ──────── Criteria ────────

    private function loadCriteria(): void
    {
        $this->criteria = $this->competition->criteria()
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'max_points' => $c->max_points])
            ->toArray();
    }

    public function addCriterion(): void
    {
        $this->criteria[] = ['id' => null, 'name' => '', 'max_points' => 10];
    }

    public function removeCriterion(int $index): void
    {
        unset($this->criteria[$index]);
        $this->criteria = array_values($this->criteria);
    }

    public function saveCriteria(): void
    {
        if ($this->competition->criteriaAreLocked()) {
            Flux::toast(__('لا يمكن تعديل بنود التقييم بعد بدء تسجيل الدرجات'), variant: 'danger');

            return;
        }

        $this->validate([
            'criteria.*.name' => 'required|string|max:255',
            'criteria.*.max_points' => 'required|integer|min:1|max:1000',
        ]);

        DB::transaction(function () {
            $keptIds = collect($this->criteria)->pluck('id')->filter()->toArray();
            $this->competition->criteria()->whereNotIn('id', $keptIds)->delete();

            foreach ($this->criteria as $index => $criterion) {
                TeacherCompetitionCriterion::updateOrCreate(
                    ['id' => $criterion['id'], 'teacher_competition_id' => $this->competition->id],
                    [
                        'name' => $criterion['name'],
                        'max_points' => $criterion['max_points'],
                        'sort_order' => $index,
                    ]
                );
            }
        });

        $this->loadCriteria();
        $this->loadScores();
        Flux::toast(__('تم حفظ بنود التقييم بنجاح'), variant: 'success');
    }

    // ──────── Scoring ────────

    private function loadScores(): void
    {
        $criteriaIds = $this->competition->criteria()->pluck('id');
        $existing = TeacherCompetitionScore::where('teacher_competition_id', $this->competition->id)->get();

        $this->scores = [];
        foreach ($this->selectedParticipants as $teacherId) {
            foreach ($criteriaIds as $criterionId) {
                $this->scores[$teacherId][$criterionId] = $existing
                    ->firstWhere(fn ($s) => $s->teacher_id === $teacherId && $s->criterion_id === $criterionId)
                    ?->score;
            }
        }
    }

    public function saveScores(): void
    {
        $criteria = $this->competition->criteria()->get()->keyBy('id');

        DB::transaction(function () use ($criteria) {
            foreach ($this->scores as $teacherId => $criterionScores) {
                if (! in_array((int) $teacherId, $this->selectedParticipants, true)) {
                    continue;
                }

                foreach ($criterionScores as $criterionId => $value) {
                    $criterion = $criteria->get($criterionId);
                    if (! $criterion) {
                        continue;
                    }

                    $value = $value === '' || $value === null ? null : (int) min(max((int) $value, 0), $criterion->max_points);

                    TeacherCompetitionScore::updateOrCreate(
                        [
                            'teacher_competition_id' => $this->competition->id,
                            'teacher_id' => $teacherId,
                            'criterion_id' => $criterionId,
                        ],
                        [
                            'score' => $value,
                            'scored_by' => $this->supervisorId(),
                        ]
                    );
                }
            }
        });

        $this->loadScores();
        Flux::toast(__('تم حفظ التقييمات بنجاح'), variant: 'success');
    }

    // ──────── Standings ────────

    #[Computed]
    public function standings()
    {
        return (new TeacherCompetitionService)->getStandings($this->competition->fresh(['participants', 'criteria']));
    }

    public function render()
    {
        return view('livewire.supervisor.teacher-competition-manage')->layout('layouts.role-shell');
    }
}
