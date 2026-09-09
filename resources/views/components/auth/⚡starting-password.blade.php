<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Livewire\Component;

/**
 * The one screen a person on the starting code is allowed to see.
 *
 * He is already signed in — the code let him in, which is its whole job — so
 * there is nothing to prove here beyond choosing something of his own. The
 * middleware keeps him on this page until he has, so there is no «later».
 *
 * The code he arrived on is refused as a new password, or the screen would be
 * a formality: a man who types 123456 twice has changed nothing.
 */
new class extends Component
{
    public string $password = '';

    public string $password_confirmation = '';

    /** Whoever is signed in, under whichever guard let him in. */
    private function person()
    {
        foreach (['manager', 'supervisor', 'teacher', 'student', 'guardian', 'staff'] as $guard) {
            if ($user = Auth::guard($guard)->user()) {
                return $user;
            }
        }

        return null;
    }

    public function save(): void
    {
        $user = $this->person();

        abort_unless($user, 403);

        $this->validate([
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ], [], ['password' => __('كلمة المرور')]);

        if (Hash::check($this->password, $user->password)) {
            $this->addError('password', __('هذه هي نفسها التي دخلت بها — اختر غيرها.'));

            return;
        }

        $user->forceFill([
            'password' => Hash::make($this->password),
            'must_change_password' => false,
        ])->save();

        $this->redirect(route('home'), navigate: true);
    }
}; ?>

<div class="flex flex-col gap-6" dir="rtl">
    <div class="flex flex-col items-center gap-2 text-center">
        <flux:icon icon="key" class="size-10 text-maroon dark:text-gold" />
        <h1 class="text-2xl font-bold text-maroon dark:text-red-secondary">{{ __('اختر كلمة مرورك') }}</h1>
        <p class="text-sm text-neutral-grey dark:text-zinc-400">
            {{ __('دخلتَ بالرمز المبدئي الذي أُعطي لك. اختر كلمةً تخصّك وحدك لتكمل.') }}
        </p>
    </div>

    <form wire:submit="save" class="flex flex-col gap-5">
        <flux:field>
            <flux:label>{{ __('كلمة المرور الجديدة') }}</flux:label>
            <flux:input type="password" wire:model="password" viewable autofocus
                autocomplete="new-password" :placeholder="__('٦ أحرف على الأقل')" />
            <flux:error name="password" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('تأكيدها') }}</flux:label>
            <flux:input type="password" wire:model="password_confirmation" viewable
                autocomplete="new-password" :placeholder="__('أعدها مرّة أخرى')" />
        </flux:field>

        <flux:button type="submit" variant="primary"
            class="w-full h-11 text-lg font-bold bg-maroon hover:bg-burgundy dark:bg-red-secondary dark:hover:bg-maroon">
            {{ __('احفظ وأكمل') }}
        </flux:button>
    </form>
</div>
