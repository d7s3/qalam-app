<div
    class="py-6 space-y-6 border shadow-sm rounded-xl border-zinc-200 dark:border-white/10"
    wire:cloak
    x-data="{ showRecoveryCodes: false }"
>
    <div class="px-6 space-y-2">
        <div class="flex items-center gap-2">
            <flux:icon.lock-closed variant="outline" class="size-4"/>
            <flux:heading size="lg" level="3">رموز الاسترداد للمصادقة الثنائية</flux:heading>
        </div>
        <flux:text variant="subtle">
            رموز الاسترداد تتيح لك استعادة الوصول لحسابك في حال فقدان جهاز المصادقة الثنائية. احتفظ بها في مكان آمن.
        </flux:text>
    </div>

    <div class="px-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <flux:button
                x-show="!showRecoveryCodes"
                icon="eye"
                icon:variant="outline"
                variant="primary"
                @click="showRecoveryCodes = true;"
                aria-expanded="false"
                aria-controls="recovery-codes-section"
            >
                عرض رموز الاسترداد
            </flux:button>

            <flux:button
                x-show="showRecoveryCodes"
                icon="eye-slash"
                icon:variant="outline"
                variant="primary"
                @click="showRecoveryCodes = false"
                aria-expanded="true"
                aria-controls="recovery-codes-section"
            >
                إخفاء رموز الاسترداد
            </flux:button>

            @if (filled($recoveryCodes))
                <flux:button
                    x-show="showRecoveryCodes"
                    icon="arrow-path"
                    variant="filled"
                    wire:click="regenerateRecoveryCodes"
                >
                    إعادة توليد الرموز
                </flux:button>
            @endif
        </div>

        <div
            x-show="showRecoveryCodes"
            x-transition
            id="recovery-codes-section"
            class="relative overflow-hidden"
            x-bind:aria-hidden="!showRecoveryCodes"
        >
            <div class="mt-3 space-y-3">
                @error('recoveryCodes')
                    <flux:callout variant="danger" icon="x-circle" heading="{{$message}}"/>
                @enderror

                @if (filled($recoveryCodes))
                    <div
                        class="grid gap-1 p-4 font-mono text-sm rounded-lg bg-zinc-100 dark:bg-white/5"
                        role="list"
                        aria-label="رموز الاسترداد"
                    >
                        @foreach($recoveryCodes as $code)
                            <div
                                role="listitem"
                                class="select-text"
                                wire:loading.class="opacity-50 animate-pulse"
                            >
                                {{ $code }}
                            </div>
                        @endforeach
                    </div>
                    <flux:text variant="subtle" class="text-xs">
                        كل رمز استرداد يمكن استخدامه مرة واحدة فقط للوصول لحسابك، وسيُحذف بعد الاستخدام. إذا احتجت المزيد، اضغط على "إعادة توليد الرموز" أعلاه.
                    </flux:text>
                @endif
            </div>
        </div>
    </div>
</div>
