<?php

namespace App\Livewire\Auth\Student;

use App\Models\Manager;
use App\Models\Student;
use App\Rules\SaudiPhone;
use App\Services\NotificationService;
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
            'email' => ['required', 'string', 'email', 'max:255', 'unique:students'],
            'phone' => ['required', new SaudiPhone, 'unique:students,phone'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
            'terms' => ['accepted'],
        ]);

        $user = Student::create([
            'name' => $this->name,
            'email' => $this->email,
            'phone' => SaudiPhone::format($this->phone),
            'password' => Hash::make($this->password),
            'is_approved' => false,
        ]);

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
