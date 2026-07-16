<div class="w-full max-w-3xl mx-auto space-y-6 px-2 md:px-6">
    {{-- Accent top bar --}}
    <div class="h-2 w-full rounded-full bg-purple-500"></div>

    {{-- Header --}}
    <div class="bg-white rounded-2xl border border-zinc-200 p-6 md:p-8 shadow-xs">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h1 class="text-2xl md:text-3xl font-extrabold text-zinc-900">
                    {{ __('صرف العملات') }} — {{ __('حلقة') }} {{ $circle?->name }}
                </h1>
                <p class="text-sm md:text-base text-zinc-500 mt-2">
                    {{ __('اصرف عملات الطالب مقابل جائزة أو علامات ورقية تسلّمها له، وسيُخصم المبلغ من رصيده فوراً دون التأثير على نقاط ترتيبه.') }}
                </p>
            </div>
            <span class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full text-xs font-medium bg-purple-50 text-purple-700 border border-purple-200 shrink-0">
                <flux:icon icon="trophy" class="size-3.5" />
                {{ $leaderboard->title }}
            </span>
        </div>
    </div>

    {{-- Students --}}
    <div class="bg-white rounded-2xl border border-zinc-200 shadow-xs overflow-hidden">
        <div class="px-6 py-4 border-b border-zinc-100 flex items-center justify-between">
            <h2 class="font-bold text-zinc-800">{{ __('طلاب الحلقة') }}</h2>
            <span class="text-xs text-zinc-400">{{ $students->count() }} {{ __('طالباً') }}</span>
        </div>

        <div class="divide-y divide-zinc-100">
            @forelse($students as $student)
                <div class="flex items-center justify-between gap-3 px-6 py-3.5" wire:key="student-{{ $student->id }}">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="size-9 rounded-full bg-purple-50 text-purple-600 flex items-center justify-center font-bold text-sm shrink-0">
                            {{ mb_substr($student->name, 0, 1) }}
                        </div>
                        <div class="font-medium text-zinc-800 truncate">{{ $student->name }}</div>
                    </div>

                    <div class="flex items-center gap-3 shrink-0">
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-sm font-bold bg-amber-50 text-amber-700 border border-amber-200">
                            <span aria-hidden="true">🪙</span>
                            {{ number_format((int) ($coins[$student->id] ?? 0)) }}
                        </span>
                        <flux:button size="sm" variant="primary" icon="banknotes"
                            wire:click="openRedeem({{ $student->id }})"
                            :disabled="(int) ($coins[$student->id] ?? 0) < 1">
                            {{ __('صرف') }}
                        </flux:button>
                    </div>
                </div>
            @empty
                <div class="px-6 py-10 text-center text-sm text-zinc-500">{{ __('لا يوجد طلاب في هذه الحلقة.') }}</div>
            @endforelse
        </div>
    </div>

    {{-- Recent redemptions --}}
    @if($recentRedemptions->isNotEmpty())
        <div class="bg-white rounded-2xl border border-zinc-200 shadow-xs overflow-hidden">
            <div class="px-6 py-4 border-b border-zinc-100">
                <h2 class="font-bold text-zinc-800">{{ __('آخر عمليات الصرف') }}</h2>
            </div>
            <div class="divide-y divide-zinc-100">
                @foreach($recentRedemptions as $redemption)
                    <div class="flex items-center justify-between gap-3 px-6 py-3" wire:key="redemption-{{ $redemption->id }}">
                        <div class="min-w-0">
                            <div class="text-sm font-medium text-zinc-800 truncate">{{ $redemption->student?->name }}</div>
                            <div class="text-xs text-zinc-400 mt-0.5 truncate">{{ $redemption->description }}</div>
                        </div>
                        <div class="text-left shrink-0">
                            <div class="text-sm font-bold text-red-600">{{ number_format(abs($redemption->amount)) }} {{ __('عملة') }}</div>
                            <div class="text-xs text-zinc-400 mt-0.5">{{ $redemption->created_at->format('Y/m/d H:i') }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Redeem modal --}}
    <flux:modal name="redeem-modal" class="min-w-[20rem] md:min-w-[26rem] space-y-5">
        <div>
            <flux:heading size="lg">{{ __('صرف عملات') }} — {{ $redeemStudent?->name }}</flux:heading>
            <flux:text class="mt-1">
                {{ __('الرصيد الحالي:') }}
                <span class="font-bold text-amber-600">{{ number_format($redeemBalance) }} {{ __('عملة') }}</span>
            </flux:text>
        </div>

        <flux:input type="number" min="1" :max="$redeemBalance" wire:model="redeemAmount"
            :label="__('عدد العملات المصروفة')" :placeholder="__('مثال: 100')" />

        <flux:input wire:model="redeemNote" :label="__('البيان (اختياري)')"
            :placeholder="__('مثال: قسيمة مكتبة، 10 علامات ورقية...')" />

        <div class="flex gap-2 justify-end">
            <flux:modal.close>
                <flux:button variant="ghost">{{ __('إلغاء') }}</flux:button>
            </flux:modal.close>
            <flux:button variant="primary" wire:click="redeem"
                wire:confirm="{{ __('تأكيد صرف العملات؟ سيُخصم المبلغ من رصيد الطالب.') }}">
                {{ __('تأكيد الصرف') }}
            </flux:button>
        </div>
    </flux:modal>

    <flux:toast />
</div>
