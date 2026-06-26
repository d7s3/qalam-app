<div class="space-y-6" dir="rtl" x-data="{
    selected: [],
    selectAll: @entangle('selectAll'),
    selectionStart: null,
    bulkType: @entangle('bulkType'),
    bulkAmount: @entangle('bulkAmount'),
    init() {
        this.selected = [];
        this.$watch('selectAll', value => {
            if (!value) {
                this.selected = [];
            }
        });
    },
    toggleAll() {
        this.selectAll = !this.selectAll;
        if (this.selectAll) {
            const count = this.$wire.planDays ? this.$wire.planDays.length : 0;
            this.selected = Array.from({length: count}, (_, i) => i);
        } else {
            this.selected = [];
        }
    },
    toggleDay(index) {
        if (this.selectionStart === null) {
            this.selectionStart = index;
            if (this.selected.includes(index)) {
                this.selected = this.selected.filter(i => i !== index);
            } else {
                this.selected.push(index);
            }
        } else {
            const start = Math.min(this.selectionStart, index);
            const end = Math.max(this.selectionStart, index);
            const desired = !this.selected.includes(this.selectionStart);
            for (let i = start; i <= end; i++) {
                if (desired) {
                    if (!this.selected.includes(i)) this.selected.push(i);
                } else {
                    this.selected = this.selected.filter(x => x !== i);
                }
            }
            this.selectionStart = null;
        }
    },
    doFill() {
        if (this.selected.length === 0) {
            alert('الرجاء تحديد يوم واحد على الأقل لتطبيق التعبئة.');
            return;
        }
        const indices = [...this.selected].sort((a, b) => a - b);
        this.$wire.fillSelected(this.bulkType, this.bulkAmount, indices).then(() => {
            this.selected = [];
            this.selectAll = false;
        });
    }
}">
    @if(!$isGenerated)
        {{-- STEP 1: INITIAL GENERATOR FORM --}}
        <flux:card class="max-w-2xl mx-auto p-6 space-y-6">
            <div>
                <flux:heading size="xl" class="font-bold text-zinc-900 dark:text-white">إعداد الخطة النموذجية للمسار</flux:heading>
                <flux:subheading>اختر المسار لتوليد وتعديل خطة التوزيع اليومي النموذجية له</flux:subheading>
            </div>

            <form wire:submit.prevent="generatePreview" class="space-y-4">
                <flux:field>
                    <flux:label>المسار المنهجي</flux:label>
                    <flux:select wire:model.live="hadithPathId" placeholder="اختر المسار..." required>
                        <flux:select.option value="">-- اختر المسار --</flux:select.option>
                        @foreach ($paths as $path)
                            <flux:select.option value="{{ $path->id }}">
                                {{ $path->name }} (متن: {{ $path->text->name ?? 'بلا متن' }})
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="hadithPathId" />
                </flux:field>

                @if($hadithPathId)
                    <div class="grid grid-cols-2 gap-4">
                        <flux:field>
                            <flux:label>نوع الحفظ (افتراضي من المسار)</flux:label>
                            <flux:select wire:model.live="memorizeType" disabled>
                                <flux:select.option value="hadiths">بالأحاديث الكاملة</flux:select.option>
                                <flux:select.option value="lines">بالأسطر</flux:select.option>
                            </flux:select>
                        </flux:field>

                        <flux:field>
                            <flux:label>المعدل اليومي (افتراضي من المسار)</flux:label>
                            <flux:input type="number" wire:model.live="memorizeAmount" disabled />
                        </flux:field>
                    </div>
                @endif

                <flux:field>
                    <flux:label>تاريخ البدء</flux:label>
                    <livewire:shared.hijri-datepicker wire:model="startDate" label="تاريخ البدء" />
                    <flux:error name="startDate" />
                </flux:field>

                <flux:field>
                    <flux:label>أيام التسميع الأسبوعية</flux:label>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                        @foreach (['Sunday' => 'الأحد', 'Monday' => 'الاثنين', 'Tuesday' => 'الثلاثاء', 'Wednesday' => 'الأربعاء', 'Thursday' => 'الخميس', 'Saturday' => 'السبت'] as $dayName => $dayAr)
                            <label class="flex items-center gap-2 p-2 bg-zinc-50 dark:bg-zinc-800 rounded-lg cursor-pointer">
                                <input type="checkbox" wire:model="activeDays" value="{{ $dayName }}" class="rounded text-indigo-600 focus:ring-indigo-500 border-zinc-300 dark:border-zinc-700" />
                                <span class="text-sm font-medium">{{ $dayAr }}</span>
                            </label>
                        @endforeach
                    </div>
                    <flux:error name="activeDays" />
                </flux:field>

                <div class="flex justify-end gap-2 pt-4">
                    <flux:button variant="ghost" href="{{ $userRole === 'supervisor' ? route('supervisor.hadiths.paths') : route('teacher.dashboard') }}">إلغاء</flux:button>
                    <flux:button type="submit" variant="primary">توليد الخطة المقترحة</flux:button>
                </div>
            </form>
        </flux:card>
    @else
        {{-- STEP 2: INTERACTIVE PREVIEW & EDIT SECTION --}}
        <flux:card class="p-4 bg-zinc-50/50 dark:bg-zinc-800/30 border border-zinc-100 dark:border-zinc-800">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                <div>
                    <div class="text-xs font-bold text-indigo-500 mb-1">الخطة النموذجية للمسار</div>
                    <flux:heading size="lg" class="font-bold">
                        مسار: {{ $paths->firstWhere('id', $hadithPathId)->name ?? '' }}
                    </flux:heading>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">تاريخ البدء: {{ $startDate }} | عدد الأيام المجدولة: {{ count($planDays) }} أيام</p>
                </div>
                <div class="flex items-center gap-2">
                    <flux:button wire:click="resetPlan" variant="ghost" icon="arrow-path" class="text-rose-500 hover:bg-rose-50">إعادة ضبط وملء جديد</flux:button>
                </div>
            </div>
        </flux:card>

        {{-- BULK ACTIONS PANEL --}}
        <flux:card class="p-4 border border-zinc-100 dark:border-zinc-800 bg-white dark:bg-zinc-900 shadow-sm">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <flux:heading size="sm" class="font-bold flex items-center gap-2 shrink-0">
                    <flux:icon icon="bolt" class="size-4 text-indigo-500" />
                    أدوات الملء التلقائي للأيام المحددة
                </flux:heading>

                <div class="flex flex-wrap items-center gap-2">
                    <div class="flex items-center gap-2">
                        <flux:label class="text-xs font-semibold whitespace-nowrap">النوع:</flux:label>
                        <select x-model="bulkType" class="text-xs rounded border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-zinc-800 dark:text-zinc-200 px-2 py-1.5 outline-none w-36">
                            <option value="hadiths">بالأحاديث الكاملة</option>
                            <option value="lines">بالأسطر</option>
                        </select>
                    </div>

                    <div class="flex items-center gap-2">
                        <flux:label class="text-xs font-semibold whitespace-nowrap">المقدار:</flux:label>
                        <input type="number" min="1" x-model="bulkAmount" class="text-xs rounded border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-zinc-800 dark:text-zinc-200 px-2 py-1 outline-none font-mono w-20" />
                    </div>

                    <flux:button size="sm" variant="primary" @click="doFill">تطبيق التعبئة وإعادة التوزيع</flux:button>
                </div>
            </div>
        </flux:card>

        {{-- DAY TABLE --}}
        <flux:card class="p-0 overflow-hidden border border-zinc-100 dark:border-zinc-800">
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-right align-middle whitespace-nowrap">
                    <thead class="bg-zinc-50 dark:bg-zinc-800 border-b border-zinc-100 dark:border-zinc-700">
                        <tr>
                            <th class="p-4 w-32 font-bold text-zinc-700 dark:text-zinc-300 cursor-pointer hover:bg-zinc-200 dark:hover:bg-zinc-700" @click="toggleAll()">
                                <div class="flex items-center gap-2">
                                    <flux:icon icon="check-circle" class="size-4 opacity-50" />
                                    <span>اليوم والترتيب</span>
                                </div>
                            </th>
                            <th class="p-3 w-40">نوع الحفظ</th>
                            <th class="p-3 w-40">المقدار</th>
                            <th class="p-3">نطاق الحفظ اليومي</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($planDays as $index => $day)
                            <tr wire:key="row-{{ $index }}" :class="selected.includes({{ $index }}) ? 'bg-indigo-50/50 dark:bg-indigo-900/10' : ''">
                                {{-- Checkbox / Date --}}
                                <td class="p-3 cursor-pointer select-none hover:bg-zinc-50 dark:hover:bg-zinc-800" @click="toggleDay({{ $index }})">
                                    <div class="flex items-center gap-3">
                                        <input type="checkbox" :value="{{ $index }}" x-model.number="selected" @click.stop class="rounded text-indigo-600 focus:ring-indigo-500 border-zinc-300" />
                                        <div class="flex flex-col">
                                            <span class="font-bold text-zinc-800 dark:text-zinc-200">اليوم {{ $index + 1 }}</span>
                                            <span class="text-xs text-zinc-500 mt-0.5">{{ $day['day_name'] }} ({{ $day['date'] }})</span>
                                        </div>
                                    </div>
                                </td>

                                {{-- Memorize Type dropdown --}}
                                <td class="p-3">
                                    <select wire:model.live="planDays.{{ $index }}.memorize_type" class="w-full text-xs rounded border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-zinc-800 dark:text-zinc-200 px-2 py-1.5 outline-none">
                                        <option value="hadiths">بالأحاديث</option>
                                        <option value="lines">بالأسطر</option>
                                    </select>
                                </td>

                                {{-- Memorize Amount input --}}
                                <td class="p-3">
                                    <input type="number" min="1" wire:model.live="planDays.{{ $index }}.memorize_amount" class="w-full text-center text-xs rounded border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-zinc-800 dark:text-zinc-200 py-1 outline-none font-mono" />
                                </td>

                                {{-- Ranges dropdowns --}}
                                <td class="p-3">
                                    <div class="flex items-center gap-2">
                                        @if($day['memorize_type'] === 'lines')
                                            {{-- LINES range selection --}}
                                            <div class="flex items-center gap-2 w-full max-w-lg">
                                                <select wire:model.live="planDays.{{ $index }}.from_hadith_id" class="flex-1 text-xs rounded border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-zinc-800 dark:text-zinc-200 px-2 py-1.5 outline-none">
                                                    <option value="">-- اختر الحديث --</option>
                                                    @foreach ($hadiths as $h)
                                                        <option value="{{ $h->id }}">{{ $h->name }}</option>
                                                    @endforeach
                                                </select>
                                                
                                                <flux:label class="text-xs text-zinc-500">من السطر</flux:label>
                                                <select wire:model.live="planDays.{{ $index }}.from_line_number" class="w-20 text-xs rounded border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-zinc-800 dark:text-zinc-200 px-2 py-1.5 outline-none">
                                                    @php
                                                        $selectedHadith = $hadiths->firstWhere('id', $day['from_hadith_id']);
                                                        $maxLines = $selectedHadith ? ($selectedHadith->lines->count() ?: 1) : 1;
                                                    @endphp
                                                    @for ($i = 1; $i <= $maxLines; $i++)
                                                        <option value="{{ $i }}">{{ $i }}</option>
                                                    @endfor
                                                </select>

                                                <flux:label class="text-xs text-zinc-500">إلى</flux:label>
                                                <select wire:model.live="planDays.{{ $index }}.to_line_number" class="w-20 text-xs rounded border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-zinc-800 dark:text-zinc-200 px-2 py-1.5 outline-none">
                                                    @php
                                                        $selectedHadith = $hadiths->firstWhere('id', $day['from_hadith_id']);
                                                        $maxLines = $selectedHadith ? ($selectedHadith->lines->count() ?: 1) : 1;
                                                        $startLine = $day['from_line_number'] ?: 1;
                                                    @endphp
                                                    @for ($i = $startLine; $i <= $maxLines; $i++)
                                                        <option value="{{ $i }}">{{ $i }}</option>
                                                    @endfor
                                                </select>
                                            </div>
                                        @else
                                            {{-- HADITHS range selection --}}
                                            <div class="flex items-center gap-2 w-full max-w-lg">
                                                <flux:label class="text-xs text-zinc-500">من</flux:label>
                                                <select wire:model.live="planDays.{{ $index }}.from_hadith_id" class="flex-1 text-xs rounded border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-zinc-800 dark:text-zinc-200 px-2 py-1.5 outline-none">
                                                    <option value="">-- الحديث الأول --</option>
                                                    @foreach ($hadiths as $h)
                                                        <option value="{{ $h->id }}">{{ $h->name }}</option>
                                                    @endforeach
                                                </select>

                                                <flux:label class="text-xs text-zinc-500">إلى</flux:label>
                                                <select wire:model.live="planDays.{{ $index }}.to_hadith_id" class="flex-1 text-xs rounded border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-zinc-800 dark:text-zinc-200 px-2 py-1.5 outline-none">
                                                    <option value="">-- الحديث الأخير --</option>
                                                    @php
                                                        $startHadithIdx = $hadiths->search(fn($h) => $h->id === $day['from_hadith_id']) ?: 0;
                                                        $availableToHadiths = $hadiths->slice($startHadithIdx);
                                                    @endphp
                                                    @foreach ($availableToHadiths as $h)
                                                        <option value="{{ $h->id }}">{{ $h->name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </flux:card>

        {{-- FORM ACTIONS --}}
        <div class="flex justify-end gap-2">
            <flux:button variant="ghost" wire:click="resetPlan">إلغاء</flux:button>
            <flux:button variant="primary" wire:click="savePlan" icon="check">حفظ خطة المسار وتوزيع الحفظ</flux:button>
        </div>
    @endif

    {{-- Confirmation modal for deleting affected achievements --}}
    <flux:modal name="confirm-delete-achievements" variant="flyout" class="max-w-md">
        <div class="space-y-4">
            <flux:heading size="lg">⚠️ تأكيد حذف التقييمات</flux:heading>
            <flux:text>
                <p class="text-red-600 dark:text-red-400 font-semibold">
                    يوجد {{ $affectedAchievementsCount }} تقييم مرتبط بالأيام المتأثرة (من اليوم رقم {{ $affectedFromDayNumber }} وما بعده).
                </p>
                <p class="mt-2">
                    سيتم <strong>حذف جميع التقييمات</strong> لهذا اليوم والأيام اللاحقة. التقييمات السابقة لهذا اليوم ستبقى كما هي.
                </p>
                <p class="mt-2 text-sm text-zinc-500">هذا الإجراء لا يمكن التراجع عنه.</p>
            </flux:text>
            <div class="flex justify-end gap-2 pt-4">
                <flux:button variant="ghost" wire:click="cancelSave">إلغاء</flux:button>
                <flux:button variant="danger" wire:click="confirmSaveWithDeletion" icon="trash">تأكيد الحذف والحفظ</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
