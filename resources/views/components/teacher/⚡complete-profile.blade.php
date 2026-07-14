<?php

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Flux\Flux;

new class extends Component
{
    public $email = '';
    public $password = '';
    public $password_confirmation = '';

    public function mount()
    {
        $teacher = Auth::guard('teacher')->user();
        if ($teacher->is_data_completed) {
            return redirect()->route('teacher.dashboard');
        }
    }

    public function save()
    {
        $teacher = Auth::guard('teacher')->user();

        $this->validate([
            'email' => 'required|email|unique:users,email,' . $teacher->id,
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $teacher->update([
            'email' => $this->email,
            'password' => Hash::make($this->password),
            'is_data_completed' => true,
        ]);

        Flux::toast('تم تحديث بياناتك بنجاح', variant: 'success');
        
        return redirect()->route('teacher.dashboard');
    }
};
?>

<div class="space-y-6">
    <flux:heading size="xl" level="1">{{ __('مرحباً بك!') }}</flux:heading>
    <flux:subheading>{{ __('لتتمكن من الوصول إلى لوحة التحكم الخاصة بك، يرجى استكمال بيانات تسجيل الدخول.') }}</flux:subheading>

    <flux:card>
        <form wire:submit="save" class="space-y-6">
            <flux:input wire:model="email" label="{{ __('البريد الإلكتروني') }}" type="email" placeholder="{{ __('أدخل بريدك الإلكتروني الشخصي') }}" required />
            
            <flux:input wire:model="password" label="{{ __('كلمة المرور الجديدة') }}" type="password" required viewable />
            
            <flux:input wire:model="password_confirmation" label="{{ __('تأكيد كلمة المرور') }}" type="password" required viewable />

            <div class="flex justify-end pt-4">
                <flux:button type="submit" variant="primary" icon="check-circle" class="w-full">{{ __('حفظ والمتابعة') }}</flux:button>
            </div>
        </form>
    </flux:card>
</div>
