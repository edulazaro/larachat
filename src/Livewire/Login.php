<?php

namespace EduLazaro\Larachat\Livewire;

use EduLazaro\Larachat\Larachat;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('larachat::layouts.panel')]
class Login extends Component
{
    /** A bcrypt hash of a random string, checked when the email is not an admin's. */
    protected const DUMMY_HASH = '$2y$12$37r8MZCJfqN9UyFvOEv6aOVeksH7JcqJX5MxCL.Y8or0zR37q.xDO';

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

        // Two limits: per email and IP, and per email whatever the IP, so changing
        // IP does not buy more guesses at the same account.
        $email = Str::lower($this->email);
        $key = 'larachat-login:' . $email . '|' . request()->ip();
        $emailKey = 'larachat-login-email:' . $email;
        foreach ([[$key, 5], [$emailKey, 10]] as [$limiter, $max]) {
            if (RateLimiter::tooManyAttempts($limiter, $max)) {
                $this->addError('email', __('larachat::panel.too_many', ['seconds' => RateLimiter::availableIn($limiter)]));

                return;
            }
        }

        // A valid user who is not an admin gets the same answer as a wrong
        // password, and in the same time: the password is hashed either way, so
        // neither the message nor the delay tells which emails are admins.
        $isAdmin = in_array($email, Larachat::adminEmails(), true);
        if (! $isAdmin) {
            Hash::check($password, self::DUMMY_HASH);
        }

        if (! $isAdmin || ! Auth::attempt(['email' => $this->email, 'password' => $password], $this->remember)) {
            RateLimiter::hit($key, 300);
            RateLimiter::hit($emailKey, 900);
            $this->addError('email', __('larachat::panel.failed'));

            return;
        }

        RateLimiter::clear($key);
        RateLimiter::clear($emailKey);
        session()->regenerate();

        return redirect()->intended(route('larachat.panel'));
    }

    public function render()
    {
        return view('larachat::livewire.login')->title(__('larachat::panel.login_title'));
    }
}
