<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class Login extends Component
{
    public string $email = '';

    public string $password = '';

    public bool $remember = false;

    /**
     * The guards tried in order until one accepts the credentials — lets a
     * single login form work for every role without the visitor picking one.
     */
    protected const GUARDS = ['student', 'teacher', 'guardian', 'supervisor', 'manager'];

    public function login()
    {
        $this->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        foreach (self::GUARDS as $guard) {
            if (Auth::guard($guard)->attempt([
                'email' => $this->email,
                'password' => $this->password,
            ], $this->remember)) {
                session()->forget('url.intended');
                session()->regenerate();

                return redirect()->route("{$guard}.dashboard");
            }
        }

        throw ValidationException::withMessages([
            'email' => __('auth.failed'),
        ]);
    }

    public function render()
    {
        return view('livewire.auth.login-unified');
    }
}
