<div class="space-y-6">
    <div class="flex items-center gap-3 pb-4 border-b border-zinc-100 dark:border-zinc-800">
        <div class="p-2 rounded-lg bg-zinc-50 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
            <flux:icon icon="pencil-square" />
        </div>
        <div>
            <flux:heading size="xl" class="font-bold text-zinc-900 dark:text-white">منشئ خطط المنظومات</flux:heading>
            <flux:subheading>توزيع أبيات المنظومة آلياً لحفظ ومراجعة الطلاب وتنسيقها زمنياً</flux:subheading>
        </div>
    </div>

    @if (!$isGenerated)
        {{-- Plan Configuration Form --}}
        <div class="bg-white dark:bg-zinc-900 rounded-2xl border border-zinc-100 dark:border-zinc-800 shadow-xs p-6 space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                {{-- Select Student --}}
                <flux:field>
                    <flux:label class="font-bold">الطالب المستهدف</flux:label>
                    <flux:select wire:model="studentId" placeholder="اختر طالباً..." search>
                        @foreach ($students as $student)
                            <flux:select.option :value="$student->id">{{ $student->name }} ({{ $student->circle->name ?? 'بلا حلقة' }})</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="studentId" />
                </flux:field>

                {{-- Select Ode --}}
                <flux:field>
                    <flux:label class="font-bold">المنظومة العلمية</flux:label>
                    <flux:select wire:model.live="odeId" placeholder="اختر منظومة...">
                        @foreach ($odes as $ode)
                            <flux:select.option :value="$ode->id">{{ $ode->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="odeId" />
                </flux:field>

                {{-- Start Date --}}
                <flux:field>
                    <flux:label class="font-bold">تاريخ البدء</flux:label>
                    <flux:input type="date" wire:model.live="startDate" />
                    <flux:error name="startDate" />
                </flux:field>
            </div>

            {{-- Active Days of Week --}}
            <div class="space-y-2">
                <flux:label class="font-bold">أيام التسميع الأسبوعية</flux:label>
                <div class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-7 gap-3">
                    @foreach([
                        'Sunday' => 'الأحد',
                        'Monday' => 'الاثنين',
                        'Tuesday' => 'الثلاثاء',
                        'Wednesday' => 'الأربعاء',
                        'Thursday' => 'الخميس',
                        'Friday' => 'الجمعة',
                        'Saturday' => 'السبت'
                    ] as $engDay => $arDay)
                        <label class="flex items-center gap-2 p-3 rounded-xl border border-zinc-100 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-800/10 cursor-pointer hover:bg-zinc-50 dark:hover:bg-zinc-800/20">
                            <flux:checkbox wire:model="activeDays" :value="$engDay" />
                            <span class="text-sm font-semibold text-zinc-700 dark:text-zinc-300">{{ $arDay }}</span>
                        </label>
                    @endforeach
                </div>
                <flux:error name="activeDays" />
            </div>

            <flux:separator />

            {{-- Hifz Settings --}}
            <div class="space-y-4">
                <flux:heading size="md" class="font-bold text-zinc-800 dark:text-zinc-200">إعدادات الحفظ</flux:heading>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <flux:field>
                        <flux:label>من البيت رقم</flux:label>
                        <flux:input type="number" min="1" wire:model="hifzStart" />
                        <flux:error name="hifzStart" />
                    </flux:field>

                    <flux:field>
                        <flux:label>إلى البيت رقم</flux:label>
                        <flux:input type="number" min="1" wire:model="hifzEnd" />
                        <flux:error name="hifzEnd" />
                    </flux:field>

                    <flux:field>
                        <flux:label>معدل الحفظ اليومي (أبيات/يوم)</flux:label>
                        <flux:input type="number" min="1" wire:model="hifzRate" />
                        <flux:error name="hifzRate" />
                    </flux:field>
                </div>
            </div>

            <flux:separator />

            {{-- Review Settings --}}
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <flux:heading size="md" class="font-bold text-zinc-800 dark:text-zinc-200">إعدادات المراجعة</flux:heading>
                    <div class="flex items-center gap-2">
                        <flux:switch wire:model.live="hasReview" />
                        <flux:label>تفعيل خطة المراجعة</flux:label>
                    </div>
                </div>

                @if ($hasReview)
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 transition-opacity duration-200">
                        <flux:field>
                            <flux:label>من البيت رقم</flux:label>
                            <flux:input type="number" min="1" wire:model="reviewStart" />
                            <flux:error name="reviewStart" />
                        </flux:field>

                        <flux:field>
                            <flux:label>إلى البيت رقم</flux:label>
                            <flux:input type="number" min="1" wire:model="reviewEnd" />
                            <flux:error name="reviewEnd" />
                        </flux:field>

                        <flux:field>
                            <flux:label>معدل المراجعة اليومي (أبيات/يوم)</flux:label>
                            <flux:input type="number" min="1" wire:model="reviewRate" />
                            <flux:error name="reviewRate" />
                        </flux:field>
                    </div>
                @endif
            </div>

            <div class="flex justify-end pt-4">
                <flux:button variant="primary" icon="arrow-path" wire:click="generatePreview">توليد الخطة ومعاينتها</flux:button>
            </div>
        </div>
    @else
        {{-- Preview & Confirmation Panel --}}
        <div class="bg-white dark:bg-zinc-900 rounded-2xl border border-zinc-100 dark:border-zinc-800 shadow-xs p-6 space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-zinc-100 dark:border-zinc-800">
                <div>
                    <flux:heading size="lg" class="font-bold text-zinc-900 dark:text-white">معاينة وتعديل خطة المنظومة</flux:heading>
                    <flux:subheading>راجع التوزيع اليومي، ويمكنك التعديل اليدوي على نطاق الأبيات لكل يوم قبل الحفظ النهائي.</flux:subheading>
                </div>
                <div class="flex items-center gap-2">
                    <flux:button variant="ghost" icon="chevron-right" wire:click="resetPlan">رجوع لتعديل المعطيات</flux:button>
                    <flux:button variant="primary" icon="check" wire:click="savePlan">اعتماد وحفظ الخطة</flux:button>
                </div>
            </div>

            <flux:error name="planDays" />

            <div class="border border-zinc-100 dark:border-zinc-800 rounded-xl overflow-hidden shadow-xs">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column class="w-16 text-center">اليوم</flux:table.column>
                        <flux:table.column class="w-32 text-center">التاريخ</flux:table.column>
                        <flux:table.column class="text-center">الحفظ (من بيت - إلى بيت)</flux:table.column>
                        @if ($hasReview)
                            <flux:table.column class="text-center">المراجعة (من بيت - إلى بيت)</flux:table.column>
                        @endif
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($planDays as $index => $day)
                            <flux:table.row :key="$index">
                                <flux:table.cell class="text-center font-bold text-zinc-700 dark:text-zinc-300">
                                    {{ $day['day_name'] }}
                                </flux:table.cell>
                                <flux:table.cell class="text-center text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ $day['date'] }}
                                </flux:table.cell>
                                <flux:table.cell>
                                    <div class="flex items-center justify-center gap-2 max-w-[250px] mx-auto">
                                        <flux:input type="number" size="sm" wire:model="planDays.{{ $index }}.from_verse_number" placeholder="من" class="text-center w-20" />
                                        <span class="text-zinc-400">-</span>
                                        <flux:input type="number" size="sm" wire:model="planDays.{{ $index }}.to_verse_number" placeholder="إلى" class="text-center w-20" />
                                    </div>
                                </flux:table.cell>
                                @if ($hasReview)
                                    <flux:table.cell>
                                        <div class="flex items-center justify-center gap-2 max-w-[250px] mx-auto">
                                            <flux:input type="number" size="sm" wire:model="planDays.{{ $index }}.review_from_verse_number" placeholder="من" class="text-center w-20" />
                                            <span class="text-zinc-400">-</span>
                                            <flux:input type="number" size="sm" wire:model="planDays.{{ $index }}.review_to_verse_number" placeholder="إلى" class="text-center w-20" />
                                        </div>
                                    </flux:table.cell>
                                @endif
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </div>

            <div class="flex justify-end gap-2 pt-4 border-t border-zinc-100 dark:border-zinc-800">
                <flux:button variant="ghost" wire:click="resetPlan">إلغاء وإعادة الضبط</flux:button>
                <flux:button variant="primary" icon="check" wire:click="savePlan">اعتماد وحفظ الخطة</flux:button>
            </div>
        </div>
    @endif
</div>
