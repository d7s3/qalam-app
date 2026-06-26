<div class="space-y-6">
    {{-- Page Header --}}
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <div class="p-2 rounded-lg bg-zinc-50 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                <flux:icon icon="map" />
            </div>
            <div>
                <flux:heading size="xl" class="font-bold text-zinc-900 dark:text-white">مسارات حفظ المنظومات</flux:heading>
                <flux:subheading>تعريف مسارات حفظ المنظومات المنهجية (بالأبيات) وتسكين الطلاب فيها</flux:subheading>
            </div>
        </div>
        <flux:button variant="primary" icon="plus" wire:click="createPath">مسار جديد</flux:button>
    </div>

    {{-- Search Bar --}}
    <div class="flex flex-col sm:flex-row gap-2">
        <flux:input icon="magnifying-glass" wire:model.live.debounce.300ms="search" placeholder="بحث باسم المسار أو المنظومة..." class="flex-1" />
    </div>

    {{-- Paths Table Card --}}
    <flux:card class="p-0 overflow-hidden">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>المسار</flux:table.column>
                <flux:table.column>المنظومة التابع لها</flux:table.column>
                <flux:table.column>عدد الأيام</flux:table.column>
                <flux:table.column>تاريخ البدء</flux:table.column>
                <flux:table.column class="w-10"></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($paths as $path)
                    <flux:table.row>
                        <flux:table.cell class="font-bold text-zinc-900 dark:text-white">
                            {{ $path->name }}
                        </flux:table.cell>
                        <flux:table.cell>
                            {{ $path->ode->name ?? 'بلا منظومة' }}
                        </flux:table.cell>
                        <flux:table.cell>
                            {{ $path->days()->count() }} يوماً
                        </flux:table.cell>
                        <flux:table.cell>
                            {{ $path->start_date->format('Y-m-d') }}
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:dropdown>
                                <flux:button variant="ghost" size="xs" icon="ellipsis-horizontal" />
                                <flux:menu>
                                    <flux:menu.item href="{{ route('supervisor.odes.create-plan', ['path_id' => $path->id]) }}" icon="calendar-days" wire:navigate>
                                        تعديل جدول الحفظ
                                    </flux:menu.item>
                                    <flux:menu.item wire:click="showEnrollModal({{ $path->id }})" icon="user-plus">
                                        تسكين الطلاب
                                    </flux:menu.item>
                                    <flux:menu.item wire:click="editPath({{ $path->id }})" icon="pencil-square">
                                        تعديل
                                    </flux:menu.item>
                                    <flux:menu.item wire:click="deletePath({{ $path->id }})" variant="danger" icon="trash"
                                        wire:confirm="هل أنت متأكد من حذف هذا المسار بالكامل؟ سيتم مسح الخطط المترتبة عليه للطلاب.">
                                        حذف
                                    </flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="text-center py-8">
                            <flux:icon icon="map" class="size-8 mx-auto text-zinc-400 mb-2" />
                            <flux:text class="text-zinc-400 text-sm">لا توجد مسارات حفظ معرفة حالياً</flux:text>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    {{-- Path Modal (Create/Edit Path) --}}
    <flux:modal name="path-modal" class="md:w-[500px]">
        <form wire:submit.prevent="savePath" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $editingPathId ? 'تعديل مسار حفظ' : 'مسار حفظ منظومة جديد' }}</flux:heading>
                <flux:subheading>أدخل تفاصيل المسار والمنظومة المرتبطة به.</flux:subheading>
            </div>

            <div class="space-y-4">
                <flux:field>
                    <flux:label>اسم المسار</flux:label>
                    <flux:input wire:model="name" placeholder="مثال: مسار حفظ تحفة الأطفال - بيتان يومياً" required />
                    <flux:error name="name" />
                </flux:field>

                <flux:field>
                    <flux:label>المنظومة</flux:label>
                    <flux:select wire:model="odeId" placeholder="اختر المنظومة...">
                        <flux:select.option value="">اختر المنظومة ...</flux:select.option>
                        @foreach ($odes as $ode)
                            <flux:select.option value="{{ $ode->id }}">{{ $ode->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="odeId" />
                </flux:field>

                <flux:field>
                    <flux:label>تاريخ البدء الافتراضي</flux:label>
                    <livewire:shared.hijri-datepicker wire:model="startDate" label="تاريخ البدء" />
                    <flux:error name="startDate" />
                </flux:field>

                <flux:field>
                    <flux:label>تاريخ الانتهاء (اختياري)</flux:label>
                    <livewire:shared.hijri-datepicker wire:model="endDate" label="تاريخ الانتهاء" />
                    <flux:description>إذا حُدّد، يتوقف توليد الأيام عند هذا التاريخ حتى لو لم تكتمل المنظومة.</flux:description>
                    <flux:error name="endDate" />
                </flux:field>
            </div>

            <div class="flex gap-2 justify-end">
                <flux:modal.close>
                    <flux:button variant="ghost">إلغاء</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">حفظ</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Enroll Students Modal --}}
    <flux:modal name="enroll-modal" class="md:w-[600px]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">تسكين الطلاب في المسار</flux:heading>
                <flux:subheading>اختر الطلاب لتسكينهم في المسار. جميع الطلاب يشتركون في نفس جدول الحفظ دون تكرار البيانات.</flux:subheading>
            </div>

            <div class="space-y-4">
                <flux:input icon="magnifying-glass" wire:model.live.debounce.300ms="studentSearch" placeholder="بحث باسم الطالب..." />

                @php
                    $circleIds = \App\Models\Circle::whereIn('stage_id', auth()->guard('supervisor')->user()?->stages()->pluck('stages.id') ?? [])->pluck('id')->toArray();
                    $students = \App\Models\Student::whereIn('circle_id', $circleIds)
                        ->when($studentSearch, function($q) {
                            $q->where('name', 'like', '%'.$this->studentSearch.'%');
                        })
                        ->with('circle')
                        ->orderBy('name')
                        ->get();

                    $grouped = $students->groupBy(function($student) {
                        return $student->circle->name ?? 'بلا حلقة';
                    });

                    $allStudentIds = $students->pluck('id')->toArray();
                    $selectedIdsInt = array_map('intval', $selectedStudentIds);
                    $isAllSelected = count($allStudentIds) > 0 && count(array_intersect($selectedIdsInt, $allStudentIds)) === count($allStudentIds);
                @endphp

                @if($students->isNotEmpty())
                    <div class="p-3 bg-zinc-100 dark:bg-zinc-800 rounded-xl flex items-center justify-between shadow-xs">
                        <label class="flex items-center gap-2.5 cursor-pointer text-sm font-bold text-zinc-800 dark:text-zinc-200">
                            <input type="checkbox"
                                   wire:click="toggleSelectAll([{{ implode(',', $allStudentIds) }}])"
                                   @if($isAllSelected) checked @endif
                                   class="rounded text-indigo-600 focus:ring-indigo-500 border-zinc-300 dark:border-zinc-700 size-4.5" />
                            <span>تحديد جميع الطلاب ({{ $students->count() }})</span>
                        </label>

                        <span class="text-xs text-zinc-500 dark:text-zinc-400 font-semibold bg-zinc-200 dark:bg-zinc-700/50 px-2.5 py-1 rounded-lg">
                            المحدد: {{ count(array_intersect($selectedIdsInt, $allStudentIds)) }} من {{ $students->count() }}
                        </span>
                    </div>
                @endif

                <div class="border border-zinc-200 dark:border-zinc-800 rounded-xl max-h-[350px] overflow-y-auto bg-zinc-50/50 dark:bg-zinc-900/50 p-3 space-y-4">
                    @forelse($grouped as $circleName => $circleStudents)
                        @php
                            $circleStudentIds = $circleStudents->pluck('id')->toArray();
                            $isCircleAllSelected = count(array_intersect($selectedIdsInt, $circleStudentIds)) === count($circleStudentIds);
                        @endphp

                        <div class="border border-zinc-200 dark:border-zinc-800/80 rounded-xl bg-white dark:bg-zinc-950 p-3.5 shadow-xs">
                            <div class="flex items-center justify-between border-b border-zinc-100 dark:border-zinc-900 pb-2.5 mb-2.5">
                                <span class="font-bold text-sm text-zinc-800 dark:text-zinc-200 flex items-center gap-1.5">
                                    <flux:icon icon="academic-cap" class="size-4 text-zinc-400 dark:text-zinc-500" />
                                    {{ $circleName }}
                                    <span class="text-xs font-normal text-zinc-400 dark:text-zinc-500">({{ $circleStudents->count() }} طالباً)</span>
                                </span>

                                <label class="flex items-center gap-1.5 cursor-pointer text-xs font-bold text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300">
                                    <input type="checkbox"
                                           wire:click="toggleSelectCircle([{{ implode(',', $circleStudentIds) }}])"
                                           @if($isCircleAllSelected) checked @endif
                                           class="rounded text-indigo-600 focus:ring-indigo-500 border-zinc-300 dark:border-zinc-700 size-3.5" />
                                    <span>تحديد الحلقة</span>
                                </label>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                @foreach($circleStudents as $student)
                                    <label class="flex items-center gap-2.5 p-2 hover:bg-zinc-50 dark:hover:bg-zinc-900 rounded-lg cursor-pointer transition-colors border border-zinc-100 dark:border-zinc-900">
                                        <input type="checkbox" wire:model.live="selectedStudentIds" value="{{ $student->id }}" class="rounded text-indigo-600 focus:ring-indigo-500 border-zinc-300 dark:border-zinc-700 size-4" />
                                        <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300 truncate">{{ $student->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <div class="p-8 text-center text-sm text-zinc-400 dark:text-zinc-500">
                            لا توجد نتائج بحث مطابقة.
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="flex gap-2 justify-end">
                <flux:modal.close>
                    <flux:button variant="ghost">إلغاء</flux:button>
                </flux:modal.close>
                <flux:button wire:click="enrollStudents" variant="primary">تسكين الطلاب المحددين</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
