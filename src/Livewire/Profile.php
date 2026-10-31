<?php

namespace EduLazaro\Larachat\Livewire;

use EduLazaro\Larachat\Larachat;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('larachat::layouts.panel')]
class Profile extends Component
{
    public string $name = '';

    public string $email = '';

    /** The current password, asked for when the email changes. */
    public string $email_password = '';

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function boot(): void
    {
        // Every request, not only the page load: Livewire's later requests
        // do not go through the panel's route middleware.
        abort_unless(Larachat::isAdmin(Auth::user()), 404);

        app()->setLocale(config('larachat.panel.locale', 'es'));
    }

    public function mount(): void
    {
        $this->name = (string) Auth::user()->name;
        $this->email = (string) Auth::user()->email;
    }

    public function saveProfile(): void
    {
        $user = Auth::user();
        $password = $this->email_password;
        $this->reset('email_password');
        $changesEmail = mb_strtolower(trim($this->email)) !== mb_strtolower((string) $user->email);

        Validator::make(['name' => $this->name, 'email' => $this->email, 'email_password' => $password], [
            'name' => 'required|string|max:120',
            'email' => ['required', 'email', 'max:160', Rule::unique($user->getTable(), 'email')->ignore($user->getKey()),
                // Access to the panel is the admins list: an email outside it locks you out.
                function (string $attribute, mixed $value, \Closure $fail) {
                    if (! in_array(mb_strtolower(trim((string) $value)), Larachat::adminEmails(), true)) {
                        $fail(__('larachat::panel.email_not_admin'));
                    }
                }],
            // A stolen session alone must not be enough to change who can sign in.
            'email_password' => $changesEmail ? 'required|current_password' : 'nullable',
        ], [
            'email_password.required' => __('larachat::panel.email_password_required'),
            'email_password.current_password' => __('larachat::panel.current_wrong'),
        ])->validate();

        // forceFill: the host's User model decides its own $fillable.
        $user->forceFill(['name' => $this->name, 'email' => trim($this->email)])->save();
        session()->flash('profile_saved', __('larachat::panel.saved'));
    }

    public function savePassword(): void
    {
        // Passwords leave the component state before validation, so they never
        // travel back in the Livewire snapshot, not even after an error.
        $input = $this->only('current_password', 'password', 'password_confirmation');
        $this->reset('current_password', 'password', 'password_confirmation');

        Validator::make($input, [
            'current_password' => 'required|current_password',
            'password' => 'required|string|min:12|confirmed',
        ], [
            'current_password.required' => __('larachat::panel.current_required'),
            'current_password.current_password' => __('larachat::panel.current_wrong'),
            'password.required' => __('larachat::panel.new_required'),
            'password.confirmed' => __('larachat::panel.mismatch'),
            'password.min' => __('larachat::panel.password_min'),
        ])->validate();

        // Hashed here: the host's User model may not cast the password itself.
        Auth::user()->forceFill(['password' => Hash::make($input['password'])])->save();
        session()->flash('password_saved', __('larachat::panel.password_saved'));
    }

    public function render()
    {
        return view('larachat::livewire.profile')->title(__('larachat::panel.profile'));
    }
}
