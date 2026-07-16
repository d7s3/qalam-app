<?php

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Student;
use App\Models\Circle;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Flux\Flux;

new class extends Component {
    use WithPagination;

    public $name = '';
    public $phone = '';
    public $search = '';

    // Modal state
    public $viewingStudent = null;
    public $editName = '';
    public $editPhone = '';
    public $editCircleId = null;
    public $editJoinedAt = '';
    public $stats = [];

    // Unassigned students modal state
    public $unassignedSearch = '';

    public function getUnassignedStudentsProperty()
    {
        return Student::whereNull('circle_id')
            ->when($this->unassignedSearch, function ($query) {
                $query->where('name', 'like', '%' . $this->unassignedSearch . '%');
            })
            ->latest()
            ->take(20)
            ->get();
    }

    public function addToCircle($studentId)
    {
        $teacher = Auth::guard('teacher')->user();

        if (empty($teacher->effectivePermissions()['can_manage_students'])) {
            Flux::toast('ليس لديك صلاحية لإضافة الطلاب', variant: 'danger');
            return;
        }

        $circle = $teacher->circles()->first();

        if (!$circle) {
            Flux::toast('ليس لديك حلقة لإضافة الطالب إليها', variant: 'danger');
            return;
        }

        $student = Student::whereNull('circle_id')->findOrFail($studentId);
        $student->update(['circle_id' => $circle->id, 'joined_at' => now()->format('Y-m-d')]);

        $this->dispatch('student-list-updated');
        Flux::toast('تمت إضافة الطالب للحلقة بنجاح', variant: 'success');
    }

    public function removeFromCircle()
    {
        $teacher = Auth::guard('teacher')->user();

        if (empty($teacher->effectivePermissions()['can_manage_students'])) {
            Flux::toast('ليس لديك صلاحية لإزالة الطلاب', variant: 'danger');
            return;
        }

        $teacherCircles = $teacher->circles()->pluck('id')->toArray();
        if (!in_array($this->viewingStudent->circle_id, $teacherCircles)) {
            abort(403);
        }

        $this->viewingStudent->update(['circle_id' => null, 'status' => 'left']);
        $this->viewingStudent->statusHistories()->create([
            'status' => 'left',
            'start_date' => now(),
            'notes' => 'تمت إزالته من الحلقة عبر إدارة الطلاب',
        ]);

        $this->dispatch('student-list-updated');
        Flux::modal('student-details')->close();
        Flux::toast('تم إزالة الطالب من الحلقة بنجاح', variant: 'success');
    }

    public function createStudent()
    {
        $teacher = Auth::guard('teacher')->user();

        if (empty($teacher->effectivePermissions()['can_create_students'])) {
            Flux::toast('ليس لديك صلاحية إضافة طلاب جدد', variant: 'danger');
            return;
        }

        $this->validate([
            'name' => 'required|string|min:2|max:255',
            'phone' => 'nullable|string|max:20',
        ]);

        $circle = $teacher->circles()->first();

        if (!$circle) {
            Flux::toast('لا توجد حلقة مرتبطة بك لإضافة الطلاب فيها.', variant: 'danger');
            return;
        }

        $student = Student::create([
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => 'student_' . Str::random(10) . '@uncompleted.altag.app',
            'password' => Hash::make(Str::random(10)),
            'circle_id' => $circle->id,
            'is_approved' => true,
            'access_token' => Str::random(32),
            'is_data_completed' => false,
            'status' => 'registering',
            'joined_at' => now()->format('Y-m-d'),
        ]);

        $student->statusHistories()->create([
            'status' => 'registering',
            'start_date' => now(),
            'notes' => 'تسجيل جديد',
        ]);

        $this->dispatch('student-list-updated');
        $this->reset(['name', 'phone']);
        $this->resetPage();

        Flux::toast('تم إنشاء حساب الطالب بنجاح', variant: 'success');
    }

    public function viewStudent($studentId)
    {
        $teacher = Auth::guard('teacher')->user();
        $circleIds = $teacher->circles()->pluck('id');

        $this->viewingStudent = Student::with([
            'circle',
            'guardian',
            'plans' => function ($q) {
                $q->latest();
            },
            'odePlans' => function ($q) {
                $q->latest();
            },
            'odePlans.path.ode',
            'odePlans.path.days',
            'attendances',
            'statusHistories',
        ])
            ->whereIn('circle_id', $circleIds)
            ->findOrFail($studentId);

        $this->editName = $this->viewingStudent->name;
        $this->editPhone = $this->viewingStudent->phone;
        $this->editCircleId = $this->viewingStudent->circle_id;
        $this->editJoinedAt = $this->viewingStudent->joined_at ? $this->viewingStudent->joined_at->format('Y-m-d') : null;

        $this->stats = [
            'present' => $this->viewingStudent->attendances->where('status', 'present')->count(),
            'absent' => $this->viewingStudent->attendances->where('status', 'absent')->count(),
            'late' => $this->viewingStudent->attendances->where('status', 'late')->count(),
        ];

        Flux::modal('student-details')->show();
    }

    public function saveStudentInfo()
    {
        $this->validate([
            'editName' => 'required|string|min:2|max:255',
            'editPhone' => 'nullable|string|max:20',
            'editJoinedAt' => 'nullable|date',
        ]);

        $this->viewingStudent->update([
            'name' => $this->editName,
            'phone' => $this->editPhone,
            'joined_at' => $this->editJoinedAt,
        ]);

        $this->dispatch('student-list-updated');

        Flux::toast('تم حفظ بيانات الطالب بنجاح', variant: 'success');
    }

    #[\Livewire\Attributes\On('student-status-updated')]
    public function refreshViewingStudent()
    {
        if ($this->viewingStudent) {
            $this->viewingStudent->refresh();
        }
    }

    public function resetToken($studentId)
    {
        $teacher = Auth::guard('teacher')->user();
        $circleIds = $teacher->circles()->pluck('id');

        $student = Student::whereIn('circle_id', $circleIds)->findOrFail($studentId);
        $student->update([
            'access_token' => Str::random(32),
        ]);

        Flux::toast('تم إنشاء رابط جديد للطالب بنجاح', variant: 'success');
    }

    public function with()
    {
        $teacher = Auth::guard('teacher')->user();
        $circles = $teacher->circles;
        $circleIds = $circles->pluck('id');

        $students = Student::whereIn('circle_id', $circleIds)
            ->when($this->search, function ($query) {
                $query->where('name', 'like', '%' . $this->search . '%');
            })
            ->latest()
            ->paginate(20);

        return [
            'students' => $students,
            'circles' => $circles,
        ];
    }
};
?>

<div class="space-y-6">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('إدارة طلاب الحلقة') }}</flux:heading>
            <flux:subheading>{{ __('قم بإنشاء حسابات سريعة لطلابك باستخدام روابط الدخول السحرية وإدارة بياناتهم.') }}
            </flux:subheading>
        </div>

    </div>

    <!-- Quick Create Card -->
    @if(Auth::guard('teacher')->user()?->effectivePermissions()['can_create_students'] ?? true)
    <flux:card>
        <form wire:submit="createStudent" class="flex flex-col md:flex-row items-end gap-4">
            <div class="w-full md:w-2/5">
                <flux:input wire:model="name" label="{{ __('اسم الطالب رباعي') }}"
                    placeholder="{{ __('مثال: محمد أحمد') }}" required />
            </div>
            <div class="w-full md:w-2/5">
                <flux:input wire:model="phone" label="{{ __('رقم هاتف الطالب') }}" placeholder="{{ __('اختياري') }}" />
            </div>
            <flux:button type="submit" variant="primary" icon="user-plus" class="min-w-fit">{{ __('إنشاء طالب') }}
            </flux:button>
        </form>
        <flux:button wire:click="$set('unassignedSearch', '')"
            x-on:click="$flux.modal('unassigned-students-modal').show()" icon="magnifying-glass-plus" class="w-full md:w-auto mt-3">
            {{ __('إضافة طالب غير مرتبط بحلقة') }}
        </flux:button>
    </flux:card>
    @endif

    <flux:card class="p-0 overflow-hidden">
        <div class="p-4 border-b border-zinc-100 dark:border-zinc-800 flex items-center justify-between gap-4">
            <flux:input wire:model.live="search" icon="magnifying-glass" placeholder="{{ __('بحث باسم الطالب...') }}"
                class="max-w-xs" />
        </div>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('اسم الطالب') }}</flux:table.column>
                <flux:table.column>{{ __('واتساب') }}</flux:table.column>
                <flux:table.column>{{ __('تاريخ الالتحاق') }}</flux:table.column>
                <flux:table.column>{{ __('حالة الطالب') }}</flux:table.column>
                <flux:table.column>{{ __('حالة البيانات') }}</flux:table.column>
                <flux:table.column>{{ __('رابط الدخول') }}</flux:table.column>
                <flux:table.column class="w-10"></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($students as $student)
                    <flux:table.row wire:key="student-row-{{ $student->id }}"
                        class="cursor-pointer hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors"
                        x-on:click="$flux.modal('student-details').show(); $wire.viewStudent({{ $student->id }})">
                        <flux:table.cell class="font-medium whitespace-nowrap">
                            {{ $student->name }}
                        </flux:table.cell>
                        <flux:table.cell @click.stop="">
                            @if ($student->phone)
                                <flux:button as="a"
                                    href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $student->phone) }}"
                                    target="_blank" size="xs" color="green" icon="chat-bubble-left-ellipsis"
                                    variant="ghost">
                                    {{ __('تواصل') }}
                                </flux:button>
                            @else
                                <span class="text-zinc-400 text-xs">-</span>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="first:ps-3" >
                            {{ $student->joined_at ? $student->joined_at->format('Y-m-d') : '-' }}
                        </flux:table.cell>
                        <flux:table.cell class="first:ps-3" >
                            @php
                                $statusColors = [
                                    'active' => 'green',
                                    'registering' => 'blue',
                                    'suspended' => 'amber',
                                    'left' => 'red',
                                ];
                                $statusLabels = [
                                    'active' => 'مشارك',
                                    'registering' => 'تحت التسجيل',
                                    'suspended' => 'موقوف',
                                    'left' => 'غادر الحلقات',
                                ];
                                $stColor = $statusColors[$student->status] ?? 'zinc';
                                $stLabel = $statusLabels[$student->status] ?? $student->status;
                            @endphp
                            <flux:badge :color="$stColor" size="sm">{{ $stLabel }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell class="first:ps-3" >
                            @if ($student->is_data_completed)
                                <flux:badge color="green" size="sm" icon="check-circle">{{ __('مكتملة') }}
                                </flux:badge>
                            @else
                                <flux:badge color="amber" size="sm" icon="clock">{{ __('غير مكتملة') }}
                                </flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell @click.stop="">
                            @if ($student->access_token)
                                <div class="flex items-center gap-2" x-data="{ copied: false, link: '{{ route('magic-link', ['token' => $student->access_token]) }}' }" @click.stop>
                                    <flux:input readonly copyable class="max-w-xs text-xs"
                                        :value="route('magic-link', ['token' => $student->access_token])" />
                                </div>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="first:ps-3" >
                            <div @click.stop>
                                <flux:dropdown>
                                    <flux:button variant="ghost" size="xs" icon="ellipsis-horizontal" />
                                    <flux:menu>
                                        <flux:menu.item x-on:click="$flux.modal('student-details').show(); $wire.viewStudent({{ $student->id }})" icon="eye">
                                            {{ __('عرض وتعديل التفاصيل') }}</flux:menu.item>
                                        <flux:separator />
                                        @if ($student->access_token)
                                            <flux:menu.item as="a"
                                                href="{{ route('magic-link.login-as', $student->access_token) }}"
                                                target="_blank" icon="arrow-right">{{ __('الدخول لحساب الطالب') }}
                                            </flux:menu.item>
                                        @endif
                                        <flux:menu.item wire:click="resetToken({{ $student->id }})"
                                            wire:confirm="هل أنت متأكد من تغيير الرابط؟ سيتم إبطال الرابط القديم فوراً."
                                            variant="danger" icon="arrow-path">{{ __('إعادة إنشاء الرابط') }}
                                        </flux:menu.item>
                                    </flux:menu>
                                </flux:dropdown>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>

        <div class="p-4 border-t border-zinc-100 dark:border-zinc-800">
            {{ $students->links() }}
        </div>
    </flux:card>

    <!-- Student Details Modal -->
    <flux:modal name="student-details" variant="flyout" class="md:w-[500px]">
        <div wire:loading wire:target="viewStudent" class="w-full h-full flex flex-col items-center justify-center min-h-[300px] text-zinc-400">
            <flux:icon icon="arrow-path" class="size-8 animate-spin mb-4" />
            <p>{{ __('جاري تحميل بيانات الطالب...') }}</p>
        </div>

        <div wire:loading.remove wire:target="viewStudent">
            @if ($viewingStudent)
                <div class="space-y-8">
                <div>
                    <flux:heading size="xl">{{ __('ملف الطالب') }}</flux:heading>
                    <flux:subheading>{{ __('عرض وتعديل بيانات الطالب وخططه') }}</flux:subheading>
                </div>

                <form wire:submit="saveStudentInfo" class="space-y-4">
                    <flux:input wire:model="editName" label="{{ __('اسم الطالب') }}" required />

                    <flux:input wire:model="editPhone" label="{{ __('رقم الهاتف') }}"
                        placeholder="{{ __('اختياري') }}" dir="ltr" class="text-right" />
                        
                    @php
                        $canChangeStatus = Auth::guard('teacher')->user()?->effectivePermissions()['can_change_student_status'] ?? true;
                    @endphp

                    <div class="grid grid-cols-2 gap-4">
                        @if($canChangeStatus)
                        <div>
                            <div class="text-sm font-medium text-zinc-800 dark:text-white mb-1.5">{{ __('حالة الطالب') }}</div>
                            @php
                                $tStatusLabels = ['active' => 'مشارك', 'registering' => 'تحت التسجيل', 'suspended' => 'موقوف', 'left' => 'غادر الحلقات'];
                                $tStatusColors = ['active' => 'green', 'registering' => 'blue', 'suspended' => 'amber', 'left' => 'red'];
                            @endphp
                            <div class="flex items-center gap-2">
                                <flux:badge color="{{ $tStatusColors[$viewingStudent->status] ?? 'zinc' }}">
                                    {{ $tStatusLabels[$viewingStudent->status] ?? $viewingStudent->status }}
                                </flux:badge>
                                <flux:button type="button" size="sm" variant="filled" icon="adjustments-horizontal"
                                    wire:click="$dispatch('open-status-manager', { studentId: {{ $viewingStudent->id }} })">
                                    {{ __('إدارة الحالة') }}
                                </flux:button>
                            </div>
                        </div>
                        <livewire:shared.hijri-datepicker wire:model="editJoinedAt" label="{{ __('تاريخ الالتحاق') }}" />
                        @else
                        <div>
                            <div class="text-xs text-zinc-500 mb-1">{{ __('حالة الطالب') }}</div>
                            <flux:badge size="sm" class="mt-1">{{ $viewingStudent->status }}</flux:badge>
                            <div class="text-[0.65rem] text-zinc-400 mt-1">لا تملك صلاحية التعديل</div>
                        </div>
                        <div>
                            <div class="text-xs text-zinc-500 mb-1">{{ __('تاريخ الالتحاق') }}</div>
                            <div class="text-sm font-medium text-zinc-700 dark:text-zinc-300 mt-1">
                                {{ $viewingStudent->joined_at?->format('Y-m-d') ?? '—' }}
                            </div>
                        </div>
                        @endif
                    </div>

                    <div class="flex justify-between pt-2">
                        <flux:button type="submit" variant="primary" size="sm" icon="check">
                            {{ __('حفظ التعديلات') }}
                        </flux:button>
                    </div>
                </form>


                <flux:separator />

                <!-- Guardian Info -->
                <div>
                    <flux:heading size="sm" class="mb-3">{{ __('ولي الأمر وطرق التواصل') }}</flux:heading>
                    @if ($viewingStudent->guardian)
                        <div class="flex items-center justify-between p-3 bg-zinc-50 dark:bg-zinc-800/50 rounded-xl">
                            <div>
                                <div class="font-medium text-sm">{{ $viewingStudent->guardian->name }}</div>
                                <div class="text-xs text-zinc-500" dir="ltr">
                                    {{ $viewingStudent->guardian->phone ?? 'لا يوجد رقم' }}</div>
                            </div>
                            @if ($viewingStudent->guardian->phone)
                                <flux:button as="a" target="_blank"
                                    href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $viewingStudent->guardian->phone) }}"
                                    size="sm" icon="chat-bubble-left-ellipsis" color="green">
                                    {{ __('واتساب') }}
                                </flux:button>
                            @endif
                        </div>
                    @else
                        <div class="text-sm text-zinc-500 p-3 bg-zinc-50 dark:bg-zinc-800/50 rounded-xl">
                            {{ __('لم يقم الطالب بربط حساب ولي أمر بعد.') }}
                        </div>
                    @endif
                </div>

                <flux:separator />

                <!-- Quran Plans -->
                <div>
                    <flux:heading size="sm" class="mb-3">
                        {{ __('الخطط القرآنية (' . $viewingStudent->plans->count() . ')') }}</flux:heading>

                    <div class="space-y-2 max-h-48 overflow-y-auto pr-2">
                        @forelse($viewingStudent->plans as $plan)
                            <div
                                class="flex items-center justify-between p-3 border border-zinc-200 dark:border-zinc-700/50 rounded-xl hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors">
                                <div class="flex flex-col">
                                    <span class="text-sm font-medium">
                                        @if ($plan->plan_type === 'hifz_review')
                                            {{ __('حفظ ومراجعة') }}
                                        @elseif($plan->plan_type === 'hifz')
                                            {{ __('حفظ') }}
                                        @else
                                            {{ __('مراجعة') }}
                                        @endif
                                    </span>
                                    <span class="text-xs text-zinc-500">{{ $plan->start_date->format('Y/m/d') }} •
                                        {{ $plan->days_count }} يوم</span>
                                </div>
                                <flux:button as="a" href="{{ route('teacher.print-plan', $plan->id) }}"
                                    target="_blank" size="xs" variant="ghost" icon="eye"></flux:button>
                            </div>
                        @empty
                            <div class="text-sm text-zinc-500 text-center py-4">{{ __('ليس لديه خطط مسجلة.') }}</div>
                        @endforelse
                    </div>
                    <div class="mt-3">
                        <flux:button as="a" href="{{ route('teacher.student-recitation-log', $viewingStudent->id) }}" variant="outline" size="sm" icon="clipboard-document-list" class="w-full">
                            {{ __('سجل التسميع الدقيق (الأداء الفعلي)') }}
                        </flux:button>
                    </div>
                </div>

                <flux:separator />

                <!-- Ode Plans -->
                <div>
                    <div class="flex justify-between items-center mb-3">
                        <flux:heading size="sm">
                            {{ __('خطط المنظومات (' . $viewingStudent->odePlans->count() . ')') }}
                        </flux:heading>
                    </div>

                    <div class="space-y-2 max-h-48 overflow-y-auto pr-2">
                        @forelse($viewingStudent->odePlans as $plan)
                            <div class="flex items-center justify-between p-3 border border-zinc-200 dark:border-zinc-700/50 rounded-xl hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors">
                                <div class="flex flex-col">
                                    <span class="text-sm font-medium">
                                        {{ $plan->path->ode->name ?? '—' }}
                                    </span>
                                    <span class="text-xs text-zinc-500">
                                        {{ $plan->start_date->format('Y/m/d') }} •
                                        {{ $plan->path->days->count() ?? 0 }} يوم •
                                        @if($plan->status === 'active')
                                            <span class="text-green-600 font-semibold">نشطة</span>
                                        @else
                                            <span class="text-zinc-400 font-semibold">مكتملة</span>
                                        @endif
                                    </span>
                                </div>
                            </div>
                        @empty
                            <div class="text-sm text-zinc-500 text-center py-4">{{ __('ليس لديه خطط منظومات مسجلة.') }}</div>
                        @endforelse
                    </div>
                </div>

                <flux:separator />

                <!-- Attendance Stats -->
                <div>
                    <flux:heading size="sm" class="mb-3">{{ __('سجل الحضور والغياب الإجمالي') }}
                    </flux:heading>
                    <div class="grid grid-cols-3 gap-3 text-center">
                        <div
                            class="p-3 bg-green-50 dark:bg-green-500/10 rounded-xl border border-green-100 dark:border-green-500/20">
                            <div class="text-2xl font-bold text-green-600 dark:text-green-500">
                                {{ $stats['present'] ?? 0 }}
                            </div>
                            <div class="text-xs text-green-600/70 dark:text-green-500/70 mt-1">{{ __('حضور') }}
                            </div>
                        </div>
                        <div
                            class="p-3 bg-red-50 dark:bg-red-500/10 rounded-xl border border-red-100 dark:border-red-500/20">
                            <div class="text-2xl font-bold text-red-600 dark:text-red-500">{{ $stats['absent'] ?? 0 }}
                            </div>
                            <div class="text-xs text-red-600/70 dark:text-red-500/70 mt-1">{{ __('غياب') }}</div>
                        </div>
                        <div
                            class="p-3 bg-amber-50 dark:bg-amber-500/10 rounded-xl border border-amber-100 dark:border-amber-500/20">
                            <div class="text-2xl font-bold text-amber-600 dark:text-amber-500">
                                {{ $stats['late'] ?? 0 }}
                            </div>
                            <div class="text-xs text-amber-600/70 dark:text-amber-500/70 mt-1">{{ __('تأخر') }}
                            </div>
                        </div>
                    </div>
                </div>

                <flux:separator />

                <!-- Status History -->
                <div>
                    <flux:heading size="sm" class="mb-3">{{ __('سجل الحالات') }}</flux:heading>
                    <div class="space-y-2 max-h-48 overflow-y-auto pr-2">
                        @forelse($viewingStudent->statusHistories as $history)
                            <div class="flex items-center justify-between p-3 border border-zinc-200 dark:border-zinc-700/50 rounded-xl bg-zinc-50 dark:bg-zinc-800/50">
                                <div class="flex flex-col">
                                    <span class="text-sm font-medium">
                                        @php
                                            $hStatusLabels = [
                                                'active' => 'مشارك',
                                                'registering' => 'تحت التسجيل',
                                                'suspended' => 'موقوف',
                                                'left' => 'غادر الحلقات',
                                            ];
                                            $hColor = [
                                                'active' => 'green',
                                                'registering' => 'blue',
                                                'suspended' => 'amber',
                                                'left' => 'red',
                                            ][$history->status] ?? 'zinc';
                                        @endphp
                                        <flux:badge color="{{ $hColor }}" size="sm">{{ $hStatusLabels[$history->status] ?? $history->status }}</flux:badge>
                                    </span>
                                    <span class="text-xs text-zinc-500 mt-1">
                                        {{ $history->start_date->format('Y/m/d') }} 
                                        @if($history->end_date)
                                            - {{ $history->end_date->format('Y/m/d') }}
                                        @else
                                            - {{ __('الآن') }}
                                        @endif
                                    </span>
                                    @if($history->notes)
                                        <span class="text-xs text-zinc-400 mt-1">{{ $history->notes }}</span>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="text-sm text-zinc-500 text-center py-4">{{ __('لا يوجد سجل حالات.') }}</div>
                        @endforelse
                    </div>
                </div>
            </div>
            <div class="flex justify-between pt-6">
                @if(Auth::guard('teacher')->user()?->effectivePermissions()['can_manage_students'] ?? true)
                <flux:button wire:click="removeFromCircle"
                    wire:confirm="{{ __('هل أنت متأكد من إزالة الطالب من الحلقة؟ (لن يتم حذف بياناته، بل سيتم فصله عن حلقتك فقط)') }}"
                    variant="ghost" size="sm" icon="user-minus">{{ __('إزالة من الحلقة') }}</flux:button>
                @endif
            </div>
            @endif
        </div>
    </flux:modal>

    <!-- Unassigned Students Modal -->
    <flux:modal name="unassigned-students-modal" class="md:w-[600px]">
        <flux:heading class="mb-4">{{ __('إضافة طالب للحلقة') }}</flux:heading>

        <flux:input wire:model.live.debounce.300ms="unassignedSearch" icon="magnifying-glass"
            placeholder="ابحث باسم الطالب..." class="mb-4" />

        <div class="space-y-2 max-h-80 overflow-y-auto px-1">
            @forelse($this->unassignedStudents as $unassignedStudent)
                <div
                    class="flex items-center justify-between p-3 border border-zinc-200 dark:border-zinc-700/50 rounded-lg">
                    <div>
                        <div class="font-medium text-sm">{{ $unassignedStudent->name }}</div>
                        <div class="text-xs text-zinc-500">{{ $unassignedStudent->email }}</div>
                    </div>
                    @if(Auth::guard('teacher')->user()?->effectivePermissions()['can_manage_students'] ?? true)
                    <flux:button wire:click="addToCircle({{ $unassignedStudent->id }})" size="sm"
                        icon="plus" variant="primary">{{ __('إضافة') }}</flux:button>
                    @else
                    <flux:badge size="sm" variant="neutral">لا تملك صلاحية</flux:badge>
                    @endif
                </div>
            @empty
                <div class="text-center text-sm text-zinc-500 py-6 bg-zinc-50 dark:bg-zinc-800/50 rounded-xl">
                    {{ __('لا يوجد طلاب غير منضمين لحلقات مطابقين للبحث.') }}
                </div>
            @endforelse
        </div>
    </flux:modal>

    <livewire:shared.student-status-manager />
</div>
