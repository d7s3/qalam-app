<?php

use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Livewire\Component;

/**
 * Where an invited person chooses his own password.
 *
 * He arrives by a signed link, so the route has already proved the letter came
 * from us and that it has not expired; nothing here needs proving again except
 * that he can type the same word twice.
 *
 * He is not signed in afterwards. Setting a password and then being carried
 * into the application teaches nobody where the door is, and finding it is the
 * first thing he will need to do tomorrow.
 */
new class extends Component
{
    public User $user;

    public string $password = '';

    public string $password_confirmation = '';

    public bool $done = false;

    public function mount(User $user): void
    {
        $this->user = $user;
    }

    public function save(): void
    {
        $this->validate([
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ], [], ['password' => __('كلمة المرور')]);

        $this->user->forceFill([
            'password' => Hash::make($this->password),
            // The invitation was the approval: somebody with the authority to
            // make the account made it, and a second approval step would only
            // leave him at a locked door with nobody told.
            'is_approved' => true,
        ])->save();

        $this->reset(['password', 'password_confirmation']);
        $this->done = true;
    }
}; ?>

<div class="flex flex-col gap-6" dir="rtl">
    @if ($done)
        <div class="flex flex-col items-center gap-4 text-center">
            <flux:icon icon="check-circle" class="size-12 text-emerald-500" />
            <h1 class="text-2xl font-bold text-maroon dark:text-red-secondary">{{ __('جاهز') }}</h1>
            <p class="text-sm text-neutral-grey dark:text-zinc-400">
                {{ __('ضُبطت كلمة مرورك. ادخل ببريدك وكلمتك.') }}
            </p>
            <flux:button variant="primary" class="w-full h-11 bg-maroon hover:bg-burgundy" :href="route('login')" wire:navigate>
                {{ __('إلى تسجيل الدخول') }}
            </flux:button>
        </div>
    @else
        <div class="flex flex-col items-center gap-2 text-center">
            <h1 class="text-2xl font-bold text-maroon dark:text-red-secondary">
                {{ __('أهلاً :name', ['name' => $user->name]) }}
            </h1>
            <p class="text-sm text-neutral-grey dark:text-zinc-400">
                {{ __('اختر كلمة مرورك لتفتح حسابك في :name.', ['name' => config('brand.name')]) }}
            </p>
        </div>

        <form wire:submit="save" class="flex flex-col gap-5">
            <flux:field>
                <flux:label>{{ __('البريد الإلكتروني') }}</flux:label>
                <flux:input value="{{ $user->email }}" dir="ltr" disabled />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('كلمة المرور') }}</flux:label>
                <flux:input type="password" wire:model="password" viewable
                    autocomplete="new-password" :placeholder="__('٦ أحرف على الأقل')" />
                <flux:error name="password" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('تأكيد كلمة المرور') }}</flux:label>
                <flux:input type="password" wire:model="password_confirmation" viewable
                    autocomplete="new-password" :placeholder="__('أعدها مرّة أخرى')" />
            </flux:field>

            <flux:button type="submit" variant="primary"
                class="w-full h-11 text-lg font-bold bg-maroon hover:bg-burgundy dark:bg-red-secondary dark:hover:bg-maroon">
                {{ __('احفظ وافتح حسابي') }}
            </flux:button>
        </form>
    @endif
</div>
