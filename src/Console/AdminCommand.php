<?php

namespace EduLazaro\Larachat\Console;

use EduLazaro\Larachat\Larachat;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminCommand extends Command
{
    protected $signature = 'larachat:admin {email : The admin\'s email} {--name= : Their name, for a new user}';

    protected $description = 'Create a panel user, or give an existing one a new password (shown once)';

    public function handle(): int
    {
        $email = mb_strtolower((string) $this->argument('email'));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Not a valid email.');

            return self::FAILURE;
        }

        $model = Larachat::userModel();
        $user = $model::query()->where('email', $email)->first() ?? new $model();
        $password = Str::password(20, symbols: false);

        // forceFill: the host's User model decides its own $fillable.
        $user->forceFill([
            'name' => $user->name ?: ($this->option('name') ?: Str::before($email, '@')),
            'email' => $email,
            'password' => Hash::make($password),
        ])->save();

        $this->info(($user->wasRecentlyCreated ? 'Created ' : 'New password for ') . $email . ':');
        $this->line('  ' . $password);
        $this->newLine();
        $this->line('Shown only now. Change it in the panel profile if you like.');

        if (! Larachat::isAdmin($user)) {
            $this->warn("{$email} is not in larachat.admins yet: add it to config/larachat.php or the login will refuse it.");
        }

        return self::SUCCESS;
    }
}
