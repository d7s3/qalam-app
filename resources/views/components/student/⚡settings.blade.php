<?php

use App\Concerns\PasswordValidationRules;
use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

new class extends Component {
    use PasswordValidationRules;

    public string $name = '';
    public string $email = '';
    public string $phone = '';
    public ?string $birth_date = null;

    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';

    public function mount()
    {
        $user = Auth::guard('student')->user();
        $this->name = $user->name;
        $this->email = $user->email;
        $this->phone = $user->phone ?? '';
        $this->birth_date = $user->birth_date?->format('Y-m-d');
    }

    public function updateProfileInformation()
    {
        $user = Auth::guard('student')->user();

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('students')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'regex:/^(05\d{8}|5\d{8})$/'],
            'birth_date' => ['nullable', 'date'],
        ], [
            'phone.regex' => 'رقم الجوال غير صحيح، يجب أن يتكون من 10 أرقام ويبدأ بـ 05، أو 9 أرقام ويبدأ بـ 5.',
        ]);

        $user->update($validated);

        $this->dispatch('profile-updated', name: $user->name);
    }

    public function updatePassword(): void
    {
        try {
            $validated = $this->validate([
                'current_password' => ['required', 'string', 'current_password:student'],
                'password' => $this->passwordRules(),
            ]);
        } catch (ValidationException $e) {
            $this->reset('current_password', 'password', 'password_confirmation');

            throw $e;
        }

        Auth::guard('student')->user()->update([
            'password' => $validated['password'],
        ]);

        $this->reset('current_password', 'password', 'password_confirmation');

        $this->dispatch('password-updated');
    }
};
?>

<section class="w-full max-w-2xl mx-auto">
    <div class="mb-6">
        <flux:heading size="lg">{{ __('إعدادات الحساب') }}</flux:heading>
        <flux:subheading>{{ __('تحديث بيانات حسابك كطالب') }}</flux:subheading>
    </div>

    <form wire:submit="updateProfileInformation" class="space-y-6">
        <flux:input wire:model="name" :label="__('الاسم')" type="text" required autofocus autocomplete="name" />

        <flux:input wire:model="email" :label="__('البريد الإلكتروني')" type="email" required autocomplete="email" />

        <flux:input wire:model="phone" :label="__('رقم الهاتف')" type="text" autocomplete="tel" placeholder="05XXXXXXXX" />

        <livewire:shared.hijri-datepicker wire:model="birth_date" :label="__('تاريخ الميلاد')" />

        <div class="flex items-center gap-4 pt-4 border-t border-zinc-100 dark:border-zinc-800">
            <div class="flex items-center justify-end">
                <flux:button variant="primary" type="submit" class="w-full">{{ __('حفظ') }}</flux:button>
            </div>

            <x-action-message class="me-3" on="profile-updated">
                {{ __('تم الحفظ.') }}
            </x-action-message>
        </div>
    </form>

    <flux:separator class="my-10" />

    <div class="mb-6">
        <flux:heading size="lg">{{ __('تغيير كلمة المرور') }}</flux:heading>
        <flux:subheading>{{ __('تأكد من استخدام كلمة مرور طويلة وعشوائية للحفاظ على أمان حسابك') }}</flux:subheading>
    </div>

    <form wire:submit="updatePassword" class="space-y-6">
        <flux:input wire:model="current_password" :label="__('كلمة المرور الحالية')" type="password" required autocomplete="current-password" viewable />

        <flux:input wire:model="password" :label="__('كلمة المرور الجديدة')" type="password" required autocomplete="new-password" viewable />

        <flux:input wire:model="password_confirmation" :label="__('تأكيد كلمة المرور')" type="password" required autocomplete="new-password" viewable />

        <div class="flex items-center gap-4 pt-4 border-t border-zinc-100 dark:border-zinc-800">
            <div class="flex items-center justify-end">
                <flux:button variant="primary" type="submit" class="w-full">{{ __('تحديث كلمة المرور') }}</flux:button>
            </div>

            <x-action-message class="me-3" on="password-updated">
                {{ __('تم الحفظ.') }}
            </x-action-message>
        </div>
    </form>
</section>