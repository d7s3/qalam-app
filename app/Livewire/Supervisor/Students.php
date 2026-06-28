<?php

namespace App\Livewire\Supervisor;

use App\Models\Circle;
use App\Models\Guardian;
use App\Models\Student;
use Flux\Flux;
use Illuminate\Support\Str;
use Livewire\Component;

class Students extends Component
{
    public $students;

    public $circles;

    public string $name = '';

    public string $email = '';

    public $circle_id = null;

    public $editingStudentId = null;

    public string $search = '';

    public string $statusFilter = 'all';

    public string $circleFilter = '';

    public $guardiansList = [];

    public $guardian_id = null;

    public string $editStatus = 'active';

    public string $editJoinedAt = '';

    public $viewingStudent = null;

    public array $stats = [];

    public array $selectedStudentIds = [];

    public bool $selectAll = false;

    public string $deleteConfirmationInput = '';

    public $bulkCircleId = null;

    public string $bulkJoinedAt = '';

    public string $bulkStatus = 'active';

    public function mount(): void
    {
        $this->loadData();
    }

    private function getSupervisorStageIds(): array
    {
        return auth()->guard('supervisor')->user()->stages()->pluck('stages.id')->toArray();
    }

    private function getSupervisorCircleIds(): array
    {
        return Circle::whereIn('stage_id', $this->getSupervisorStageIds())->pluck('id')->toArray();
    }

    public function loadData(): void
    {
        $circleIds = $this->getSupervisorCircleIds();
        $stageIds = $this->getSupervisorStageIds();
        $this->circles = Circle::with('stage')->whereIn('id', $circleIds)->get();

        // Students in the supervisor's circles, plus circle-less students assigned
        // directly to one of the supervisor's stages (still registering).
        $query = Student::with(['circle.stage', 'stage', 'guardian'])
            ->where(function ($q) use ($circleIds, $stageIds) {
                $q->whereIn('circle_id', $circleIds)
                    ->orWhere(function ($sub) use ($stageIds) {
                        $sub->whereNull('circle_id')->whereIn('stage_id', $stageIds);
                    });
            });

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%'.$this->search.'%')
                    ->orWhere('email', 'like', '%'.$this->search.'%');
            });
        }

        if ($this->statusFilter === 'pending') {
            $query->where('is_approved', false);
        } elseif ($this->statusFilter === 'approved') {
            $query->where('is_approved', true);
        }

        if ($this->circleFilter) {
            $query->where('circle_id', $this->circleFilter);
        }

        $this->students = $query->latest()->get();
        $this->guardiansList = Guardian::where('is_approved', true)->get();
    }

    public function updatedSearch(): void
    {
        $this->resetSelection();
        $this->loadData();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetSelection();
        $this->loadData();
    }

    public function updatedCircleFilter(): void
    {
        $this->resetSelection();
        $this->loadData();
    }

    public function resetSelection(): void
    {
        $this->selectedStudentIds = [];
        $this->selectAll = false;
        $this->deleteConfirmationInput = '';
    }

    public function updatedSelectAll(bool $value): void
    {
        if ($value) {
            $circleIds = $this->getSupervisorCircleIds();
            $query = Student::whereIn('circle_id', $circleIds);

            if ($this->search) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('email', 'like', '%'.$this->search.'%');
                });
            }

            if ($this->statusFilter === 'pending') {
                $query->where('is_approved', false);
            } elseif ($this->statusFilter === 'approved') {
                $query->where('is_approved', true);
            }

            if ($this->circleFilter) {
                $query->where('circle_id', $this->circleFilter);
            }

            $this->selectedStudentIds = $query->pluck('id')->map(fn ($id) => (string) $id)->toArray();
        } else {
            $this->selectedStudentIds = [];
        }
    }

    private function getSupervisorStudentsQuery()
    {
        $circleIds = $this->getSupervisorCircleIds();

        return Student::whereIn('circle_id', $circleIds)->whereIn('id', $this->selectedStudentIds);
    }

    public function applyBulkCircle(): void
    {
        $this->validate([
            'bulkCircleId' => 'nullable|exists:circles,id',
        ]);

        $circleIds = $this->getSupervisorCircleIds();

        if ($this->bulkCircleId && ! in_array((int) $this->bulkCircleId, $circleIds)) {
            Flux::toast(__('الحلقة المختارة خارج نطاق صلاحياتك'), variant: 'danger');

            return;
        }

        $count = $this->getSupervisorStudentsQuery()->update([
            'circle_id' => $this->bulkCircleId ?: null,
        ]);

        $this->resetSelection();
        $this->loadData();
        $this->bulkCircleId = null;

        Flux::modal('bulk-circle-modal')->close();
        Flux::toast(__('تم تغيير حلقة '.$count.' طلاب بنجاح'), variant: 'success');
    }

    public function applyBulkJoinedAt(): void
    {
        $this->validate([
            'bulkJoinedAt' => 'required|date',
        ]);

        $count = $this->getSupervisorStudentsQuery()->update([
            'joined_at' => $this->bulkJoinedAt,
        ]);

        $this->resetSelection();
        $this->loadData();
        $this->bulkJoinedAt = '';

        Flux::modal('bulk-joined-at-modal')->close();
        Flux::toast(__('تم تغيير تاريخ التحاق '.$count.' طلاب بنجاح'), variant: 'success');
    }

    public function applyBulkStatus(): void
    {
        $this->validate([
            'bulkStatus' => 'required|in:active,registering,suspended,left',
        ]);

        $count = $this->getSupervisorStudentsQuery()->update([
            'status' => $this->bulkStatus,
        ]);

        $this->resetSelection();
        $this->loadData();
        $this->bulkStatus = 'active';

        Flux::modal('bulk-status-modal')->close();
        Flux::toast(__('تم تغيير حالة '.$count.' طلاب بنجاح'), variant: 'success');
    }

    public function applyBulkResetMagicLinks(): void
    {
        $students = $this->getSupervisorStudentsQuery()->get();
        $count = $students->count();

        foreach ($students as $student) {
            $student->update([
                'access_token' => Str::random(32),
            ]);
        }

        $this->resetSelection();
        $this->loadData();

        Flux::toast(__('تم تحديث الروابط السحرية لـ '.$count.' طلاب بنجاح'), variant: 'success');
    }

    public function confirmBulkDelete(): void
    {
        if ($this->deleteConfirmationInput !== 'تأكيد الحذف') {
            Flux::toast(__('يرجى إدخال نص التأكيد بشكل صحيح'), variant: 'danger');

            return;
        }

        $students = $this->getSupervisorStudentsQuery()->get();
        $count = $students->count();

        foreach ($students as $student) {
            $student->delete();
        }

        $this->resetSelection();
        $this->loadData();

        Flux::modal('bulk-delete-modal')->close();
        Flux::toast(__('تم حذف '.$count.' طلاب بنجاح'), variant: 'success');
    }

    public function approve($id): void
    {
        $circleIds = $this->getSupervisorCircleIds();
        $student = Student::whereIn('circle_id', $circleIds)->find($id);

        if (! $student) {
            Flux::toast(__('الطالب غير موجود أو ليس ضمن صلاحياتك'), variant: 'danger');

            return;
        }

        $student->update([
            'is_approved' => 1,
            'approved_by' => auth()->id(),
        ]);

        $this->loadData();
        Flux::toast(__('تمت الموافقة على الطالب بنجاح'), variant: 'success');
    }

    public function edit($id): void
    {
        $circleIds = $this->getSupervisorCircleIds();

        $this->viewingStudent = Student::with([
            'circle.stage',
            'guardian',
            'plans' => fn ($q) => $q->latest(),
            'odePlans' => fn ($q) => $q->latest(),
            'odePlans.path.ode',
            'odePlans.path.days',
            'attendances',
            'statusHistories',
        ])
            ->whereIn('circle_id', $circleIds)
            ->find($id);

        if (! $this->viewingStudent) {
            Flux::toast(__('الطالب غير موجود أو ليس ضمن صلاحياتك'), variant: 'danger');

            return;
        }

        $this->editingStudentId = $this->viewingStudent->id;
        $this->name = $this->viewingStudent->name;
        $this->email = $this->viewingStudent->email;
        $this->circle_id = $this->viewingStudent->circle_id;
        $this->guardian_id = $this->viewingStudent->guardian_id;
        $this->editStatus = $this->viewingStudent->status ?? 'active';
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
        $this->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:students,email,'.$this->editingStudentId,
            'circle_id' => 'nullable|exists:circles,id',
            'guardian_id' => 'nullable|exists:guardians,id',
            'editStatus' => 'required|in:active,registering,suspended,left',
            'editJoinedAt' => 'nullable|date',
        ]);

        $circleIds = $this->getSupervisorCircleIds();

        // Validate that the chosen circle is within scope
        if ($this->circle_id && ! in_array($this->circle_id, $circleIds)) {
            Flux::toast(__('الحلقة المختارة خارج نطاق صلاحياتك'), variant: 'danger');

            return;
        }

        Student::find($this->editingStudentId)->update([
            'name' => $this->name,
            'email' => $this->email,
            'circle_id' => $this->circle_id,
            'guardian_id' => $this->guardian_id,
            'status' => $this->editStatus,
            'joined_at' => $this->editJoinedAt ?: null,
        ]);

        Flux::toast(__('تم تحديث بيانات الطالب بنجاح'), variant: 'success');
        $this->reset(['name', 'email', 'circle_id', 'guardian_id', 'editStatus', 'editJoinedAt', 'editingStudentId']);
        $this->loadData();
        Flux::modal('student-modal')->close();
    }

    public function resetToken($id): void
    {
        $circleIds = $this->getSupervisorCircleIds();
        $student = Student::whereIn('circle_id', $circleIds)->find($id);

        if ($student) {
            $student->update(['access_token' => Str::random(32)]);
            $this->loadData();
            if ($this->viewingStudent && $this->viewingStudent->id === $student->id) {
                $this->viewingStudent->access_token = $student->access_token;
            }
            Flux::toast(__('تم إنشاء رابط الدخول بنجاح'), variant: 'success');
        }
    }

    public function render()
    {
        return view('livewire.supervisor.students')
            ->layout('layouts.role-shell');
    }
}
