<?php

namespace App\Livewire\Auth\Supervisor;

use App\Models\Supervisor;
use App\Rules\SaudiPhone;
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

    public function register()
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'phone' => ['required', new SaudiPhone, 'unique:users,phone'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ]);

        $user = Supervisor::create([
            'name' => $this->name,
            'email' => $this->email,
            'phone' => SaudiPhone::format($this->phone),
            'password' => Hash::make($this->password),
            'is_approved' => false,
        ]);

        event(new Registered($user));

        Auth::guard('supervisor')->login($user);

        return redirect()->route('supervisor.dashboard');
    }

    public function render()
    {
        return view('livewire.auth.supervisor.register')
            ->layout('layouts.auth', ['title' => 'إنشاء حساب - مشرف']);
    }
}
