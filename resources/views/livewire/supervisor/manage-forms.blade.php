<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div class="p-2.5 rounded-xl bg-maroon/10 text-maroon dark:bg-white/10 dark:text-white">
                <flux:icon icon="document-text" />
            </div>
            <div>
                <flux:heading size="xl" class="font-bold text-zinc-900 dark:text-white">إدارة النماذج والاستمارات</flux:heading>
                <flux:subheading class="text-zinc-400">أنشئ النماذج المخصصة لجمع البيانات والتسجيل واستعراض الردود وتحويلها لطلاب</flux:subheading>
            </div>
        </div>
        <flux:button as="a" :href="route($currentRole.'.forms.create')" variant="primary" icon="plus" class="bg-accent hover:bg-accent/90 text-white border-0">
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
            <flux:button as="a" :href="route($currentRole.'.forms.create')" variant="filled" class="mt-6">
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
                                @if($form->is_public)
                                    <span @class([
                                        'inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold',
                                        'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300' => $form->isOpenToPublic(),
                                        'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300' => ! $form->isOpenToPublic(),
                                    ])>
                                        <flux:icon name="globe-alt" class="size-3" />
                                        {{ $form->isOpenToPublic() ? 'مفتوح للعامة' : 'مفتوح ورابطه معطّل' }}
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
                            <div class="mt-3 flex items-center gap-1.5 text-xs text-zinc-400 dark:text-zinc-500 min-w-0">
                                <flux:icon name="link" class="size-3.5 shrink-0" />
                                <span class="truncate dir-ltr text-left select-all min-w-0 flex-1" id="link-text-{{ $form->id }}">
                                    {{ route('forms.submit', $form->slug) }}
                                </span>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="pt-4 border-t border-zinc-100 dark:border-zinc-800 flex items-center justify-between gap-2">
                            <div class="flex items-center gap-2">
                                <flux:button as="a" :href="route($currentRole.'.forms.responses', $form->id)" size="sm" variant="filled" icon="chat-bubble-left-right" class="text-xs">
                                    الردود
                                </flux:button>
                                
                                <flux:button as="a" :href="route($currentRole.'.forms.edit', $form->id)" size="sm" variant="ghost" icon="pencil-square" class="text-xs" />
                                
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

                                @if(in_array($form->id, $changeableIds, true))
                                    <!-- Open to people with no account -->
                                    <flux:button wire:click="share({{ $form->id }})" size="sm" variant="ghost" icon="globe-alt"
                                        class="text-xs {{ $form->is_public ? 'text-emerald-600 hover:text-emerald-700 hover:bg-emerald-50 dark:hover:bg-emerald-950/20' : '' }}"
                                        title="رابطٌ عام لمن لا حساب له"
                                    />
                                @endif

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
    {{-- The link handed to people who have no account, and the dates that keep it honest --}}
    <flux:modal name="public-link" class="md:w-[580px]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">رابطٌ عام لمن لا حساب له</flux:heading>
                <flux:subheading>
                    يُجيب عليه وليّ الأمر دون تسجيل دخول، وتصل إجابته إلى شاشة الردود مع بقية الردود.
                </flux:subheading>
            </div>

            @if($sharing)
                <flux:switch
                    wire:model.live="isPublic"
                    label="افتحه للعامة"
                    description="أغلقه متى شئت؛ الرابط يبقى محفوظاً ويعمل إن فتحته ثانية."
                />

                @if($isPublic)
                    <div class="space-y-4">
                        <flux:input
                            type="date"
                            wire:model="closesOn"
                            label="يُغلق التسجيل في"
                            description="اتركه فارغاً ليبقى مفتوحاً بلا أجل."
                        />
                        <flux:textarea
                            wire:model="publicIntro"
                            label="تعريفٌ يظهر أعلى الاستمارة"
                            rows="3"
                            placeholder="سطران يعرّفان بالبرنامج لمن يفتح الرابط لأول مرة."
                        />
                    </div>

                    @if($sharing->public_token)
                        <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-800 dark:bg-zinc-900/60">
                            <div class="flex items-center gap-1.5 text-xs font-semibold text-zinc-500 dark:text-zinc-400">
                                <flux:icon name="globe-alt" class="size-3.5" /> الرابط
                            </div>
                            <div dir="ltr" class="mt-2 select-all break-all text-left font-mono text-xs text-zinc-700 dark:text-zinc-200">
                                {{ $sharing->publicUrl() }}
                            </div>
                            <div class="mt-3 flex items-center gap-2">
                                <flux:button size="sm" variant="filled" icon="clipboard" class="text-xs"
                                    x-data
                                    x-on:click="
                                        navigator.clipboard.writeText('{{ $sharing->publicUrl() }}');
                                        $dispatch('toast', { message: 'تم نسخ الرابط', variant: 'success' })
                                    "
                                >نسخ</flux:button>
                                <flux:button as="a" href="{{ $sharing->publicUrl() }}" target="_blank" size="sm" variant="ghost" icon="arrow-top-right-on-square" class="text-xs">
                                    افتحه
                                </flux:button>
                            </div>
                        </div>
                    @else
                        <flux:callout icon="check-circle" class="text-sm">
                            احفظ، ويُنشأ الرابط.
                        </flux:callout>
                    @endif

                    {{-- A link that is open but dead is the worst of the three states: the
                         father sees a page, answers, and nothing is kept. Say so plainly. --}}
                    @if($sharing->is_public && ! $sharing->isOpenToPublic())
                        <flux:callout variant="warning" icon="exclamation-triangle" class="text-sm">
                            @if(! $sharing->isPublished())
                                النموذج ما زال مسودّة، والرابط لا يفتح حتى تنشره من شاشة التعديل.
                            @else
                                مضى تاريخ الإغلاق ({{ $sharing->closes_on?->format('Y-m-d') }})، والرابط مغلقٌ الآن.
                            @endif
                        </flux:callout>
                    @endif
                @endif
            @endif

            <div class="flex gap-2 justify-end">
                <flux:modal.close>
                    <flux:button variant="ghost">إغلاق</flux:button>
                </flux:modal.close>
                <flux:button wire:click="saveSharing" variant="primary">حفظ</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
