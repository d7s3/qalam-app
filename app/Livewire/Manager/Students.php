<?php

namespace App\Livewire\Manager;

use App\Models\Circle;
use App\Models\Guardian;
use App\Models\Student;
use App\Services\StudentStatusService;
use Flux\Flux;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithPagination;

class Students extends Component
{
    use WithPagination;

    public $circles;

    public string $name = '';

    public string $email = '';

    public $circle_id = null;

    public $editingStudentId = null;

    public string $search = '';

    public string $statusFilter = 'all';

    public string $circleFilter = '';

    public string $guardianFilter = 'all';

    public $guardiansList = [];

    public $guardian_id = null;

    public string $editStatus = 'active';

    public string $editStatusDate = '';

    public string $editJoinedAt = '';

    public function mount()
    {
        $this->circles = Circle::with('stage')->get();
        $this->guardiansList = Guardian::whereRoleState(fn ($q) => $q->where('is_approved', true))->get();
    }

    private function getStudentsQuery()
    {
        $query = Student::with(['circle.stage', 'guardian']);

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%'.$this->search.'%')
                    ->orWhere('email', 'like', '%'.$this->search.'%');
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

        if ($this->guardianFilter !== 'all') {
            $query->where('guardian_id', $this->guardianFilter);
        }

        return $query->latest();
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedStatusFilter()
    {
        $this->resetPage();
    }

    public function updatedCircleFilter()
    {
        $this->resetPage();
    }

    public function updatedGuardianFilter()
    {
        $this->resetPage();
    }

    public function approve($id)
    {
        $student = Student::find($id);

        if (! $student) {
            Flux::toast(__('الطالب غير موجود'), variant: 'danger');

            return;
        }

        $student->update([
            'is_approved' => 1,
            'approved_by' => auth()->id(),
        ]);

        Flux::toast(__('تمت الموافقة على الطالب بنجاح'), variant: 'success');
    }

    public $viewingStudent = null;

    public $stats = [];

    public function edit($id)
    {
        $this->viewingStudent = Student::with([
            'circle.stage',
            'guardian',
            'plans' => function ($q) {
                $q->latest();
            },
            'attendances',
            'statusHistories',
        ])->find($id);

        if (! $this->viewingStudent) {
            Flux::toast(__('الطالب غير موجود'), variant: 'danger');

            return;
        }

        $this->editingStudentId = $this->viewingStudent->id;
        $this->name = $this->viewingStudent->name;
        $this->email = $this->viewingStudent->email;
        $this->circle_id = $this->viewingStudent->circle_id;
        $this->guardian_id = $this->viewingStudent->guardian_id;
        $this->editStatus = $this->viewingStudent->status ?? 'active';
        $this->editStatusDate = now('Asia/Riyadh')->format('Y-m-d');
        $this->editJoinedAt = $this->viewingStudent->joined_at ? $this->viewingStudent->joined_at->format('Y-m-d') : '';

        $this->stats = [
            'present' => $this->viewingStudent->attendances->where('status', 'present')->count(),
            'absent' => $this->viewingStudent->attendances->where('status', 'absent')->count(),
            'late' => $this->viewingStudent->attendances->where('status', 'late')->count(),
        ];

        Flux::modal('student-modal')->show();
    }

    public function save()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,'.$this->editingStudentId,
            'circle_id' => 'nullable|exists:circles,id',
            'guardian_id' => 'nullable|exists:users,id',
            'editStatus' => 'required|in:active,registering,suspended,left',
            'editStatusDate' => 'nullable|date|before_or_equal:today',
            'editJoinedAt' => 'nullable|date',
        ], [
            'editStatusDate.before_or_equal' => __('تاريخ سريان الحالة لا يمكن أن يكون في المستقبل'),
        ]);

        $student = Student::find($this->editingStudentId);

        $student->update([
            'name' => $this->name,
            'email' => $this->email,
            'circle_id' => $this->circle_id,
            'guardian_id' => $this->guardian_id,
            'joined_at' => $this->editJoinedAt ?: null,
        ]);

        StudentStatusService::changeStatus($student, $this->editStatus, $this->editStatusDate ?: null);

        Flux::toast(__('تم تحديث بيانات الطالب بنجاح'), variant: 'success');
        $this->reset(['name', 'email', 'circle_id', 'guardian_id', 'editStatus', 'editStatusDate', 'editJoinedAt', 'editingStudentId']);
        Flux::modal('student-modal')->close();
    }

    public function deleteStatusHistory(int $historyId)
    {
        if (! $this->viewingStudent) {
            return;
        }

        StudentStatusService::deleteHistoryEntry($this->viewingStudent, $historyId);

        $this->edit($this->viewingStudent->id);
        Flux::toast(__('تم حذف سجل الحالة'), variant: 'success');
    }

    public function resetToken($id)
    {
        $student = Student::find($id);
        if ($student) {
            $student->update([
                'access_token' => Str::random(32),
            ]);
            if ($this->viewingStudent && $this->viewingStudent->id === $student->id) {
                $this->viewingStudent->access_token = $student->access_token;
            }
            Flux::toast(__('تم إنشاء رابط الدخول بنجاح'), variant: 'success');
        }
    }

    public function delete($id)
    {
        $student = Student::findOrFail($id);
        $student->delete();
        Flux::toast(__('تم حذف الطالب بنجاح'), variant: 'success');
    }

    public function cancel()
    {
        $this->reset(['name', 'email', 'circle_id', 'guardian_id', 'editingStudentId']);
    }

    public function render()
    {
        return view('livewire.manager.students', [
            'students' => $this->getStudentsQuery()->paginate(20),
        ]);
    }
}
