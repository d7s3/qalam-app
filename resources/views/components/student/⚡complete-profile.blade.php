<?php

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use App\Models\Guardian;
use Flux\Flux;

new class extends Component
{
    public $email = '';
    public $password = '';
    public $password_confirmation = '';

    public $needsGuardian = false;
    public $guardian_name = '';
    public $guardian_phone = '';

    public function mount()
    {
        $student = Auth::guard('student')->user();
        if ($student->is_data_completed) {
            return redirect()->route('student.dashboard');
        }

        if (!$student->guardian_id) {
            $this->needsGuardian = true;
        }
    }

    public function save()
    {
        $student = Auth::guard('student')->user();

        $rules = [
            'email' => 'required|email|unique:users,email,' . $student->id,
            'password' => ['required', 'confirmed', Password::defaults()],
        ];

        if ($this->needsGuardian) {
            $rules['guardian_name'] = [
                'required',
                'string',
                'max:255',
                'regex:/^[\p{L}]+\s+[\p{L}\s]+$/u' // Must be at least two words
            ];
            $rules['guardian_phone'] = [
                'required',
                'string',
                'regex:/^(05\d{8}|5\d{8})$/' // Starts with 5 (9 digits) or 05 (10 digits)
            ];
        }

        $this->validate($rules, [
            'guardian_name.regex' => 'يجب إدخال الاسم ثنائياً على الأقل.',
            'guardian_phone.regex' => 'رقم الجوال غير صحيح، يجب أن يتكون من 10 أرقام ويبدأ بـ 05، أو 9 أرقام ويبدأ بـ 5.',
        ]);

        if ($this->needsGuardian) {
            // Format phone to 966...
            $formattedPhone = $this->guardian_phone;
            if (str_starts_with($formattedPhone, '0')) {
                $formattedPhone = '966' . substr($formattedPhone, 1);
            } elseif (str_starts_with($formattedPhone, '5')) {
                $formattedPhone = '966' . $formattedPhone;
            }

            // Check if Guardian with this phone already exists
            $guardian = Guardian::where('phone', $formattedPhone)->first();

            if (!$guardian) {
                $emailPrefix = $formattedPhone;
                $guardianEmail = $emailPrefix . '@parent.com';
                
                // Add randomness if the generated email already exists
                while (Guardian::where('email', $guardianEmail)->exists()) {
                    $guardianEmail = $emailPrefix . rand(10, 99) . '@parent.com';
                }

                $guardian = Guardian::create([
                    'name' => $this->guardian_name,
                    'phone' => $formattedPhone,
                    'email' => $guardianEmail,
                    'password' => Hash::make($formattedPhone),
                    'is_approved' => true,
                ]);
            }

            $student->guardian_id = $guardian->id;
        }

        $student->email = $this->email;
        $student->password = Hash::make($this->password);
        $student->is_data_completed = true;
        $student->save();

        Flux::toast('تم تحديث بياناتك بنجاح', variant: 'success');
        
        return redirect()->route('student.dashboard');
    }
};
?>

<div class="space-y-6">
    <flux:heading size="xl" level="1">{{ __('مرحباً بك!') }}</flux:heading>
    <flux:subheading>{{ __('لتتمكن من الوصول إلى لوحة التحكم الخاصة بك، يرجى استكمال بيانات تسجيل الدخول.') }}</flux:subheading>

    <flux:card>
        <form wire:submit="save" class="space-y-6">
            @if($needsGuardian)
                <div class="space-y-4 mb-6 pb-6 border-b border-zinc-200 dark:border-zinc-700">
                    <flux:heading size="md">{{ __('بيانات ولي الأمر (مطلوبة)') }}</flux:heading>
                    <flux:subheading size="sm">{{ __('حسابك غير مرتبط بولي أمر حتى الآن. يرجى إدخال بيانات ولي الأمر لإنشاء حساب له وربطه بك.') }}</flux:subheading>
                    <flux:input wire:model="guardian_name" label="{{ __('اسم ولي الأمر الأساسي') }}" type="text" placeholder="{{ __('الاسم الثلاثي') }}" required />
                    <flux:input wire:model="guardian_phone" label="{{ __('رقم الهاتف / الجوال') }}" type="tel" placeholder="{{ __('مثال: 05XXXXXXXX') }}" required />
                </div>
                <flux:heading size="md" class="mt-4 mb-4">{{ __('بيانات الدخول الخاصة بك (الطالب)') }}</flux:heading>
            @endif

            <flux:input wire:model="email" label="{{ __('البريد الإلكتروني') }}" type="email" placeholder="{{ __('أدخل بريدك الإلكتروني الشخصي') }}" required />
            
            <flux:input wire:model="password" label="{{ __('كلمة المرور الجديدة') }}" type="password" required viewable />
            
            <flux:input wire:model="password_confirmation" label="{{ __('تأكيد كلمة المرور') }}" type="password" required viewable />

            <div class="flex justify-end pt-4">
                <flux:button type="submit" variant="primary" icon="check-circle" class="w-full">{{ __('حفظ والمتابعة') }}</flux:button>
            </div>
        </form>
    </flux:card>
</div>