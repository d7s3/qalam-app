<?php

namespace App\Livewire\Auth\Student;

use App\Models\Manager;
use App\Models\Student;
use App\Rules\SaudiPhone;
use App\Services\NotificationService;
use App\Services\StudentStatusService;
use App\Support\StudentStatus;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Livewire\Component;

class Register extends Component
{
    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $password = '';

    public string $password_confirmation = '';

    public bool $terms = false;

    public function register()
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'phone' => ['required', new SaudiPhone, 'unique:users,phone'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
            'terms' => ['accepted'],
        ]);

        $user = Student::create([
            'name' => $this->name,
            'email' => $this->email,
            'phone' => SaudiPhone::format($this->phone),
            'password' => Hash::make($this->password),
            'is_approved' => false,
            // Not مشارك before anyone has looked at him: he was, from the
            // column's default, and so sat in competitions, public results and
            // every count of active students while still awaiting approval.
            'status' => StudentStatus::Registering->value,
        ]);

        StudentStatusService::changeStatus($user, StudentStatus::Registering->value, null, __('أنشأ حسابه بنفسه'));

        event(new Registered($user));

        foreach (Manager::pluck('id') as $managerId) {
            NotificationService::notify(
                'manager',
                $managerId,
                'new_registration',
                'طلب تسجيل جديد',
                "قام {$user->name} بإنشاء حساب جديد وينتظر الموافقة عليه",
                route('manager.pending-approvals'),
            );
        }

        Auth::guard('student')->login($user);

        return redirect()->route('student.dashboard');
    }

    public function render()
    {
        return view('livewire.auth.student.register')
            ->layout('layouts.auth', ['title' => 'إنشاء حساب جديد', 'panelVariant' => 'dark']);
    }
}
