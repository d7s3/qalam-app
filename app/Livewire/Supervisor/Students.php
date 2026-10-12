<?php

namespace App\Livewire\Supervisor;

use App\Concerns\CopiesStudentMagicLinks;
use App\Models\Circle;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\StudentPlacementRequest;
use App\Services\StudentPlacementService;
use App\Services\StudentStatusService;
use App\Support\HijriDate;
use App\Support\Scope;
use App\Support\StudentStatus;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class Students extends Component
{
    use CopiesStudentMagicLinks, WithPagination;

    public $circles;

    public string $name = '';

    public string $email = '';

    public $circle_id = null;

    #[Locked]
    public $editingStudentId = null;

    public string $search = '';

    public string $statusFilter = 'all';

    public string $circleFilter = '';

    public $guardiansList = [];

    public $guardian_id = null;

    public string $editJoinedAt = '';

    public $viewingStudent = null;

    public array $stats = [];

    public string $deleteConfirmationInput = '';

    public $bulkCircleId = null;

    public string $bulkJoinedAt = '';

    public string $bulkStatus = 'active';

    public string $bulkStatusDate = '';

    /** The group chosen on each «ضمّه» row, when the sheikh holds more than one. */
    public array $groupFor = [];

    public function mount(): void
    {
        $this->circles = Circle::with('stage')->whereIn('id', $this->getSupervisorCircleIds())->get();
        $this->guardiansList = Guardian::whereRoleState(fn ($q) => $q->where('is_approved', true))->get();
    }

    private function getSupervisorStageIds(): array
    {
        return Scope::forRole('supervisor')->reachableStageIds()->all();
    }

    private function getSupervisorCircleIds(): array
    {
        return Scope::forRole('supervisor')->reachableCircleIds()->all();
    }

    /**
     * Constrain a student query to the supervisor's scope: students in their
     * circles, plus circle-less students assigned directly to one of their stages.
     *
     * @template TQuery of \Illuminate\Database\Eloquent\Builder
     *
     * @param  TQuery  $query
     * @return TQuery
     */
    private function scopeToSupervisor($query)
    {
        $circleIds = $this->getSupervisorCircleIds();
        $stageIds = $this->getSupervisorStageIds();

        return $query->where(function ($q) use ($circleIds, $stageIds) {
            $q->whereIn('circle_id', $circleIds)
                ->orWhere(function ($sub) use ($stageIds) {
                    $sub->whereNull('circle_id')->whereIn('stage_id', $stageIds);
                });
        });
    }

    protected function selectableStudentsQuery()
    {
        return $this->scopeToSupervisor(Student::query());
    }

    protected function filteredStudentsQuery()
    {
        $query = $this->scopeToSupervisor(Student::with(['circle.stage', 'stage', 'guardian']));

        if ($this->search) {
            // An Arabic keyboard types ٢٢ where the record holds 22.
            $term = '%'.HijriDate::digits($this->search).'%';

            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('phone', 'like', $term);
            });
        }

        if ($this->statusFilter === 'pending') {
            $query->whereRoleState(fn ($q) => $q->where('is_approved', false));
        } elseif ($this->statusFilter === 'approved') {
            $query->whereRoleState(fn ($q) => $q->where('is_approved', true));
        }

        if ($this->circleFilter) {
            $query->where('circle_id', $this->circleFilter);
        }

        return $query->latest();
    }

    public function updatedSearch(): void
    {
        $this->resetSelectAllToggle();
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetSelectAllToggle();
        $this->resetPage();
    }

    public function updatedCircleFilter(): void
    {
        $this->resetSelectAllToggle();
        $this->resetPage();
    }

    public function resetSelection(): void
    {
        $this->resetStudentSelection();
        $this->deleteConfirmationInput = '';
    }

    public function applyBulkCircle(): void
    {
        $this->validate([
            'bulkCircleId' => 'nullable|exists:circles,id',
        ]);

        $circleIds = $this->getSupervisorCircleIds();

        if ($this->bulkCircleId && ! in_array((int) $this->bulkCircleId, $circleIds)) {
            Flux::toast(__('الدفعة المختارة خارج نطاق صلاحياتك'), variant: 'danger');

            return;
        }

        $count = $this->selectedStudentsQuery()->update([
            'circle_id' => $this->bulkCircleId ?: null,
        ]);

        $this->resetSelection();
        $this->bulkCircleId = null;

        Flux::modal('bulk-circle-modal')->close();
        Flux::toast(__('تم تغيير دفعة '.$count.' طلاب بنجاح'), variant: 'success');
    }

    public function applyBulkJoinedAt(): void
    {
        $this->validate([
            'bulkJoinedAt' => 'required|date',
        ]);

        $count = $this->selectedStudentsQuery()->update([
            'joined_at' => $this->bulkJoinedAt,
        ]);

        $this->resetSelection();
        $this->bulkJoinedAt = '';

        Flux::modal('bulk-joined-at-modal')->close();
        Flux::toast(__('تم تغيير تاريخ التحاق '.$count.' طلاب بنجاح'), variant: 'success');
    }

    public function applyBulkStatus(): void
    {
        $this->validate([
            'bulkStatus' => ['required', Rule::enum(StudentStatus::class)],
            // Riyadh, not app.timezone: see the note in the student status manager.
            'bulkStatusDate' => 'nullable|date|before_or_equal:'.now('Asia/Riyadh')->format('Y-m-d'),
        ], [
            'bulkStatusDate.before_or_equal' => __('تاريخ سريان الحالة لا يمكن أن يكون في المستقبل'),
        ]);

        $students = $this->selectedStudentsQuery()->get();
        $count = 0;
        $skipped = 0;
        foreach ($students as $student) {
            try {
                StudentStatusService::changeStatus($student, $this->bulkStatus, $this->bulkStatusDate ?: null);
                $count++;
            } catch (\InvalidArgumentException $e) {
                $skipped++;
            }
        }

        if ($skipped > 0) {
            Flux::toast(__('تم تخطي '.$skipped.' طلاب لأن التاريخ يسبق سجلات حالاتهم الحالية'), variant: 'warning');
        }

        $this->resetSelection();
        $this->bulkStatus = 'active';
        $this->bulkStatusDate = '';

        Flux::modal('bulk-status-modal')->close();
        Flux::toast(__('تم تغيير حالة '.$count.' طلاب بنجاح'), variant: 'success');
    }

    public function applyBulkResetMagicLinks(): void
    {
        $students = $this->selectedStudentsQuery()->get();
        $count = $students->count();

        foreach ($students as $student) {
            $student->update([
                'access_token' => Str::random(32),
            ]);
        }

        $this->resetSelection();

        Flux::toast(__('تم تحديث الروابط السحرية لـ '.$count.' طلاب بنجاح'), variant: 'success');
    }

    public function confirmBulkDelete(): void
    {
        if ($this->deleteConfirmationInput !== 'تأكيد الحذف') {
            Flux::toast(__('يرجى إدخال نص التأكيد بشكل صحيح'), variant: 'danger');

            return;
        }

        $students = $this->selectedStudentsQuery()->get();
        $count = $students->count();

        foreach ($students as $student) {
            $student->delete();
        }

        $this->resetSelection();

        Flux::modal('bulk-delete-modal')->close();
        Flux::toast(__('تم حذف '.$count.' طلاب بنجاح'), variant: 'success');
    }

    public function approve($id): void
    {
        $student = $this->scopeToSupervisor(Student::query())->find($id);

        if (! $student) {
            Flux::toast(__('الطالب غير موجود أو ليس ضمن صلاحياتك'), variant: 'danger');

            return;
        }

        $student->update([
            'is_approved' => 1,
            'approved_by' => auth('manager')->id(),
        ]);

        Flux::toast(__('تمت الموافقة على الطالب بنجاح'), variant: 'success');
    }

    public function edit($id): void
    {
        $this->viewingStudent = $this->scopeToSupervisor(Student::with([
            'circle.stage',
            'guardian',
            'plans' => fn ($q) => $q->latest(),
            'odePlans' => fn ($q) => $q->latest(),
            'odePlans.path.ode',
            'odePlans.path.days',
            'attendances',
            'statusHistories',
        ]))->find($id);

        if (! $this->viewingStudent) {
            Flux::toast(__('الطالب غير موجود أو ليس ضمن صلاحياتك'), variant: 'danger');

            return;
        }

        $this->editingStudentId = $this->viewingStudent->id;
        $this->name = $this->viewingStudent->name;
        $this->email = $this->viewingStudent->email;
        $this->circle_id = $this->viewingStudent->circle_id;
        $this->guardian_id = $this->viewingStudent->guardian_id;
        $this->editJoinedAt = $this->viewingStudent->joined_at ? $this->viewingStudent->joined_at->format('Y-m-d') : '';

        $this->stats = [
            'present' => $this->viewingStudent->attendances->where('status', 'present')->count(),
            'absent' => $this->viewingStudent->attendances->where('status', 'absent')->count(),
            'late' => $this->viewingStudent->attendances->where('status', 'late')->count(),
        ];

        Flux::modal('student-modal')->show();
    }

    public function save(): void
    {
        $student = $this->editingStudentId
            ? $this->scopeToSupervisor(Student::query())->find($this->editingStudentId)
            : null;

        if (! $student) {
            Flux::toast(__('الطالب غير موجود أو ليس ضمن صلاحياتك'), variant: 'danger');

            return;
        }

        $this->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,'.$student->id,
            'circle_id' => 'nullable|exists:circles,id',
            'guardian_id' => ['nullable', Rule::exists('user_roles', 'user_id')->where('role', 'guardian')],
            'editJoinedAt' => 'nullable|date',
        ], [
            'guardian_id.exists' => __('ولي الأمر المختار غير موجود.'),
        ]);

        $circleIds = $this->getSupervisorCircleIds();

        // Validate that the chosen circle is within scope
        if ($this->circle_id && ! in_array($this->circle_id, $circleIds)) {
            Flux::toast(__('الدفعة المختارة خارج نطاق صلاحياتك'), variant: 'danger');

            return;
        }

        $student->update([
            'name' => $this->name,
            'email' => $this->email,
            'circle_id' => $this->circle_id,
            'guardian_id' => $this->guardian_id,
            'joined_at' => $this->editJoinedAt ?: null,
        ]);

        Flux::toast(__('تم تحديث بيانات الطالب بنجاح'), variant: 'success');
        $this->reset(['name', 'email', 'circle_id', 'guardian_id', 'editJoinedAt', 'editingStudentId']);
        Flux::modal('student-modal')->close();
    }

    #[On('student-status-updated')]
    public function refreshViewingStudent(): void
    {
        if ($this->viewingStudent) {
            $this->edit($this->viewingStudent->id);
        }
    }

    public function resetToken($id): void
    {
        $student = $this->scopeToSupervisor(Student::query())->find($id);

        if ($student) {
            $student->update(['access_token' => Str::random(32)]);
            if ($this->viewingStudent && $this->viewingStudent->id === $student->id) {
                $this->viewingStudent->access_token = $student->access_token;
            }
            Flux::toast(__('تم إنشاء رابط الدخول بنجاح'), variant: 'success');
        }
    }

    /**
     * The asks waiting on this sheikh: students who chose one of his groups,
     * and students a teacher asked to place in one.
     *
     * @return Collection<int, StudentPlacementRequest>
     */
    #[Computed]
    public function waitingAsks(): Collection
    {
        return StudentPlacementRequest::pending()
            ->with(['student', 'circle', 'requestedBy'])
            ->whereIn('circle_id', $this->getSupervisorCircleIds())
            ->latest('id')
            ->get();
    }

    /**
     * Approved students in no cohort who have asked for none, from his
     * programmes or from none: the ones nobody could see before.
     *
     * @return Builder<Student>
     */
    private function unchosenQuery(): Builder
    {
        $stageIds = $this->getSupervisorStageIds();

        return Student::query()
            ->whereNull('circle_id')
            ->whereRoleState(fn ($q) => $q->where('is_approved', true))
            ->where(fn ($q) => $q->whereNull('status')->orWhere('status', '!=', StudentStatus::Left->value))
            ->where(fn ($q) => $q->whereNull('stage_id')->orWhereIn('stage_id', $stageIds))
            ->whereNotIn('id', StudentPlacementRequest::pending()->select('student_id'));
    }

    /** @return Collection<int, Student> */
    #[Computed]
    public function unchosen(): Collection
    {
        return $this->unchosenQuery()->latest()->limit(50)->get(['id', 'name', 'created_at', 'stage_id']);
    }

    public function acceptAsk(int $askId): void
    {
        $ask = StudentPlacementRequest::pending()->whereIn('circle_id', $this->getSupervisorCircleIds())->findOrFail($askId);

        StudentPlacementService::approve($ask, auth()->guard('supervisor')->user());

        unset($this->waitingAsks);
        Flux::toast(__('انضم :student إلى :circle', ['student' => $ask->student?->name, 'circle' => trim($ask->circle?->name ?? '')]), variant: 'success');
    }

    public function declineAsk(int $askId): void
    {
        $ask = StudentPlacementRequest::pending()->whereIn('circle_id', $this->getSupervisorCircleIds())->findOrFail($askId);

        StudentPlacementService::reject($ask, auth()->guard('supervisor')->user(), __('ليس من هذه المجموعة'));

        unset($this->waitingAsks, $this->unchosen);
        Flux::toast(__('أُعيد :student ليختار مجموعة أخرى', ['student' => $ask->student?->name]));
    }

    public function takeIntoGroup(int $studentId): void
    {
        $student = $this->unchosenQuery()->findOrFail($studentId);
        // The row's own choice, else the first of his groups — the one its
        // list shows first.
        $circleId = (int) ($this->groupFor[$studentId] ?? 0) ?: (int) ($this->circles->first()?->id ?? 0);

        $circle = Circle::whereIn('id', $this->getSupervisorCircleIds())->findOrFail($circleId);

        StudentPlacementService::placeDirectly($student, $circle, auth()->guard('supervisor')->user());

        unset($this->unchosen);
        Flux::toast(__('انضم :student إلى :circle', ['student' => $student->name, 'circle' => trim($circle->name)]), variant: 'success');
    }

    public function render()
    {
        return view('livewire.supervisor.students', [
            'students' => $this->filteredStudentsQuery()->paginate(20),
            'selectedMagicLinksText' => $this->buildSelectedMagicLinksText(),
            'selectedOutsideFilters' => $this->selectedOutsideFiltersCount(),
        ])->layout('layouts.role-shell');
    }
}
