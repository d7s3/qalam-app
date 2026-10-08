<?php

use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component {
    public bool $show = false;

    public string $guardian_name = '';

    public string $guardian_phone = '';

    public function mount(): void
    {
        $student = Auth::guard('student')->user();
        $this->show = !$student->guardian_id;
    }

    public function save(): void
    {
        $this->validate([
            'guardian_name' => [
                'required',
                'string',
                'max:255',
                'regex:/^[\p{L}]+\s+[\p{L}\s]+$/u',
            ],
            'guardian_phone' => [
                'required',
                'string',
                'regex:/^(05\d{8}|5\d{8})$/',
            ],
        ], [
            'guardian_name.regex' => 'يجب إدخال الاسم ثنائياً على الأقل.',
            'guardian_phone.regex' => 'رقم الجوال غير صحيح، يجب أن يبدأ بـ 05 (10 أرقام) أو 5 (9 أرقام).',
        ]);

        $student = Auth::guard('student')->user();

        $guardian = app(\App\Services\GuardianLinkService::class)->guardianFor($this->guardian_name, $this->guardian_phone);

        $student->guardian_id = $guardian->id;
        $student->is_data_completed = true;
        $student->save();

        $this->show = false;

        Flux::toast('تم ربط حساب ولي الأمر بنجاح ✓', variant: 'success');
    }
};
?>

<div>
    @if($show)
        <div x-data="{ expanded: false }"
            class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 dark:border-amber-800/50 dark:bg-amber-950/30 shadow-sm overflow-hidden">
            {{-- Banner Header --}}
            <div class="flex items-center gap-3 px-5 py-4">
                <div
                    class="flex-shrink-0 w-9 h-9 rounded-full bg-amber-100 dark:bg-amber-900/50 flex items-center justify-center">
                    <flux:icon icon="exclamation-triangle" class="size-5 text-amber-600 dark:text-amber-400" />
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-amber-800 dark:text-amber-300">
                        {{ __('بيانات ولي الأمر غير مكتملة') }}
                    </p>
                    <p class="text-xs text-amber-700/80 dark:text-amber-400/80 mt-0.5">
                        {{ __('يرجى إضافة بيانات ولي الأمر حتى يتمكن من متابعة تقدمك.') }}
                    </p>
                </div>
                <button @click="expanded = !expanded"
                    class="flex-shrink-0 text-xs font-medium text-amber-700 dark:text-amber-400 hover:text-amber-900 dark:hover:text-amber-200 border border-amber-300 dark:border-amber-700 rounded-lg px-3 py-1.5   s">
                    <span x-show="!expanded">{{ __('إضافة الآن') }}</span>
                    <span x-show="expanded" style="display:none;">{{ __('إخفاء') }}</span>
                </button>
            </div>

            {{-- Inline Form --}}
            <div x-show="expanded" x-transition:enter="ease-out duration-200"
                x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="ease-in duration-150" x-transition:leave-start="opacity-100 translate-y-0"
                x-transition:leave-end="opacity-0 -translate-y-2" style="display:none;"
                class="border-t border-amber-200 dark:border-amber-800/50 px-5 pb-5 pt-4 bg-white/60 dark:bg-zinc-900/40">
                <form wire:submit="save" class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-end">
                    <flux:input wire:model="guardian_name" label="{{ __('اسم ولي الأمر') }}"
                        placeholder="{{ __('مثال: محمد أحمد العتيبي') }}" required />
                    <flux:input wire:model="guardian_phone" label="{{ __('رقم الجوال') }}" type="tel"
                        placeholder="{{ __('05XXXXXXXX') }}" required dir="ltr" />
                    <flux:button type="submit" variant="primary" wire:loading.attr="disabled" class="w-full">
                        <span wire:loading.remove>{{ __('حفظ') }}</span>
                        <span wire:loading>{{ __('جارٍ الحفظ...') }}</span>
                    </flux:button>
                </form>
            </div>
        </div>
    @endif
</div>