<?php

namespace App\Livewire\Supervisor;

use App\Models\TeacherCompetition;
use Flux\Flux;
use Livewire\Component;

class TeacherCompetitions extends Component
{
    public $competitions;

    public string $name = '';

    public string $start_date = '';

    public string $end_date = '';

    public function mount(): void
    {
        $this->loadData();
    }

    private function supervisorId(): int
    {
        return auth()->guard('supervisor')->id();
    }

    public function loadData(): void
    {
        $this->competitions = TeacherCompetition::where('supervisor_id', $this->supervisorId())
            ->withCount('participants')
            ->latest()
            ->get();
    }

    public function create(): void
    {
        $this->reset(['name', 'start_date', 'end_date']);
        Flux::modal('teacher-competition-modal')->show();
    }

    public function save(): void
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        TeacherCompetition::create([
            'name' => $this->name,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'supervisor_id' => $this->supervisorId(),
        ]);

        Flux::toast(__('تم إنشاء المسابقة بنجاح'), variant: 'success');
        $this->reset(['name', 'start_date', 'end_date']);
        $this->loadData();
        Flux::modal('teacher-competition-modal')->close();
    }

    public function toggleActive(int $id): void
    {
        $competition = TeacherCompetition::where('supervisor_id', $this->supervisorId())->findOrFail($id);
        $competition->update(['is_active' => ! $competition->is_active]);

        $this->loadData();
        Flux::toast($competition->is_active ? __('تم تفعيل المسابقة') : __('تم إنهاء المسابقة'), variant: 'success');
    }

    public function delete(int $id): void
    {
        TeacherCompetition::where('supervisor_id', $this->supervisorId())->findOrFail($id)->delete();

        $this->loadData();
        Flux::toast(__('تم حذف المسابقة بنجاح'), variant: 'success');
    }

    public function cancel(): void
    {
        $this->reset(['name', 'start_date', 'end_date']);
    }

    public function render()
    {
        return view('livewire.supervisor.teacher-competitions')->layout('layouts.role-shell');
    }
}
