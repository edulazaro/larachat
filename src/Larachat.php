<?php

namespace EduLazaro\Larachat;

use EduLazaro\Larachat\Contracts\Persona;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;

/**
 * What the rest of the package asks about the site: the visitor's locale,
 * the persona, and whether the owner is at the panel right now.
 */
class Larachat
{
    /**
     * The locale of a page, read from its first path segment. A path without
     * a known locale prefix is in the default locale.
     */
    public static function locale(?string $page): string
    {
        $path = trim((string) parse_url((string) $page, PHP_URL_PATH), '/');
        $first = explode('/', $path)[0] ?? '';
        $default = config('larachat.default_locale', 'en');

        if ($first !== '' && $first !== $default && in_array($first, config('larachat.locales', []), true)) {
            return $first;
        }

        return $default;
    }

    public static function persona(): Persona
    {
        return app(Persona::class);
    }

    /**
     * The owner counts as available when they switched it on AND the panel
     * was seen open in the last few minutes, so a forgotten switch turns
     * itself off instead of leaving visitors waiting for nobody.
     */
    public static function humanAvailable(): bool
    {
        return static::admins()
            ->where('is_available', true)
            ->where('last_seen_at', '>=', now()->subMinutes((int) config('larachat.panel.available_minutes', 3)))
            ->exists();
    }

    /**
     * Where the login form is: its own page, or the panel's address when both
     * are configured with the same path.
     */
    public static function loginUrl(): string
    {
        return route(app('router')->has('larachat.login') ? 'larachat.login' : 'larachat.panel');
    }

    /**
     * The users allowed into the panel: the configured emails, nobody else,
     * whatever other users the site has.
     */
    public static function admins(): Builder
    {
        $model = static::userModel();

        return $model::query()->whereIn('email', static::adminEmails());
    }

    public static function isAdmin(?Authenticatable $user): bool
    {
        return $user !== null && in_array(mb_strtolower((string) $user->email), static::adminEmails(), true);
    }

    /** @return list<string> */
    public static function adminEmails(): array
    {
        return array_values(array_map('mb_strtolower', (array) config('larachat.admins', [])));
    }

    /** @return class-string<\Illuminate\Database\Eloquent\Model> */
    public static function userModel(): string
    {
        return config('auth.providers.users.model', 'App\\Models\\User');
    }

    /**
     * The address of a page on the public site, as the bot cites it.
     */
    public static function url(string $path): string
    {
        $base = rtrim((string) config('larachat.knowledge.base_url'), '/');

        return $base . ($path === '/' ? '' : '/' . ltrim($path, '/'));
    }
}
