<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">إدارة النماذج والاستمارات</h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">أنشئ النماذج المخصصة لجمع البيانات والتسجيل واستعراض الردود وتحويلها لطلاب.</p>
        </div>
        <flux:button as="a" :href="route('supervisor.forms.create')" variant="primary" icon="plus" class="bg-accent hover:bg-accent/90 text-white border-0">
            إنشاء نموذج جديد
        </flux:button>
    </div>

    <!-- Forms Grid -->
    @if($forms->isEmpty())
        <div class="flex flex-col items-center justify-center p-12 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 text-center">
            <div class="w-16 h-16 rounded-full bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center text-zinc-400 dark:text-zinc-500 mb-4">
                <flux:icon name="document-text" class="size-8" />
            </div>
            <h3 class="text-lg font-medium text-zinc-900 dark:text-white">لا توجد نماذج حالياً</h3>
            <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1 max-w-sm">ابدأ بإنشاء أول نموذج مخصص لك لتلقي الردود والتسجيل.</p>
            <flux:button as="a" :href="route('supervisor.forms.create')" variant="filled" class="mt-6">
                أنشئ نموذجك الأول
            </flux:button>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($forms as $form)
                <div class="flex flex-col bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs overflow-hidden group hover:border-zinc-300 dark:hover:border-zinc-700 transition-all">
                    <!-- Color Strip / Header Image -->
                    @if($form->header_image_path)
                        <div class="h-28 w-full bg-cover bg-center relative" style="background-image: url('{{ asset('storage/' . $form->header_image_path) }}')">
                            <div class="absolute inset-0 bg-black/30"></div>
                        </div>
                    @else
                        <div class="h-4 w-full" style="background-color: {{ $form->color }}"></div>
                    @endif

                    <div class="p-5 flex-1 flex flex-col justify-between space-y-4">
                        <!-- Info -->
                        <div>
                            <div class="flex items-start justify-between gap-2">
                                <h3 class="font-bold text-lg text-zinc-900 dark:text-white truncate" title="{{ $form->title }}">
                                    {{ $form->title }}
                                </h3>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400 shrink-0">
                                    {{ $form->responses_count }} رد
                                </span>
                            </div>
                            <div class="flex flex-wrap items-center gap-1.5 mt-2">
                                @if($form->is_supervisor_shared)
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-indigo-50 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300">
                                        <flux:icon name="users" class="size-3" /> عام للمشرفين
                                    </span>
                                @endif
                                @if($form->supervisor_id !== $currentSupervisorId)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                                        أنشأه: {{ $form->supervisor?->name ?? 'مشرف آخر' }}
                                    </span>
                                @endif
                            </div>
                            <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-2 line-clamp-2">
                                {{ $form->description ?: 'لا يوجد وصف للنموذج.' }}
                            </p>
                            
                            <!-- Slug Link -->
                            <div class="mt-3 flex items-center gap-1.5 text-xs text-zinc-400 dark:text-zinc-500">
                                <flux:icon name="link" class="size-3.5 shrink-0" />
                                <span class="truncate dir-ltr text-left select-all" id="link-text-{{ $form->id }}">
                                    {{ route('forms.submit', $form->slug) }}
                                </span>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="pt-4 border-t border-zinc-100 dark:border-zinc-800 flex items-center justify-between gap-2">
                            <div class="flex items-center gap-2">
                                <flux:button as="a" :href="route('supervisor.forms.responses', $form->id)" size="sm" variant="filled" icon="chat-bubble-left-right" class="text-xs">
                                    الردود
                                </flux:button>
                                
                                <flux:button as="a" :href="route('supervisor.forms.edit', $form->id)" size="sm" variant="ghost" icon="pencil-square" class="text-xs" />
                                
                                <!-- Copy Link Button -->
                                <flux:button size="sm" variant="ghost" icon="clipboard" class="text-xs" 
                                    x-data="{ copied: false }"
                                    x-on:click="
                                        navigator.clipboard.writeText('{{ route('forms.submit', $form->slug) }}');
                                        copied = true;
                                        setTimeout(() => copied = false, 2000);
                                        $dispatch('toast', { message: 'تم نسخ الرابط بنجاح', variant: 'success' })
                                    "
                                    ::title="copied ? 'تم النسخ!' : 'نسخ رابط النموذج'"
                                />

                                @if($form->is_public_report)
                                    <!-- Copy Public Report Link -->
                                    <flux:button size="sm" variant="ghost" icon="share" class="text-xs text-indigo-500 hover:text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-950/20" 
                                        x-data="{ copied: false }"
                                        x-on:click="
                                            navigator.clipboard.writeText('{{ route('forms.report', [$form->slug, $form->public_report_token]) }}');
                                            copied = true;
                                            setTimeout(() => copied = false, 2000);
                                            $dispatch('toast', { message: 'تم نسخ رابط التقرير العام بنجاح', variant: 'success' })
                                        "
                                        ::title="copied ? 'تم النسخ!' : 'نسخ رابط التقرير العام'"
                                    />
                                @endif
                            </div>

                            @if($form->supervisor_id === $currentSupervisorId)
                                <flux:button wire:click="delete({{ $form->id }})" wire:confirm="هل أنت متأكد من رغبتك في حذف هذا النموذج وجميع إجاباته؟ لا يمكن التراجع عن هذا الإجراء." size="sm" variant="ghost" icon="trash" class="text-rose-500 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/30 text-xs" />
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
