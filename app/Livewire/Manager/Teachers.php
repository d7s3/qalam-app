<?php

namespace App\Livewire\Manager;

use App\Models\Circle;
use App\Models\Teacher;
use Flux\Flux;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Component;

class Teachers extends Component
{
    public $teachers;

    public $circles;

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public array $selectedCircles = [];

    public $editingTeacherId = null;

    public string $quickName = '';

    public string $quickPhone = '';

    public string $search = '';

    public string $statusFilter = 'all';

    public string $circleFilter = 'all';

    public function mount()
    {
        $this->loadData();
    }

    public function loadData()
    {
        $this->circles = Circle::with('stage')->get();
        $query = Teacher::with('circles');

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

        if ($this->circleFilter !== 'all') {
            $query->whereHas('circles', function ($q) {
                $q->where('circles.id', $this->circleFilter);
            });
        }

        $this->teachers = $query->latest()->get();
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

    public function approve($id)
    {
        $teacher = Teacher::find($id);

        if (! $teacher) {
            Flux::toast(__('المعلم غير موجود'), variant: 'danger');

            return;
        }

        $teacher->update([
            'is_approved' => true,
            'approved_by' => auth()->id(),
        ]);
        $this->loadData();
        Flux::toast(__('تمت الموافقة على المعلم بنجاح'), variant: 'success');
    }

    public function createQuickTeacher()
    {
        $this->validate([
            'quickName' => 'required|string|min:2|max:255',
            'quickPhone' => 'nullable|string|max:20',
        ]);

        Teacher::create([
            'name' => $this->quickName,
            'phone' => $this->quickPhone,
            'email' => 'teacher_'.Str::random(10).'@unregistered.invalid',
            'password' => Hash::make(Str::random(10)),
            'is_approved' => true,
            'approved_by' => auth()->id(),
            'access_token' => Str::random(32),
            'is_data_completed' => false,
        ]);

        $this->reset(['quickName', 'quickPhone']);
        $this->loadData();

        Flux::toast(__('تم إنشاء حساب المعلم بنجاح'), variant: 'success');
    }

    public function resetToken($id)
    {
        $teacher = Teacher::find($id);
        if ($teacher) {
            $teacher->update([
                'access_token' => Str::random(32),
            ]);
            $this->loadData();
            if ($this->viewingTeacher && $this->viewingTeacher->id === $teacher->id) {
                $this->viewingTeacher->access_token = $teacher->access_token;
            }
            Flux::toast(__('تم إعادة إنشاء الرابط السحري بنجاح'), variant: 'success');
        }
    }

    public $viewingTeacher = null;

    public function edit($id)
    {
        $this->viewingTeacher = Teacher::with('circles')->find($id);

        if (! $this->viewingTeacher) {
            Flux::toast(__('المعلم غير موجود'), variant: 'danger');

            return;
        }

        $this->editingTeacherId = $this->viewingTeacher->id;
        $this->name = $this->viewingTeacher->name;
        $this->email = $this->viewingTeacher->email;
        $this->phone = $this->viewingTeacher->phone ?? '';
        $this->selectedCircles = $this->viewingTeacher->circles->pluck('id')->toArray();
        Flux::modal('teacher-modal')->show();
    }

    public function save()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,'.$this->editingTeacherId,
            'phone' => 'nullable|string|max:20',
        ]);

        $teacher = Teacher::find($this->editingTeacherId);
        $teacher->update([
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
        ]);

        $teacher->circles()->sync($this->selectedCircles);

        Flux::toast(__('تم تحديث بيانات المعلم بنجاح'), variant: 'success');
        $this->reset(['name', 'email', 'phone', 'selectedCircles', 'editingTeacherId']);
        $this->loadData();
        Flux::modal('teacher-modal')->close();
    }

    public function delete($id)
    {
        $teacher = Teacher::find($id);

        if ($teacher) {
            $teacher->delete();
        }

        $this->loadData();
        Flux::toast(__('تم حذف المعلم بنجاح'), variant: 'success');
    }

    public function cancel()
    {
        $this->reset(['name', 'email', 'phone', 'selectedCircles', 'editingTeacherId']);
    }

    public function render()
    {
        return view('livewire.manager.teachers');
    }
}
