<div class="space-y-6" dir="rtl">
    <div class="flex items-center gap-3 pb-4 border-b border-zinc-100 dark:border-zinc-800">
        <div class="p-2 rounded-lg bg-zinc-50 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
            <flux:icon icon="pencil-square" />
        </div>
        <div>
            <flux:heading size="xl" class="font-bold text-zinc-900 dark:text-white">منشئ خطط المنظومات</flux:heading>
            <flux:subheading>توليد وتعديل خطة التوزيع اليومي النموذجية للمسار، يشترك فيها جميع الطلاب المسكّنين</flux:subheading>
        </div>
    </div>

    @if (!$isGenerated)
        {{-- STEP 1: PATH SELECTION & PLAN CONFIGURATION FORM --}}
        <div class="bg-white dark:bg-zinc-900 rounded-2xl border border-zinc-100 dark:border-zinc-800 shadow-xs p-6 space-y-6">
            <flux:field>
                <flux:label class="font-bold">المسار المنهجي</flux:label>
                <flux:select wire:model.live="odePathId" placeholder="اختر المسار..." required>
                    <flux:select.option value="">-- اختر المسار --</flux:select.option>
                    @foreach ($paths as $path)
                        <flux:select.option value="{{ $path->id }}">
                            {{ $path->name }} (منظومة: {{ $path->ode->name ?? 'بلا منظومة' }})
                        </flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="odePathId" />
            </flux:field>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                {{-- Start Date --}}
                <flux:field>
                    <flux:label class="font-bold">تاريخ البدء</flux:label>
                    <flux:input type="date" wire:model.live="startDate" />
                    <flux:error name="startDate" />
                </flux:field>

                {{-- End Date (optional cap) --}}
                <flux:field>
                    <flux:label class="font-bold">تاريخ الانتهاء (اختياري)</flux:label>
                    <flux:input type="date" wire:model.live="endDate" />
                    <flux:description>إذا حُدّد، يتوقف توليد الأيام عند هذا التاريخ حتى لو لم تكتمل المنظومة.</flux:description>
                    <flux:error name="endDate" />
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

            <div class="flex justify-end gap-2 pt-4">
                <flux:button variant="ghost" href="{{ $userRole === 'supervisor' ? route('supervisor.odes.paths') : route('teacher.ode-plans') }}">إلغاء</flux:button>
                <flux:button variant="primary" icon="arrow-path" wire:click="generatePreview">توليد الخطة ومعاينتها</flux:button>
            </div>
        </div>
    @else
        {{-- STEP 2: Preview & Confirmation Panel --}}
        <div class="bg-white dark:bg-zinc-900 rounded-2xl border border-zinc-100 dark:border-zinc-800 shadow-xs p-6 space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-zinc-100 dark:border-zinc-800">
                <div>
                    <div class="text-xs font-bold text-indigo-500 mb-1">الخطة النموذجية للمسار</div>
                    <flux:heading size="lg" class="font-bold text-zinc-900 dark:text-white">
                        مسار: {{ $paths->firstWhere('id', $odePathId)->name ?? '' }}
                    </flux:heading>
                    <flux:subheading>راجع التوزيع اليومي، ويمكنك التعديل اليدوي على نطاق الأبيات لكل يوم قبل الحفظ النهائي.</flux:subheading>
                </div>
                <div class="flex items-center gap-2">
                    <flux:button variant="ghost" icon="arrow-path" wire:click="resetPlan" class="text-rose-500 hover:bg-rose-50">إعادة ضبط وملء جديد</flux:button>
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
                                    <div class="flex flex-col">
                                        <span>اليوم {{ $index + 1 }}</span>
                                        <span class="text-xs font-normal text-zinc-500">{{ $day['day_name'] }}</span>
                                    </div>
                                </flux:table.cell>
                                <flux:table.cell class="text-center text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ $day['date'] }}
                                </flux:table.cell>
                                <flux:table.cell class="first:ps-3" >
                                    <div class="flex items-center justify-center gap-2 max-w-[250px] mx-auto">
                                        <flux:input type="number" size="sm" wire:model="planDays.{{ $index }}.from_verse_number" placeholder="من" class="text-center w-20" />
                                        <span class="text-zinc-400">-</span>
                                        <flux:input type="number" size="sm" wire:model="planDays.{{ $index }}.to_verse_number" placeholder="إلى" class="text-center w-20" />
                                    </div>
                                </flux:table.cell>
                                @if ($hasReview)
                                    <flux:table.cell class="first:ps-3" >
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
