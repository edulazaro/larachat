<?php

namespace EduLazaro\Larachat\Tests\Unit;

use EduLazaro\Larachat\Larachat;
use EduLazaro\Larachat\Tests\Fixtures\TestPersona;
use EduLazaro\Larachat\Tests\TestCase;

class LarachatTest extends TestCase
{
    public function test_locale_comes_from_the_first_segment(): void
    {
        $this->assertSame('en', Larachat::locale('/'));
        $this->assertSame('en', Larachat::locale(null));
        $this->assertSame('en', Larachat::locale('/industries/legal'));
        $this->assertSame('es', Larachat::locale('/es'));
        $this->assertSame('es', Larachat::locale('/es/industrias/legal'));
        $this->assertSame('en', Larachat::locale('/fr/whatever'), 'fr is not a site locale here');
        $this->assertSame('es', Larachat::locale('https://acme.test/es/x?y=1'));
    }

    public function test_a_site_whose_default_locale_has_no_prefix(): void
    {
        config(['larachat.locales' => ['es', 'ca', 'fr'], 'larachat.default_locale' => 'es']);

        $this->assertSame('es', Larachat::locale('/desarrollo-laravel'));
        $this->assertSame('ca', Larachat::locale('/ca/desenvolupament-laravel'));
        $this->assertSame('fr', Larachat::locale('/fr'));
        $this->assertSame('es', Larachat::locale('/es/foo'), 'a prefix for the default locale is just a path');
    }

    public function test_texts_without_an_email_use_their_variant(): void
    {
        $persona = new class extends TestPersona {
            protected ?string $email = null;
        };

        $this->assertStringNotContainsString('@', $persona->text('closed', 'es'));
        $this->assertSame('Esta conversación ha finalizado.', $persona->text('closed', 'es'));
        $this->assertStringContainsString('Edu', $persona->text('keep_asking', 'ca'));
    }

    public function test_admins_are_only_the_configured_emails(): void
    {
        $admin = $this->admin();
        $other = \EduLazaro\Larachat\Tests\Fixtures\User::forceCreate(['name' => 'X', 'email' => 'x@acme.test', 'password' => 'x']);

        $this->assertTrue(Larachat::isAdmin($admin));
        $this->assertFalse(Larachat::isAdmin($other));
        $this->assertFalse(Larachat::isAdmin(null));
        $this->assertSame([$admin->id], Larachat::admins()->pluck('id')->all());
    }

    public function test_available_only_while_the_panel_is_open(): void
    {
        $admin = $this->admin();
        $this->assertFalse(Larachat::humanAvailable());

        $admin->forceFill(['is_available' => true, 'last_seen_at' => now()])->save();
        $this->assertTrue(Larachat::humanAvailable());

        // A forgotten switch turns itself off.
        $admin->forceFill(['last_seen_at' => now()->subMinutes(4)])->save();
        $this->assertFalse(Larachat::humanAvailable());
    }
}
