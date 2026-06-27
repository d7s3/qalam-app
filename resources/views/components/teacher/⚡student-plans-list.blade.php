<?php

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\StudentPlan;
use Illuminate\Support\Facades\Auth;

new class extends Component {
    use WithPagination;

    public $search = '';

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public $showStudentModal = false;
    public $modalAction = ''; // 'change' or 'duplicate'
    public $selectedPlanId = null;
    public $selectedNewStudentId = null;
    public $studentsList = [];
    public $hasAchievements = false;
    public $keepAchievements = null;

    public function openStudentModal($planId, $action)
    {
        $this->selectedPlanId = $planId;
        $this->modalAction = $action;
        $this->selectedNewStudentId = null;
        $this->hasAchievements = false;
        $this->keepAchievements = null;

        $teacher = Auth::guard('teacher')->user();
        $circleIds = $teacher->circles()->pluck('id');
        $this->studentsList = \App\Models\Student::whereIn('circle_id', $circleIds)->get();

        $plan = StudentPlan::with('days')->whereHas('student', function ($q) use ($circleIds) {
            $q->whereIn('circle_id', $circleIds);
        })->findOrFail($planId);

        if ($action === 'change') {
            $this->hasAchievements = collect($plan->days)->contains(function ($day) {
                return !is_null($day->hifz_achievement) || !is_null($day->review_achievement);
            });
        }

        $this->showStudentModal = true;
    }

    public function executeStudentAction()
    {
        $rules = [
            'selectedNewStudentId' => 'required|exists:students,id',
        ];
        $messages = [
            'selectedNewStudentId.required' => 'يرجى اختيار طالب',
        ];

        if ($this->modalAction === 'change' && $this->hasAchievements) {
            $rules['keepAchievements'] = 'required|in:yes,no';
            $messages['keepAchievements.required'] = 'يرجى تحديد خيار التعامل مع الإنجازات السابقة';
        }

        $this->validate($rules, $messages);

        $teacher = Auth::guard('teacher')->user();
        $circleIds = $teacher->circles()->pluck('id');
        $plan = StudentPlan::with('days')->whereHas('student', function ($q) use ($circleIds) {
            $q->whereIn('circle_id', $circleIds);
        })->findOrFail($this->selectedPlanId);

        if ($this->modalAction === 'change') {
            $plan->update(['student_id' => $this->selectedNewStudentId]);

            if ($this->hasAchievements && $this->keepAchievements === 'no') {
                foreach ($plan->days as $day) {
                    $day->update([
                        'hifz_achievement' => null,
                        'review_achievement' => null,
                        'hifz_graded_at' => null,
                        'review_graded_at' => null,
                    ]);
                }
                session()->flash('success', 'تم نقل الخطة للطالب ومسح الإنجازات السابقة بنجاح');
            } else {
                session()->flash('success', 'تم نقل الخطة للطالب الجديد بنجاح');
            }
        } elseif ($this->modalAction === 'duplicate') {
            $newPlan = $plan->replicate();
            $newPlan->student_id = $this->selectedNewStudentId;
            $newPlan->teacher_id = $teacher->id;
            $newPlan->save();

            foreach ($plan->days as $day) {
                $newDay = $day->replicate();
                $newDay->student_plan_id = $newPlan->id;
                $newDay->hifz_achievement = null;
                $newDay->review_achievement = null;
                $newDay->hifz_graded_at = null;
                $newDay->review_graded_at = null;
                $newDay->save();
            }
            session()->flash('success', 'تم نسخ الخطة للطالب الجديد بنجاح');
        }

        $this->showStudentModal = false;
        $this->selectedPlanId = null;
        $this->selectedNewStudentId = null;
    }

    public function approvePlan($id)
    {
        $teacher = Auth::guard('teacher')->user();
        $circleIds = $teacher->circles()->pluck('id');
        $plan = StudentPlan::whereHas('student', function ($q) use ($circleIds) {
            $q->whereIn('circle_id', $circleIds);
        })->findOrFail($id);
        $plan->update(['is_approved' => true]);
        session()->flash('success', 'تم اعتماد الخطة بنجاح');
    }

    public function deletePlan($id)
    {
        $teacher = Auth::guard('teacher')->user();
        $circleIds = $teacher->circles()->pluck('id');
        $plan = StudentPlan::whereHas('student', function ($q) use ($circleIds) {
            $q->whereIn('circle_id', $circleIds);
        })->findOrFail($id);
        $plan->delete();
        session()->flash('success', 'تم حذف الخطة بنجاح');
    }

    public function with()
    {
        $teacher = Auth::guard('teacher')->user();
        $circleIds = $teacher->circles()->pluck('id');

        $plans = StudentPlan::with('student')
            ->whereHas('student', function ($q) use ($circleIds) {
                $q->whereIn('circle_id', $circleIds);
            })
            ->when($this->search, function ($query) {
                $query->whereHas('student', function ($q) {
                    $q->where('name', 'like', '%' . $this->search . '%');
                });
            })
            // Move unapproved to top, then active ones
            ->orderBy('is_approved', 'asc')
            ->latest()
            ->paginate(20);

        return [
            'plans' => $plans,
        ];
    }
};
?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl" level="1">{{ __('الخطط الدراسية المنشأة') }}</flux:heading>
            <flux:subheading>{{ __('إدارة وعرض خطط الحفظ والمراجعة لطلابك') }}</flux:subheading>
        </div>
        <flux:button variant="primary" icon="plus" href="{{ route('teacher.plan-creator') }}">
            {{ __('إنشاء خطة جديدة') }}
        </flux:button>
    </div>

    <flux:card class="p-0 overflow-hidden">
        <div class="p-4 border-b border-zinc-100 dark:border-zinc-800 flex items-center justify-between gap-4">
            <flux:input wire:model.live="search" icon="magnifying-glass" placeholder="{{ __('بحث باسم الطالب...') }}"
                class="max-w-xs" />
        </div>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('الطالب') }}</flux:table.column>
                <flux:table.column>{{ __('نوع الخطة') }}</flux:table.column>
                <flux:table.column>{{ __('تاريخ البدء') }}</flux:table.column>
                <flux:table.column>{{ __('عدد الأيام') }}</flux:table.column>
                <flux:table.column>{{ __('الحالة') }}</flux:table.column>
                <flux:table.column class="w-10"></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach($plans as $plan)
                    <flux:table.row class="{{ !$plan->is_approved ? 'bg-amber-50/50 dark:bg-amber-900/10' : '' }}">
                        <flux:table.cell class="font-medium">{{ $plan->student->name }}</flux:table.cell>
                        <flux:table.cell class="first:ps-3" >
                            @if($plan->plan_type === 'review')
                                <flux:badge color="indigo" size="sm">{{ __('مراجعة') }}</flux:badge>
                            @elseif($plan->plan_type === 'hifz_review')
                                <flux:badge color="teal" size="sm">{{ __('حفظ ومراجعة') }}</flux:badge>
                            @else
                                <flux:badge color="green" size="sm">{{ __('حفظ') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="first:ps-3" >{{ $plan->start_date->format('Y-m-d') }}</flux:table.cell>
                        <flux:table.cell class="first:ps-3" >{{ $plan->days_count }}</flux:table.cell>
                        <flux:table.cell class="first:ps-3" >
                            @if(!$plan->is_approved)
                                <flux:badge color="amber" size="sm" icon="clock">{{ __('قيد الاعتماد') }}</flux:badge>
                            @else
                                <flux:badge size="sm">{{ $plan->status }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="first:ps-3" >
                            <flux:dropdown>
                                <flux:button variant="ghost" size="xs" icon="ellipsis-horizontal" />
                                <flux:menu>
                                    @if(!$plan->is_approved)
                                        <flux:menu.item wire:click="approvePlan({{ $plan->id }})" icon="check-circle"
                                            class="text-emerald-600 dark:text-emerald-400">{{ __('اعتماد الخطة') }}
                                        </flux:menu.item>
                                    @endif
                                    <flux:menu.item href="{{ route('teacher.plan-creator', ['edit' => $plan->id]) }}"
                                        icon="pencil">{{ __('تعديل') }}</flux:menu.item>
                                    <flux:menu.item href="{{ route('teacher.print-plan', $plan->id) }}" target="_blank"
                                        icon="printer">{{ __('عرض وطباعة') }}</flux:menu.item>
                                    <flux:menu.item href="{{ route('teacher.download-plan-pdf', $plan->id) }}"
                                        icon="document-arrow-down">{{ __('تحميل كـ PDF') }}</flux:menu.item>

                                    <flux:menu.separator />

                                    <flux:menu.item wire:click="openStudentModal({{ $plan->id }}, 'duplicate')"
                                        icon="document-duplicate">
                                        {{ __('نسخ الخطة لطالب آخر') }}
                                    </flux:menu.item>

                                    <flux:menu.separator />

                                    <flux:menu.item wire:click="deletePlan({{ $plan->id }})" variant="danger" icon="trash"
                                        wire:confirm="{{ __('هل أنت متأكد من حذف هذه الخطة بالكامل؟') }}">{{ __('حذف') }}
                                    </flux:menu.item>
                                    <flux:menu.item wire:click="openStudentModal({{ $plan->id }}, 'change')"
                                        icon="user-circle">
                                        {{ __('نقل الخطة لطالب آخر') }}
                                    </flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>

        <div class="p-4 border-t border-zinc-100 dark:border-zinc-800">
            {{ $plans->links() }}
        </div>
    </flux:card>

    <flux:modal wire:model="showStudentModal" class="md:w-96">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">
                    {{ $modalAction === 'change' ? __('نقل الخطة لطالب آخر') : __('نسخ الخطة لطالب آخر') }}
                </flux:heading>
                <flux:subheading>
                    {{ $modalAction === 'change' ? __('اختر الطالب الذي تود نقل هذه الخطة إليه.') : __('اختر الطالب الذي تود نسخ الخطة له (بدون بيانات الإنجاز).') }}
                </flux:subheading>
            </div>

            <flux:select wire:model="selectedNewStudentId" label="{{ __('اختر الطالب') }}"
                placeholder="{{ __('الرجاء الاختيار...') }}">
                @foreach($studentsList as $student)
                    <flux:select.option value="{{ $student->id }}">{{ $student->name }}</flux:select.option>
                @endforeach
            </flux:select>

            @if($modalAction === 'change' && $hasAchievements)
                <div
                    class="space-y-3 bg-amber-50 dark:bg-amber-900/20 border border-amber-100 dark:border-amber-900/50 rounded-xl p-4">
                    <div class="flex gap-2 text-amber-700 dark:text-amber-500 font-medium">
                        <flux:icon icon="exclamation-triangle" class="w-5 h-5 shrink-0" />
                        <span class="text-sm">{{ __('هذه الخطة تحتوي على أيام منجزة وتسميعات سابقة.') }}</span>
                    </div>

                    <flux:radio.group wire:model.live="keepAchievements"
                        label="{{ __('كيف تود التعامل مع الإنجازات السابقة؟') }}">
                        <flux:radio value="yes" label="{{ __('نقل الإنجازات والتسميعات مع الخطة') }}" />
                        <flux:radio value="no" label="{{ __('تصفير ومسح الإنجازات لتبدأ كخطة جديدة') }}" />
                    </flux:radio.group>
                    @error('keepAchievements')
                        <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                    @enderror

                    @if($keepAchievements === 'no')
                        <p class="text-xs text-red-600 dark:text-red-400 mt-2 font-bold">
                            {{ __('تنبيه: سيتم مسح جميع الإنجازات والتقييمات المسجلة في هذه الخطة بشكل نهائي، ولا يمكن التراجع عن هذا الإجراء.') }}
                        </p>
                    @endif
                </div>
            @endif

            <div class="flex gap-2">
                <flux:spacer />
                <flux:button wire:click="$set('showStudentModal', false)">{{ __('إلغاء') }}</flux:button>
                <flux:button wire:click="executeStudentAction" variant="primary">{{ __('تأكيد') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>