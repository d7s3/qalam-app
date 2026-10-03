<?php

namespace App\Livewire\Manager;

use App\Models\Circle;
use App\Models\Stage;
use App\Models\Teacher;
use App\Support\Scope;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Circles extends Component
{
    public $circles;

    public $stages;

    public string $name = '';

    public string $description = '';

    public $stage_id = null;

    #[Locked]
    public $editingCircleId = null;

    public string $search = '';

    public $stageFilter = null;

    public string $teacherFilter = 'all';

    public array $selectedTeachers = [];

    public $teachersList = [];

    /**
     * The circle records this reader may act on. The lists were already
     * narrowed to the reader's reach; every id that arrives with a button —
     * approve, edit, save, reset a link, delete — is looked up through here too,
     * so a programme's manager cannot reach into the next programme by id.
     *
     * @return Builder<Circle>
     */
    private function reachable()
    {
        return Scope::forRole('manager')->applyToCircles(Circle::query());
    }

    public function mount()
    {
        $this->loadData();
    }

    public function loadData()
    {
        $this->stages = Stage::all();
        // A man over one programme sees its cohorts, not the academy's.
        $query = Scope::forRole('manager')
            ->applyToCircles(Circle::with(['stage', 'teachers'])->withCount('students'));

        if ($this->search) {
            $query->where('name', 'like', '%'.$this->search.'%');
        }

        if ($this->stageFilter) {
            $query->where('stage_id', $this->stageFilter);
        }

        if ($this->teacherFilter !== 'all') {
            $query->whereHas('teachers', function ($q) {
                $q->where('users.id', $this->teacherFilter);
            });
        }

        $this->circles = $query->latest()->get();
        $this->teachersList = Teacher::whereRoleState(fn ($q) => $q->where('is_approved', true))->get();
    }

    public function updatedSearch()
    {
        $this->loadData();
    }

    public function updatedStageFilter()
    {
        $this->loadData();
    }

    public function updatedTeacherFilter()
    {
        $this->loadData();
    }

    public function save()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'stage_id' => 'required|exists:stages,id',
        ]);

        if ($this->editingCircleId) {
            $circle = $this->reachable()->find($this->editingCircleId);
            $circle->update([
                'name' => $this->name,
                'description' => $this->description,
                'stage_id' => $this->stage_id,
            ]);
            $circle->teachers()->sync($this->selectedTeachers);
            Flux::toast(__('تم تحديث الدفعة بنجاح'), variant: 'success');
        } else {
            $circle = Circle::create([
                'name' => $this->name,
                'description' => $this->description,
                'stage_id' => $this->stage_id,
            ]);
            $circle->teachers()->attach($this->selectedTeachers);
            Flux::toast(__('تم إضافة الدفعة بنجاح'), variant: 'success');
        }

        $this->reset(['name', 'description', 'stage_id', 'editingCircleId', 'selectedTeachers']);
        $this->loadData();
        Flux::modal('circle-modal')->close();
    }

    public function edit($id)
    {
        $circle = $this->reachable()->findOrFail($id);
        $this->editingCircleId = $circle->id;
        $this->name = $circle->name;
        $this->description = $circle->description ?? '';
        $this->stage_id = $circle->stage_id;
        $this->selectedTeachers = $circle->teachers->pluck('id')->toArray();
        Flux::modal('circle-modal')->show();
    }

    public function create()
    {
        $this->cancel();
        Flux::modal('circle-modal')->show();
    }

    public function delete($id)
    {
        $circle = $this->reachable()->findOrFail($id);
        if ($circle->teachers()->count() > 0 || $circle->students()->count() > 0) {
            Flux::toast(__('لا يمكن حذف الدفعة لاحتوائها على معلمين أو طلاب'), variant: 'danger');

            return;
        }

        $circle->delete();
        $this->loadData();
        Flux::toast(__('تم حذف الدفعة بنجاح'), variant: 'success');
    }

    public function cancel()
    {
        $this->reset(['name', 'description', 'stage_id', 'editingCircleId', 'selectedTeachers']);
    }

    public function render()
    {
        return view('livewire.manager.circles');
    }
}
