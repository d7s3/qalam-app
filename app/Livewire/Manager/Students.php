<?php

namespace App\Livewire\Manager;

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

    public string $guardianFilter = 'all';

    public $guardiansList = [];

    public $guardian_id = null;

    public string $editStatus = 'active';

    public string $editJoinedAt = '';

    public function mount()
    {
        $this->circles = Circle::with('stage')->get();
        $this->loadData();
    }

    public function loadData()
    {
        $query = Student::with(['circle.stage', 'guardian']);

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

        if ($this->guardianFilter !== 'all') {
            $query->where('guardian_id', $this->guardianFilter);
        }

        $this->students = $query->latest()->get();
        $this->guardiansList = Guardian::where('is_approved', true)->get();
    }

    public function updatedSearch()
    {
        $this->loadData();
    }

    public function updatedStatusFilter()
    {
        $this->loadData();
    }

    public function updatedCircleFilter()
    {
        $this->loadData();
    }

    public function updatedGuardianFilter()
    {
        $this->loadData();
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

        $this->loadData();
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
            'email' => 'required|email|unique:students,email,'.$this->editingStudentId,
            'circle_id' => 'nullable|exists:circles,id',
            'guardian_id' => 'nullable|exists:guardians,id',
            'editStatus' => 'required|in:active,registering,suspended,left',
            'editJoinedAt' => 'nullable|date',
        ]);

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

    public function resetToken($id)
    {
        $student = Student::find($id);
        if ($student) {
            $student->update([
                'access_token' => Str::random(32),
            ]);
            $this->loadData();
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
        $this->loadData();
        Flux::toast(__('تم حذف الطالب بنجاح'), variant: 'success');
    }

    public function cancel()
    {
        $this->reset(['name', 'email', 'circle_id', 'guardian_id', 'editingStudentId']);
    }

    public function render()
    {
        return view('livewire.manager.students');
    }
}
