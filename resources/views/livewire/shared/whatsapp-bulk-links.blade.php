<div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-6 shadow-sm space-y-5">
    <div>
        <flux:heading size="lg">{{ __('إرسال روابط الدخول عبر الواتساب') }}</flux:heading>
        <flux:subheading>
            {{ __('أرسل روابط الدخول السحرية دفعة واحدة لجميع الطلاب المشاركين وأولياء أمورهم. قبل الإرسال يظهر لك تقرير كامل بمن تنطبق عليه الشروط ومن لا تنطبق عليه مع السبب.') }}
        </flux:subheading>
    </div>

    <div class="flex flex-col md:flex-row gap-3 md:items-end">
        <div class="grow">
            <flux:select wire:model.live="sendType" :label="__('نوع الإرسال')">
                @foreach($sendTypes as $value => $label)
                    <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
        <flux:button variant="primary" icon="document-magnifying-glass" wire:click="previewReport">
            {{ __('عرض التقرير قبل الإرسال') }}
        </flux:button>
    </div>

    @if($report)
        {{-- Summary --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <div class="p-4 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20">
                <div class="text-2xl font-bold text-emerald-700 dark:text-emerald-400">{{ count($report['qualified']) }}</div>
                <div class="text-xs text-emerald-700/80 dark:text-emerald-400/80 mt-1">{{ __('طالباً تنطبق عليهم الشروط') }}</div>
            </div>
            <div class="p-4 rounded-xl bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/20">
                <div class="text-2xl font-bold text-red-700 dark:text-red-400">{{ count($report['unqualified']) }}</div>
                <div class="text-xs text-red-700/80 dark:text-red-400/80 mt-1">{{ __('لا تنطبق عليهم الشروط') }}</div>
            </div>
            <div class="p-4 rounded-xl bg-blue-50 dark:bg-blue-500/10 border border-blue-200 dark:border-blue-500/20">
                <div class="text-2xl font-bold text-blue-700 dark:text-blue-400">{{ $report['messages_count'] }}</div>
                <div class="text-xs text-blue-700/80 dark:text-blue-400/80 mt-1">
                    {{ __('رسالة ستُرسل') }}
                    @if($sendType === 'guardian_link_to_guardian')
                        <span class="block mt-0.5">{{ __('(ولي الأمر المتكرر تصله رسالة واحدة)') }}</span>
                    @endif
                </div>
            </div>
        </div>

        {{-- Qualified --}}
        @if(count($report['qualified']))
            <div class="border border-zinc-200 dark:border-zinc-700/50 rounded-xl overflow-hidden">
                <div class="px-4 py-3 bg-emerald-50 dark:bg-emerald-500/10 border-b border-zinc-200 dark:border-zinc-700/50 font-medium text-sm text-emerald-800 dark:text-emerald-300">
                    {{ __('سيتم الإرسال لهؤلاء') }}
                </div>
                <div class="max-h-64 overflow-y-auto divide-y divide-zinc-100 dark:divide-zinc-800">
                    @foreach($report['qualified'] as $row)
                        <div class="flex items-center justify-between gap-3 px-4 py-2.5 text-sm" wire:key="q-{{ $row['student']->id }}">
                            <div class="min-w-0">
                                <span class="font-medium text-zinc-800 dark:text-white">{{ $row['student']->name }}</span>
                                <span class="text-xs text-zinc-400 mr-2">{{ $row['student']->circle?->name }}</span>
                            </div>
                            <div class="text-xs text-zinc-500 shrink-0 text-left">
                                <div>{{ $row['recipient'] }}</div>
                                <div dir="ltr">{{ $row['phone'] }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Unqualified with reasons --}}
        @if(count($report['unqualified']))
            <div class="border border-zinc-200 dark:border-zinc-700/50 rounded-xl overflow-hidden">
                <div class="px-4 py-3 bg-red-50 dark:bg-red-500/10 border-b border-zinc-200 dark:border-zinc-700/50 font-medium text-sm text-red-800 dark:text-red-300">
                    {{ __('لن يصلهم شيء — مع السبب') }}
                </div>
                <div class="max-h-64 overflow-y-auto divide-y divide-zinc-100 dark:divide-zinc-800">
                    @foreach($report['unqualified'] as $row)
                        <div class="flex items-center justify-between gap-3 px-4 py-2.5 text-sm" wire:key="u-{{ $row['student']->id }}">
                            <div class="min-w-0">
                                <span class="font-medium text-zinc-800 dark:text-white">{{ $row['student']->name }}</span>
                                <span class="text-xs text-zinc-400 mr-2">{{ $row['student']->circle?->name }}</span>
                            </div>
                            <div class="flex flex-wrap gap-1 justify-end shrink-0">
                                @foreach($row['reasons'] as $reason)
                                    <flux:badge color="red" size="sm">{{ $reason }}</flux:badge>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="flex items-center justify-end gap-2">
            <flux:button variant="ghost" wire:click="cancelReport">{{ __('إلغاء') }}</flux:button>
            <flux:button variant="primary" icon="paper-airplane"
                wire:click="send"
                wire:confirm="{{ __('تأكيد إرسال :count رسالة واتساب؟', ['count' => $report['messages_count']]) }}"
                :disabled="$report['messages_count'] === 0">
                {{ __('إرسال :count رسالة', ['count' => $report['messages_count']]) }}
            </flux:button>
        </div>
    @endif
</div>
