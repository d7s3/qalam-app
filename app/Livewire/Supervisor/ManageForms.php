<?php

namespace App\Livewire\Supervisor;

use App\Models\Form;
use Flux\Flux;
use Livewire\Component;

class ManageForms extends Component
{
    public function delete($id): void
    {
        $supervisorId = auth()->guard('supervisor')->id();
        $form = Form::where('supervisor_id', $supervisorId)->findOrFail($id);
        $form->delete();

        Flux::toast('تم حذف النموذج بنجاح', variant: 'success');
    }

    public function render()
    {
        $supervisorId = auth()->guard('supervisor')->id();
        $forms = Form::where('supervisor_id', $supervisorId)
            ->withCount('responses')
            ->latest()
            ->get();

        return view('livewire.supervisor.manage-forms', [
            'forms' => $forms,
        ]);
    }
}
