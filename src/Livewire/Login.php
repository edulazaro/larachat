<?php

namespace EduLazaro\Larachat\Livewire;

use EduLazaro\Larachat\Larachat;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('larachat::layouts.panel')]
class Login extends Component
{
    public string $email = '';

    public string $password = '';

    public bool $remember = false;

    public function boot(): void
    {
        app()->setLocale(config('larachat.panel.locale', 'es'));
    }

    public function mount()
    {
        if (Larachat::isAdmin(Auth::user())) {
            return redirect()->route('larachat.panel');
        }
    }

    public function login()
    {
        // Take the password out of the component state before anything else,
        // so it never travels back in the Livewire snapshot.
        $password = $this->password;
        $this->reset('password');

        $this->validate(['email' => 'required|email']);
        if ($password === '') {
            $this->addError('password', __('larachat::panel.password_required'));

            return;
        }

        $key = 'larachat-login:' . Str::lower($this->email) . '|' . request()->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('email', __('larachat::panel.too_many', ['seconds' => RateLimiter::availableIn($key)]));

            return;
        }

        // A valid user who is not an admin gets the same answer as a wrong
        // password: the login does not confirm which emails exist.
        if (! in_array(Str::lower($this->email), Larachat::adminEmails(), true)
            || ! Auth::attempt(['email' => $this->email, 'password' => $password], $this->remember)) {
            RateLimiter::hit($key, 300);
            $this->addError('email', __('larachat::panel.failed'));

            return;
        }

        RateLimiter::clear($key);
        session()->regenerate();

        return redirect()->intended(route('larachat.panel'));
    }

    public function render()
    {
        return view('larachat::livewire.login')->title(__('larachat::panel.login_title'));
    }
}
