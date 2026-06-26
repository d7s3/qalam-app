<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <div class="p-2 rounded-lg bg-amber-50 text-amber-500 dark:bg-amber-900/30 dark:text-amber-400">
                <flux:icon icon="trophy" class="size-6" />
            </div>
            <div>
                <flux:heading size="xl" class="font-bold text-zinc-900 dark:text-white">مسابقات المرحلة</flux:heading>
                <flux:subheading>أنشئ مسابقات تشمل حلقات متعددة وتحفّز الطلاب على التنافس</flux:subheading>
            </div>
        </div>
        <flux:button wire:click="create" variant="primary" icon="plus"
            class="bg-amber-500 hover:bg-amber-600 border-none text-amber-950 shadow-md shadow-amber-500/20">
            إنشاء مسابقة جديدة
        </flux:button>
    </div>

    {{-- Grid --}}
    @if (count($competitions) > 0)
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach ($competitions as $competition)
                <flux:card
                    class="relative overflow-hidden group hover:shadow-lg transition-shadow duration-300 border border-zinc-200 dark:border-zinc-700/50">

                    {{-- Supervisor Badge --}}
                    <div class="absolute top-3 left-3">
                        <flux:badge size="sm" color="indigo" icon="shield-check">مسابقة المشرف</flux:badge>
                    </div>

                    {{-- Actions Menu --}}
                    <div class="absolute top-2 right-2">
                        <flux:dropdown>
                            <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal"
                                class="text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200" />
                            <flux:menu>
                                <flux:menu.item wire:click="edit({{ $competition->id }})" icon="pencil-square">
                                    تعديل
                                </flux:menu.item>
                                <flux:menu.separator />
                                <flux:menu.item wire:click="delete({{ $competition->id }})"
                                    wire:confirm="هل أنت متأكد من حذف هذه المسابقة؟" icon="trash"
                                    class="text-rose-500 hover:text-rose-600">
                                    حذف
                                </flux:menu.item>
                            </flux:menu>
                        </flux:dropdown>
                    </div>

                    <div class="mt-6 mb-4">
                        <div class="flex flex-wrap items-center gap-1.5 mb-2">
                            <flux:badge color="{{ $competition->is_active ? 'amber' : 'zinc' }}" size="sm">
                                {{ $competition->is_active ? 'نشطة' : 'مغلقة' }}
                            </flux:badge>
                            @if (($competition->competition_type ?? 'normal') === 'gamification')
                                @php
                                    $themeName = $competition->settings['theme']['name'] ?? 'طابع مخصص';
                                    $themeEmoji = $competition->settings['theme']['team_emoji'] ?? '🎮';
                                    $isImage = str_contains($themeEmoji, '/') || str_ends_with($themeEmoji, '.webp');
                                @endphp
                                <flux:badge color="purple" icon="sparkles" size="sm">
                                    {{ __('تلعيب:') }} {{ $themeName }}
                                    @if($isImage)
                                        <img src="{{ Storage::url($themeEmoji) }}" class="size-4 inline-block object-contain rounded ms-1 align-middle" />
                                    @else
                                        {{ $themeEmoji }}
                                    @endif
                                </flux:badge>
                            @endif
                        </div>
                        <flux:heading size="lg" class="mb-1 text-zinc-800 dark:text-zinc-100 leading-snug">
                            {{ $competition->title }}
                        </flux:heading>
                        <div class="text-sm text-zinc-500 flex items-center gap-2">
                            <flux:icon icon="calendar" class="size-4" />
                            <span>{{ $competition->start_date->format('Y-m-d') }}</span>
                            @if($competition->end_date)
                                <span>-</span>
                                <span>{{ $competition->end_date->format('Y-m-d') }}</span>
                            @endif
                        </div>
                    </div>

                    {{-- Circles --}}
                    <div class="mb-4">
                        <div class="text-xs text-zinc-500 mb-1.5 font-medium">الحلقات المشاركة</div>
                        <div class="flex flex-wrap gap-1">
                            @forelse($competition->circles as $circle)
                                <flux:badge size="sm" variant="neutral">{{ $circle->name }}</flux:badge>
                            @empty
                                <span class="text-xs text-zinc-400">لم تُحدَّد حلقات</span>
                            @endforelse
                        </div>
                    </div>

                    {{-- Stats --}}
                    <div class="bg-zinc-50 dark:bg-zinc-800/50 rounded-lg p-3 text-sm mb-4">
                        <div class="flex justify-between items-center text-zinc-600 dark:text-zinc-400">
                            <span class="flex items-center gap-2">
                                <flux:icon icon="star" class="size-4 text-emerald-500" />
                                بنود التقييم المخصصة
                            </span>
                            <span class="font-bold text-zinc-900 dark:text-zinc-100">{{ $competition->criteria_count }}</span>
                        </div>
                    </div>

                    {{-- Actions --}}
                    <div class="grid grid-cols-2 gap-2 mt-4">
                        <flux:button wire:click="toggleActive({{ $competition->id }})" variant="ghost" size="sm"
                            class="w-full border border-zinc-200 dark:border-zinc-700">
                            @if ($competition->is_active)
                                <flux:icon icon="pause-circle" class="size-4 ml-1" /> إيقاف
                            @else
                                <flux:icon icon="play-circle" class="size-4 ml-1 text-emerald-500" /> تنشيط
                            @endif
                        </flux:button>
                        
                        <flux:button wire:click="toggleActiveForGrading({{ $competition->id }})" variant="ghost" size="sm"
                            class="w-full border {{ $competition->is_active_for_grading ? 'bg-amber-50 dark:bg-amber-900/20 text-amber-600 border-amber-300 dark:border-amber-700' : 'border-zinc-200 dark:border-zinc-700' }}">
                            @if ($competition->is_active_for_grading)
                                <flux:icon icon="star" variant="solid" class="size-4 ml-1 text-amber-500" /> أساسية للتسجيل
                            @else
                                <flux:icon icon="star" variant="outline" class="size-4 ml-1 text-zinc-400" /> تعيين للتسجيل
                            @endif
                        </flux:button>
                    </div>

                    @if (($competition->competition_type ?? 'normal') === 'gamification')
                        <div class="mt-2">
                            <flux:button href="{{ route('supervisor.competitions.gamification', $competition->id) }}" variant="primary" size="sm" class="w-full bg-purple-600 hover:bg-purple-700 border-none text-white shadow-sm shadow-purple-500/20" icon="cog-6-tooth" wire:navigate>
                                إدارة نظام التلعيب ⚙️
                            </flux:button>
                        </div>
                    @endif
                </flux:card>
            @endforeach
        </div>
    @else
        <div
            class="text-center py-20 bg-zinc-50 dark:bg-zinc-800/20 rounded-xl border border-dashed border-zinc-200 dark:border-zinc-700">
            <div
                class="bg-amber-100 dark:bg-amber-900/30 text-amber-500 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
                <flux:icon icon="trophy" class="size-8" />
            </div>
            <flux:heading size="lg" class="mb-2">لا توجد مسابقات حالية</flux:heading>
            <p class="text-zinc-500 mb-6 max-w-md mx-auto">أنشئ مسابقة تشمل حلقات متعددة لتحفيز الطلاب على التنافس عبر
                المرحلة.</p>
            <flux:button wire:click="create" variant="primary" icon="plus"
                class="bg-amber-500 hover:bg-amber-600 border-none text-white shadow-md shadow-amber-500/20">
                إنشاء أول مسابقة
            </flux:button>
        </div>
    @endif

    {{-- Create / Edit Modal --}}
    <flux:modal wire:model="showModal" class="md:w-[800px] w-full">
        @if ($isEditing)
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">تعديل المسابقة</flux:heading>
                    <flux:subheading>حدد اسم المسابقة والحلقات المشاركة وإعدادات النقاط.</flux:subheading>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    {{-- Basic Info --}}
                    <div class="space-y-4">
                        <flux:input wire:model="title" label="اسم المسابقة" placeholder="مثال: نجوم التحفيظ لشهر شوال"
                            required />

                        <div class="grid grid-cols-2 gap-4">
                            <livewire:shared.hijri-datepicker wire:model="start_date" label="تاريخ البداية" />
                            <livewire:shared.hijri-datepicker wire:model="end_date" label="تاريخ النهاية" />
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <flux:select label="نوع المسابقة" wire:model.live="competition_type">
                                <flux:select.option value="normal">مسابقة تقليدية</flux:select.option>
                                <flux:select.option value="gamification">مسابقة تلعيب (Gamification)</flux:select.option>
                            </flux:select>

                            <!-- custom theme settings will be managed in the right column -->
                        </div>

                        <flux:switch wire:model="is_active" label="المسابقة نشطة"
                            description="المسابقات النشطة تظهر بأولوية في صفحة الطلاب" />

                        {{-- Circles selection --}}
                        <div>
                            <flux:heading size="sm" class="mb-2">الحلقات المشاركة</flux:heading>
                            @error('selectedCircles')
                                <div class="text-sm text-red-500 mb-2">{{ $message }}</div>
                            @enderror
                            <div
                                class="grid grid-cols-2 gap-2 max-h-40 overflow-y-auto p-2 border border-zinc-200 dark:border-zinc-700 rounded-lg">
                                @forelse($circlesList as $circle)
                                    <label
                                        class="flex items-start gap-2 cursor-pointer p-1.5 rounded hover:bg-zinc-50 dark:hover:bg-zinc-800">
                                        <flux:checkbox wire:model="selectedCircles" :value="$circle->id"
                                            :id="'circle-'.$circle->id" />
                                        <div>
                                            <div class="text-sm font-medium">{{ $circle->name }}</div>
                                            <div class="text-xs text-zinc-400">{{ $circle->stage->name }}</div>
                                        </div>
                                    </label>
                                @empty
                                    <span class="text-xs text-zinc-400 col-span-2 text-center py-2">لا توجد حلقات متاحة</span>
                                @endforelse
                            </div>
                        </div>
                    </div>

                    {{-- Custom Criteria --}}
                    @if ($competition_type !== 'gamification')
                        <div
                            class="space-y-4 p-4 bg-zinc-50 dark:bg-zinc-800/40 rounded-xl border border-zinc-100 dark:border-zinc-700/50 h-[300px] overflow-y-auto">
                            <div class="flex items-center justify-between mb-2">
                                <flux:heading size="sm">بنود التقييم اليدوية</flux:heading>
                                <flux:button wire:click="addCriterion" size="xs" variant="ghost" icon="plus"
                                    class="text-emerald-600 bg-emerald-50 hover:bg-emerald-100 dark:text-emerald-400 dark:bg-emerald-900/30">
                                    إضافة بند
                                </flux:button>
                            </div>

                            @foreach ($criteria as $index => $criterion)
                                <div class="flex items-center gap-2" wire:key="criterion-{{ $index }}">
                                    <flux:input wire:model="criteria.{{ $index }}.name" class="flex-2"
                                        placeholder="اسم البند مثال: الهدوء" />
                                    <flux:input type="number" wire:model="criteria.{{ $index }}.points" class="flex-1"
                                        placeholder="نقاط" />
                                    <flux:button wire:click="removeCriterion({{ $index }})" variant="ghost" icon="trash"
                                        class="text-rose-500 shrink-0" />
                                </div>
                            @endforeach

                            @if (count($criteria) === 0)
                                <div class="text-center py-6 text-sm text-zinc-400">لم تقم بإضافة بنود يدوية بعد.</div>
                            @endif
                        </div>
                    @else
                        <!-- Custom Theme Settings for Gamification -->
                        <div class="space-y-4 p-4 bg-purple-50/10 dark:bg-purple-950/5 rounded-xl border border-purple-100/60 dark:border-purple-900/30">
                            <div class="font-bold text-sm text-purple-700 dark:text-purple-400 flex items-center gap-1.5">
                                <flux:icon icon="paint-brush" class="size-4" />
                                إعدادات طابع التلعيب المخصص
                            </div>
                            
                            <div class="grid grid-cols-2 gap-4">
                                <flux:input label="اسم الطابع" wire:model="custom_theme_name" placeholder="مثال: فرسان القرآن" />
                                <flux:select label="اللون الرئيسي" wire:model="custom_theme_color">
                                    <flux:select.option value="#4f46e5">بنفسجي ملكي</flux:select.option>
                                    <flux:select.option value="#10b981">أخضر زمردي</flux:select.option>
                                    <flux:select.option value="#f59e0b">أصفر ذهبي</flux:select.option>
                                    <flux:select.option value="#ef4444">أحمر ناري</flux:select.option>
                                    <flux:select.option value="#06b6d4">أزرق سماوي</flux:select.option>
                                </flux:select>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <flux:input label="اسم العملة" wire:model="custom_theme_currency" placeholder="مثال: جوهرة" />
                                <div class="space-y-1">
                                    <label class="text-xs font-bold text-zinc-700 dark:text-zinc-350 block">صورة رمز العملة</label>
                                    <input type="file" wire:model="coin_image_file" class="w-full text-xs text-zinc-500 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-zinc-100 file:text-zinc-700 hover:file:bg-zinc-200 cursor-pointer" />
                                    @if(str_contains($custom_theme_currency_emoji, '/') || str_ends_with($custom_theme_currency_emoji, '.webp'))
                                        <div class="text-[11px] text-zinc-400 flex items-center gap-1.5 mt-1">
                                            <span>الصورة الحالية:</span>
                                            <img src="{{ Storage::url($custom_theme_currency_emoji) }}" class="size-6 object-contain rounded border border-zinc-200 bg-white" />
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <div>
                                <flux:input label="اسم نقاط الخبرة" wire:model="custom_theme_xp" placeholder="مثال: طاقة الخبرة" />
                            </div>

                            <div class="grid grid-cols-3 gap-2">
                                <flux:input label="المجموعة (مفرد)" wire:model="custom_theme_team" placeholder="مثال: كتيبة" />
                                <flux:input label="المجموعة (جمع)" wire:model="custom_theme_team_plural" placeholder="مثال: كتائب" />
                                <div class="space-y-1">
                                    <label class="text-xs font-bold text-zinc-700 dark:text-zinc-350 block">صورة رمز المجموعة</label>
                                    <input type="file" wire:model="team_image_file" class="w-full text-xs text-zinc-500 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-zinc-100 file:text-zinc-700 hover:file:bg-zinc-200 cursor-pointer" />
                                    @if(str_contains($custom_theme_team_emoji, '/') || str_ends_with($custom_theme_team_emoji, '.webp'))
                                        <div class="text-[11px] text-zinc-400 flex items-center gap-1.5 mt-1">
                                            <span>الصورة الحالية:</span>
                                            <img src="{{ Storage::url($custom_theme_team_emoji) }}" class="size-6 object-contain rounded border border-zinc-200 bg-white" />
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <flux:input label="صيغة المخاطب (مجموعتك)" wire:model="custom_theme_team_possessive_your" placeholder="مثال: كتيبتك" />
                                <flux:input label="صيغة المتكلم (مجموعتي)" wire:model="custom_theme_team_possessive_my" placeholder="مثال: كتيبتي" />
                            </div>
                        </div>
                    @endif
                </div>

                <flux:separator />

                {{-- Automated Settings --}}
                @if ($competition_type !== 'gamification')
                <div>
                    <flux:heading size="md" class="mb-4 flex items-center gap-2">
                        <flux:icon icon="cog-6-tooth" class="size-5 text-indigo-500" />
                        إعدادات النقاط التلقائية
                    </flux:heading>
 
                    {{-- Quran + Attendance --}}
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 space-y-3">
                            <flux:switch wire:model="hifz_enabled" label="نقاط حفظ القرآن" />
                            <div x-show="$wire.hifz_enabled"
                                class="space-y-2 mt-2 pt-2 border-t border-zinc-100 dark:border-zinc-800">
                                <flux:input type="number" size="sm" wire:model="hifz_excellent" label="تقييم ممتاز" />
                                <flux:input type="number" size="sm" wire:model="hifz_good" label="تقييم جيد" />
                                <flux:input type="number" size="sm" wire:model="hifz_acceptable" label="تقييم مقبول" />
                            </div>
                        </div>
                        <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 space-y-3">
                            <flux:switch wire:model="review_enabled" label="نقاط مراجعة القرآن" />
                            <div x-show="$wire.review_enabled"
                                class="space-y-2 mt-2 pt-2 border-t border-zinc-100 dark:border-zinc-800">
                                <flux:input type="number" size="sm" wire:model="review_excellent" label="تقييم ممتاز" />
                                <flux:input type="number" size="sm" wire:model="review_good" label="تقييم جيد وجيد جدا" />
                            </div>
                        </div>
                        <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 space-y-3">
                            <flux:switch wire:model="attendance_enabled" label="نقاط الحضور" />
                            <div x-show="$wire.attendance_enabled"
                                class="space-y-2 mt-2 pt-2 border-t border-zinc-100 dark:border-zinc-800">
                                <flux:input type="number" size="sm" wire:model="attendance_present" label="حاضر بوقت" />
                                <flux:input type="number" size="sm" wire:model="attendance_late" label="حاضر متأخر" />
                            </div>
                        </div>
                    </div>

                    {{-- Ode + Hadith --}}
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mt-4">
                        <div class="p-4 rounded-xl border border-violet-200 dark:border-violet-900/50 bg-violet-50/30 dark:bg-violet-950/10 space-y-3">
                            <flux:switch wire:model="ode_hifz_enabled" label="نقاط حفظ المنظومة" />
                            <div x-show="$wire.ode_hifz_enabled"
                                class="space-y-2 mt-2 pt-2 border-t border-violet-100 dark:border-violet-900/30">
                                <flux:input type="number" size="sm" wire:model="ode_hifz_excellent" label="تقييم ممتاز" />
                                <flux:input type="number" size="sm" wire:model="ode_hifz_good" label="تقييم جيد" />
                                <flux:input type="number" size="sm" wire:model="ode_hifz_acceptable" label="تقييم مقبول" />
                            </div>
                        </div>
                        <div class="p-4 rounded-xl border border-violet-200 dark:border-violet-900/50 bg-violet-50/30 dark:bg-violet-950/10 space-y-3">
                            <flux:switch wire:model="ode_review_enabled" label="نقاط مراجعة المنظومة" />
                            <div x-show="$wire.ode_review_enabled"
                                class="space-y-2 mt-2 pt-2 border-t border-violet-100 dark:border-violet-900/30">
                                <flux:input type="number" size="sm" wire:model="ode_review_excellent" label="تقييم ممتاز" />
                                <flux:input type="number" size="sm" wire:model="ode_review_good" label="تقييم جيد وجيد جدا" />
                            </div>
                        </div>
                        <div class="p-4 rounded-xl border border-rose-200 dark:border-rose-900/50 bg-rose-50/30 dark:bg-rose-950/10 space-y-3">
                            <flux:switch wire:model="hadith_hifz_enabled" label="نقاط حفظ المتون" />
                            <div x-show="$wire.hadith_hifz_enabled"
                                class="space-y-2 mt-2 pt-2 border-t border-rose-100 dark:border-rose-900/30">
                                <flux:input type="number" size="sm" wire:model="hadith_hifz_excellent" label="تقييم ممتاز" />
                                <flux:input type="number" size="sm" wire:model="hadith_hifz_good" label="تقييم جيد" />
                                <flux:input type="number" size="sm" wire:model="hadith_hifz_acceptable" label="تقييم مقبول" />
                            </div>
                        </div>
                        <div class="p-4 rounded-xl border border-rose-200 dark:border-rose-900/50 bg-rose-50/30 dark:bg-rose-950/10 space-y-3">
                            <flux:switch wire:model="hadith_review_enabled" label="نقاط مراجعة المتون" />
                            <div x-show="$wire.hadith_review_enabled"
                                class="space-y-2 mt-2 pt-2 border-t border-rose-100 dark:border-rose-900/30">
                                <flux:input type="number" size="sm" wire:model="hadith_review_excellent" label="تقييم ممتاز" />
                                <flux:input type="number" size="sm" wire:model="hadith_review_good" label="تقييم جيد وجيد جدا" />
                            </div>
                        </div>
                    </div>
                </div>
                @endif

                <div class="flex justify-end gap-2 mt-4">
                    <flux:button wire:click="$set('showModal', false)" variant="ghost">إلغاء</flux:button>
                    <flux:button wire:click="save" variant="primary"
                        class="bg-amber-500 hover:bg-amber-600 border-none text-white">
                        حفظ التعديلات
                    </flux:button>
                </div>
            </div>
        @else
            {{-- Wizard Step Creation Flow --}}
            <div class="space-y-6">
                {{-- Wizard Header --}}
                <div class="flex items-center justify-between pb-4 border-b border-zinc-100 dark:border-zinc-800">
                    <div class="flex items-center gap-2">
                        <span class="w-7 h-7 rounded-full bg-amber-500 text-amber-950 flex items-center justify-center text-sm font-bold shadow-sm shadow-amber-500/20">
                            {{ $currentStep }}
                        </span>
                        <div>
                            <flux:heading size="lg" class="font-bold">
                                @if ($currentStep === 1) تحديد نوع المسابقة وطابعها @endif
                                @if ($currentStep === 2) تحديد اسم المسابقة @endif
                                @if ($currentStep === 3) تحديد الفترة الزمنية @endif
                                @if ($currentStep === 4) تحديد الحلقات المشاركة @endif
                            </flux:heading>
                        </div>
                    </div>
                    <span class="text-xs text-zinc-400">الخطوة {{ $currentStep }} من 4</span>
                </div>

                {{-- Wizard Progress Bar --}}
                <div class="mb-2">
                    <div class="w-full bg-zinc-100 dark:bg-zinc-800 h-1.5 rounded-full overflow-hidden">
                        <div class="bg-gradient-to-r from-amber-400 to-amber-500 h-full rounded-full transition-all duration-300" style="width: {{ ($currentStep / 4) * 100 }}%"></div>
                    </div>
                </div>

                {{-- Steps Container --}}
                <div class="min-h-[220px]">
                    @if ($currentStep === 1)
                        <div class="space-y-4 py-2">
                            <flux:heading size="md">اختر نوع المسابقة التي ترغب بإنشائها:</flux:heading>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <!-- Normal Card -->
                                <div wire:click="$set('competition_type', 'normal')"
                                    class="group p-5 rounded-xl border-2 cursor-pointer transition-all duration-300 {{ $competition_type === 'normal' ? 'border-amber-500 bg-amber-50/20 dark:bg-amber-950/10 shadow-sm' : 'border-zinc-200 dark:border-zinc-800 hover:border-zinc-300 dark:hover:border-zinc-700' }}">
                                    <div class="flex items-center gap-3 mb-2">
                                        <div class="p-2 rounded-lg bg-zinc-100 dark:bg-zinc-800 text-zinc-500 group-hover:bg-amber-100 dark:group-hover:bg-amber-950 group-hover:text-amber-500 transition-colors">
                                            <flux:icon icon="trophy" class="size-6" />
                                        </div>
                                        <div class="font-bold text-zinc-800 dark:text-zinc-100">مسابقة تقليدية</div>
                                    </div>
                                    <p class="text-xs text-zinc-500 dark:text-zinc-400">مسابقة قائمة على رصد النقاط والترتيب التقليدي للطلاب.</p>
                                </div>
                                <!-- Gamification Card -->
                                <div wire:click="$set('competition_type', 'gamification')"
                                    class="group p-5 rounded-xl border-2 cursor-pointer transition-all duration-300 {{ $competition_type === 'gamification' ? 'border-amber-500 bg-amber-50/20 dark:bg-amber-950/10 shadow-sm' : 'border-zinc-200 dark:border-zinc-800 hover:border-zinc-300 dark:hover:border-zinc-700' }}">
                                    <div class="flex items-center gap-3 mb-2">
                                        <div class="p-2 rounded-lg bg-zinc-100 dark:bg-zinc-800 text-zinc-500 group-hover:bg-amber-100 dark:group-hover:bg-amber-950 group-hover:text-amber-500 transition-colors">
                                            <flux:icon icon="sparkles" class="size-6" />
                                        </div>
                                        <div class="font-bold text-zinc-800 dark:text-zinc-100">مسابقة تلعيب (Gamification)</div>
                                    </div>
                                    <p class="text-xs text-zinc-500 dark:text-zinc-400">مسابقة غامرة تحتوي على مستويات، شعلة الحماسة، فرق، متجر وأوسمة.</p>
                                </div>
                            </div>

                            @if ($competition_type === 'gamification')
                                <div class="mt-6 space-y-4 p-4 bg-purple-50/10 dark:bg-purple-950/5 rounded-xl border border-purple-100/60 dark:border-purple-900/30">
                                    <div class="font-bold text-sm text-purple-700 dark:text-purple-400 flex items-center gap-1.5">
                                        <flux:icon icon="paint-brush" class="size-4" />
                                        إعدادات طابع التلعيب المخصص
                                    </div>
                                    
                                    <div class="grid grid-cols-2 gap-4">
                                        <flux:input label="اسم الطابع" wire:model="custom_theme_name" placeholder="مثال: فرسان القرآن" />
                                        <flux:select label="اللون الرئيسي" wire:model="custom_theme_color">
                                            <flux:select.option value="#4f46e5">بنفسجي ملكي</flux:select.option>
                                            <flux:select.option value="#10b981">أخضر زمردي</flux:select.option>
                                            <flux:select.option value="#f59e0b">أصفر ذهبي</flux:select.option>
                                            <flux:select.option value="#ef4444">أحمر ناري</flux:select.option>
                                            <flux:select.option value="#06b6d4">أزرق سماوي</flux:select.option>
                                        </flux:select>
                                    </div>

                                    <div class="grid grid-cols-2 gap-4">
                                        <flux:input label="اسم العملة" wire:model="custom_theme_currency" placeholder="مثال: جوهرة" />
                                        <div class="space-y-1">
                                            <label class="text-xs font-bold text-zinc-700 dark:text-zinc-350 block">صورة رمز العملة</label>
                                            <input type="file" wire:model="coin_image_file" class="w-full text-xs text-zinc-500 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-zinc-100 file:text-zinc-700 hover:file:bg-zinc-200 cursor-pointer" />
                                            @if(str_contains($custom_theme_currency_emoji, '/') || str_ends_with($custom_theme_currency_emoji, '.webp'))
                                                <div class="text-[11px] text-zinc-400 flex items-center gap-1.5 mt-1">
                                                    <span>الصورة الحالية:</span>
                                                    <img src="{{ Storage::url($custom_theme_currency_emoji) }}" class="size-6 object-contain rounded border border-zinc-200 bg-white" />
                                                </div>
                                            @endif
                                        </div>
                                    </div>

                                    <div>
                                        <flux:input label="اسم نقاط الخبرة" wire:model="custom_theme_xp" placeholder="مثال: طاقة الخبرة" />
                                    </div>

                                    <div class="grid grid-cols-3 gap-2">
                                        <flux:input label="المجموعة (مفرد)" wire:model="custom_theme_team" placeholder="مثال: كتيبة" />
                                        <flux:input label="المجموعة (جمع)" wire:model="custom_theme_team_plural" placeholder="مثال: كتائب" />
                                        <div class="space-y-1">
                                            <label class="text-xs font-bold text-zinc-700 dark:text-zinc-350 block">صورة رمز المجموعة</label>
                                            <input type="file" wire:model="team_image_file" class="w-full text-xs text-zinc-500 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-zinc-100 file:text-zinc-700 hover:file:bg-zinc-200 cursor-pointer" />
                                            @if(str_contains($custom_theme_team_emoji, '/') || str_ends_with($custom_theme_team_emoji, '.webp'))
                                                <div class="text-[11px] text-zinc-400 flex items-center gap-1.5 mt-1">
                                                    <span>الصورة الحالية:</span>
                                                    <img src="{{ Storage::url($custom_theme_team_emoji) }}" class="size-6 object-contain rounded border border-zinc-200 bg-white" />
                                                </div>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-2 gap-4">
                                        <flux:input label="صيغة المخاطب (مجموعتك)" wire:model="custom_theme_team_possessive_your" placeholder="مثال: كتيبتك" />
                                        <flux:input label="صيغة المتكلم (مجموعتي)" wire:model="custom_theme_team_possessive_my" placeholder="مثال: كتيبتي" />
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endif

                    @if ($currentStep === 2)
                        <div class="space-y-4 py-4">
                            <flux:input wire:model="title" label="اسم المسابقة" placeholder="مثال: فرسان الحفظ لشهر ذي الحجة" required />
                            @error('title') <div class="text-xs text-rose-500 font-semibold">{{ $message }}</div> @enderror
                        </div>
                    @endif

                    @if ($currentStep === 3)
                        <div class="space-y-4 py-2">
                            <flux:heading size="md" class="mb-2">حدد تاريخ بدء وانتهاء المسابقة:</flux:heading>
                            <div class="grid grid-cols-2 gap-4">
                                <livewire:shared.hijri-datepicker wire:model="start_date" label="تاريخ البداية" />
                                <livewire:shared.hijri-datepicker wire:model="end_date" label="تاريخ النهاية" />
                            </div>
                            @error('start_date') <div class="text-xs text-rose-500 font-semibold">{{ $message }}</div> @enderror
                            @error('end_date') <div class="text-xs text-rose-500 font-semibold">{{ $message }}</div> @enderror
                        </div>
                    @endif

                    @if ($currentStep === 4)
                        <div class="space-y-4 py-2">
                            <flux:heading size="md">اختر الحلقات المشاركة في المسابقة:</flux:heading>
                            @error('selectedCircles')
                                <div class="text-xs text-rose-500 font-semibold mb-2">{{ $message }}</div>
                            @enderror
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-60 overflow-y-auto p-2 border border-zinc-200 dark:border-zinc-700 rounded-lg bg-zinc-50/50 dark:bg-zinc-900/20">
                                @forelse($circlesList as $circle)
                                    <label class="flex items-start gap-2.5 cursor-pointer p-2 rounded hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors">
                                        <flux:checkbox wire:model="selectedCircles" :value="$circle->id" :id="'circle-'.$circle->id" />
                                        <div>
                                            <div class="text-sm font-semibold text-zinc-800 dark:text-zinc-200">{{ $circle->name }}</div>
                                            <div class="text-xs text-zinc-400">{{ $circle->stage->name }}</div>
                                        </div>
                                    </label>
                                @empty
                                    <span class="text-xs text-zinc-400 col-span-2 text-center py-4">لا توجد حلقات متاحة</span>
                                @endforelse
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Wizard Footer --}}
                <div class="flex justify-between items-center pt-4 border-t border-zinc-100 dark:border-zinc-800">
                    <div>
                        @if ($currentStep > 1)
                            <flux:button wire:click="prevStep" variant="ghost" icon="chevron-right" class="font-bold">السابق</flux:button>
                        @endif
                    </div>
                    <div class="flex gap-2">
                        <flux:button wire:click="$set('showModal', false)" variant="ghost">إلغاء</flux:button>
                        @if ($currentStep < 4)
                            <flux:button wire:click="nextStep" variant="primary" class="bg-amber-500 hover:bg-amber-600 border-none text-white font-bold">التالي</flux:button>
                        @else
                            <flux:button wire:click="save" variant="primary" class="bg-amber-500 hover:bg-amber-600 border-none text-white font-bold">إنشاء المسابقة</flux:button>
                        @endif
                    </div>
                </div>
            </div>
        @endif
    </flux:modal>
</div>