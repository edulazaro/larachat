<?php

namespace EduLazaro\Larachat;

use EduLazaro\Larachat\Contracts\Persona as PersonaContract;
use Illuminate\Support\Facades\Lang;

/**
 * A persona with sensible defaults. A site extends it, fills the four
 * properties and writes systemPrompt() and scopePrompt(); everything else is
 * optional. Fixed texts come from the package's lang files, which a site
 * overrides in lang/vendor/larachat/{locale}/chat.php.
 */
abstract class Persona implements PersonaContract
{
    protected string $name = 'Assistant';

    protected string $owner = 'the team';

    protected string $site = '';

    protected ?string $email = null;

    public function name(): string
    {
        return $this->name;
    }

    public function owner(): string
    {
        return $this->owner;
    }

    public function site(): string
    {
        return $this->site !== '' ? $this->site : (string) config('app.name');
    }

    public function email(): ?string
    {
        return $this->email;
    }

    public function siteMap(string $locale): ?string
    {
        return null;
    }

    public function fallbackKnowledge(string $locale): ?string
    {
        return null;
    }

    public function pageContext(?string $page, string $locale): ?string
    {
        return null;
    }

    public function privacyUrl(string $locale): ?string
    {
        return null;
    }

    public function text(string $key, string $locale, array $replace = []): string
    {
        // A text that sends the visitor to the contact email has a variant
        // without it, for a site that does not give one.
        if (! $this->email() && Lang::has("larachat::chat.{$key}_no_email", $locale)) {
            $key .= '_no_email';
        }

        return (string) __('larachat::chat.' . $key, $replace + [
            'bot' => $this->name(),
            'owner' => $this->owner(),
            'site' => $this->site(),
            'email' => (string) $this->email(),
        ], $locale);
    }
}
