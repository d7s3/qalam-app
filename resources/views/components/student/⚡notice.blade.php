<?php

use App\Models\StudentNotice;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * The message written to this student by name, and — when it came with one —
 * the box for the code that opens his account.
 */
new class extends Component
{
    public string $code = '';

    public function mount(): void
    {
        if (! $this->notice) {
            $this->redirectRoute('student.dashboard');
        }
    }

    #[Computed]
    public function notice(): ?StudentNotice
    {
        return StudentNotice::waitingFor(Auth::guard('student')->id());
    }

    public function open(): void
    {
        $this->resetErrorBag();
        $notice = $this->notice;

        if (! $notice) {
            $this->redirectRoute('student.dashboard');

            return;
        }

        if ($notice->locks()) {
            // Five tries a minute: enough for a slip of the finger, too few to guess a code.
            $key = 'student-notice:'.$notice->student_id;

            if (RateLimiter::tooManyAttempts($key, 5)) {
                $this->addError('code', __('محاولات كثيرة. انتظر دقيقة ثم حاول.'));

                return;
            }

            if (! $notice->acceptsCode($this->code)) {
                RateLimiter::hit($key, 60);
                $this->addError('code', __('الرمز غير صحيح.'));

                return;
            }

            RateLimiter::clear($key);
        }

        $notice->update(['opened_at' => now()]);
        $this->code = '';
        unset($this->notice);

        if (! $this->notice) {
            $this->redirectRoute('student.dashboard');
        }
    }
};
?>

<div class="flex items-center justify-center" dir="rtl">
    @if ($this->notice)
        <div class="w-full max-w-lg bg-white dark:bg-zinc-900 rounded-2xl border border-zinc-200 dark:border-zinc-800 shadow-sm p-8 space-y-6" data-student-notice>
            <div class="text-center space-y-3">
                <div class="inline-flex p-4 rounded-full bg-maroon/10 dark:bg-white/10">
                    <flux:icon :icon="$this->notice->locks() ? 'lock-closed' : 'envelope'" class="size-10 text-maroon dark:text-white" />
                </div>
                <flux:heading size="xl">{{ __('رسالة لك') }}</flux:heading>
            </div>

            <p class="text-base leading-loose text-zinc-800 dark:text-zinc-100 whitespace-pre-line">{{ $this->notice->bodyFor(Auth::guard('student')->user()->name) }}</p>

            @if ($this->notice->locks())
                <form wire:submit="open" class="space-y-3">
                    <flux:input wire:model="code" :label="__('الرمز')" autocomplete="off" autofocus dir="ltr" class="text-center" />
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('حسابك مغلق حتى تُدخل الرمز الذي يعطيك إياه مشرفك.') }}</p>
                    <flux:button type="submit" variant="primary" class="w-full">{{ __('افتح حسابي') }}</flux:button>
                </form>
            @else
                <flux:button wire:click="open" variant="primary" class="w-full">{{ __('قرأتها') }}</flux:button>
            @endif

            <form method="POST" action="{{ route('logout') }}" class="text-center">
                @csrf
                <button type="submit" class="text-xs text-zinc-400 hover:text-zinc-600 underline underline-offset-4">{{ __('تسجيل الخروج') }}</button>
            </form>
        </div>
    @endif
</div>
