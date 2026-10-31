<?php

namespace EduLazaro\Larachat\Tests\Fixtures;

use EduLazaro\Larachat\Persona;

class TestPersona extends Persona
{
    protected string $name = 'Nodo';

    protected string $owner = 'Edu';

    protected string $site = 'Acme';

    protected ?string $email = 'hi@acme.test';

    public function systemPrompt(string $locale): string
    {
        return "You are Nodo, the assistant of Acme. PERSONA-PROMPT-{$locale}";
    }

    public function scopePrompt(): string
    {
        return 'SCOPE-PROMPT';
    }

    public function siteMap(string $locale): ?string
    {
        return "\n\nSITE MAP: https://acme.test";
    }

    public function fallbackKnowledge(string $locale): ?string
    {
        return "\n\nFALLBACK-KNOWLEDGE";
    }

    public function privacyUrl(string $locale): ?string
    {
        return 'https://acme.test/privacy';
    }
}
