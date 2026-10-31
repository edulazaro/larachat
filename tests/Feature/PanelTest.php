<?php

namespace EduLazaro\Larachat\Tests\Feature;

use EduLazaro\Larachat\Livewire\Inbox;
use EduLazaro\Larachat\Livewire\Login;
use EduLazaro\Larachat\Livewire\Profile;
use EduLazaro\Larachat\Models\ChatSession;
use EduLazaro\Larachat\Tests\Fixtures\User;
use EduLazaro\Larachat\Tests\TestCase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Orchestra\Testbench\Attributes\DefineEnvironment;

class PanelTest extends TestCase
{
    protected function conversation(string $first = 'Hello'): ChatSession
    {
        $session = ChatSession::create(['session_token' => ChatSession::generateToken(), 'visitor_ip' => '1.2.3.4', 'visitor_page' => '/', 'last_message_at' => now()]);
        $session->messages()->create(['sender' => 'visitor', 'message' => $first]);

        return $session;
    }

    public function test_one_address_shows_guests_the_login_and_the_admin_the_inbox(): void
    {
        $this->get('/larachat')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')
            ->assertSee('Contraseña')->assertDontSee('Conversaciones');
        $this->get('/larachat/profile')->assertNotFound();

        $this->actingAs(User::forceCreate(['name' => 'X', 'email' => 'x@acme.test', 'password' => 'x']));
        $this->get('/larachat')->assertOk()->assertSee('Contraseña')->assertDontSee('Conversaciones');

        $this->actingAs($this->admin());
        $this->get('/larachat')->assertOk()->assertSee('Conversaciones');
    }

    protected function useSeparateLogin($app): void
    {
        $app['config']->set('larachat.routes.login', 'enter');
    }

    #[DefineEnvironment('useSeparateLogin')]
    public function test_with_a_login_page_of_its_own_the_panel_answers_404_to_guests_and_non_admins(): void
    {
        $this->get('/larachat')->assertNotFound();
        $this->get('/larachat/profile')->assertNotFound();
        $this->get('/enter')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')->assertSee('Contraseña');

        $this->actingAs(User::forceCreate(['name' => 'X', 'email' => 'x@acme.test', 'password' => 'x']));
        $this->get('/larachat')->assertNotFound();

        $this->actingAs($this->admin());
        $this->post('/larachat/logout')->assertRedirect('/enter');
    }

    public function test_panel_actions_are_refused_to_anyone_who_is_not_an_admin(): void
    {
        $this->actingAs(User::forceCreate(['name' => 'X', 'email' => 'x@acme.test', 'password' => 'x']));

        Livewire::test(Inbox::class)->assertStatus(404);
        Livewire::test(Profile::class)->assertStatus(404);
    }

    public function test_login_never_keeps_the_password_and_refuses_non_admins_alike(): void
    {
        $this->admin();
        User::forceCreate(['name' => 'X', 'email' => 'x@acme.test', 'password' => Hash::make('secret-password')]);

        Livewire::test(Login::class)
            ->set('email', 'edu@acme.test')->set('password', 'wrong')
            ->call('login')
            ->assertHasErrors('email')
            ->assertSet('password', '');

        Livewire::test(Login::class)
            ->set('email', 'x@acme.test')->set('password', 'secret-password')
            ->call('login')
            ->assertHasErrors('email');
        $this->assertGuest();

        Livewire::test(Login::class)
            ->assertSet('remember', false)
            ->set('email', 'edu@acme.test')->set('password', 'secret-password')
            ->call('login')
            ->assertRedirect(route('larachat.panel'));
        $this->assertAuthenticated();
    }

    public function test_the_inbox_lists_replies_takes_blocks_and_deletes(): void
    {
        $admin = $this->admin();
        $session = $this->conversation('Necesito una web');
        $this->actingAs($admin);

        $this->get('/larachat?c=' . $session->id)->assertOk()->assertSee('Conversaciones')->assertSee('Necesito una web');

        Livewire::test(Inbox::class)
            ->call('select', $session->id)
            ->set('reply', 'Hola, soy Edu')
            ->call('send')
            ->assertDispatched('reply-sent');
        $this->assertSame('human', $session->fresh()->mode);
        $this->assertSame('Hola, soy Edu', $session->messages()->where('sender', 'admin')->value('message'));

        Livewire::test(Inbox::class, ['open' => $session->id])->call('release');
        $this->assertSame('ai', $session->fresh()->mode);

        Livewire::test(Inbox::class)->set('open', $session->id)->call('block');
        $this->assertSame('closed', $session->fresh()->status);
        $this->assertTrue(ChatSession::ipBlocked('1.2.3.4'));

        Livewire::test(Inbox::class)->set('open', $session->id)->call('deleteConversation')->assertSet('open', null);
        $this->assertNull(ChatSession::find($session->id));
        $this->assertFalse(ChatSession::ipBlocked('1.2.3.4'), 'deleting a blocked conversation lifts the IP block');
    }

    public function test_heartbeat_keeps_the_owner_available_and_announces_new_messages(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $inbox = Livewire::test(Inbox::class)->set('available', true);
        $this->assertTrue((bool) $admin->fresh()->is_available);

        $this->conversation('New one');
        $inbox->call('heartbeat')->assertDispatched('chat-new');
        $this->assertTrue(\EduLazaro\Larachat\Larachat::humanAvailable());
    }

    public function test_profile_changes_the_password_without_keeping_it(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        Livewire::test(Profile::class)
            ->set('current_password', 'nope')->set('password', 'n3w-password')->set('password_confirmation', 'n3w-password')
            ->call('savePassword')
            ->assertHasErrors('current_password')
            ->assertSet('password', '');

        Livewire::test(Profile::class)
            ->set('current_password', 'secret-password')->set('password', 'n3w-password')->set('password_confirmation', 'n3w-password')
            ->call('savePassword')
            ->assertHasNoErrors();
        $this->assertTrue(Hash::check('n3w-password', $admin->fresh()->password));
    }

    public function test_the_password_needs_twelve_characters_and_the_email_needs_the_password(): void
    {
        config(['larachat.admins' => ['edu@acme.test', 'edu@other.test']]);
        $admin = $this->admin();
        $this->actingAs($admin);

        Livewire::test(Profile::class)
            ->set('current_password', 'secret-password')->set('password', 'short')->set('password_confirmation', 'short')
            ->call('savePassword')->assertHasErrors(['password' => 'min']);

        Livewire::test(Profile::class)->set('email', 'edu@other.test')->call('saveProfile')
            ->assertHasErrors('email_password');
        Livewire::test(Profile::class)->set('email', 'edu@other.test')->set('email_password', 'wrong')->call('saveProfile')
            ->assertHasErrors('email_password')->assertSet('email_password', '');
        Livewire::test(Profile::class)->set('email', 'stranger@acme.test')->set('email_password', 'secret-password')->call('saveProfile')
            ->assertHasErrors('email');
        $this->assertSame('edu@acme.test', $admin->fresh()->email);

        Livewire::test(Profile::class)->set('name', 'Edu L')->call('saveProfile')->assertHasNoErrors();
        Livewire::test(Profile::class)->set('email', 'edu@other.test')->set('email_password', 'secret-password')->call('saveProfile')
            ->assertHasNoErrors();
        $this->assertSame('edu@other.test', $admin->fresh()->email);
    }

    public function test_changing_ip_does_not_buy_more_guesses_at_the_same_email(): void
    {
        $this->admin();
        foreach (range(1, 10) as $i) {
            \Illuminate\Support\Facades\RateLimiter::hit('larachat-login-email:edu@acme.test', 900);
        }

        Livewire::test(Login::class)
            ->set('email', 'edu@acme.test')->set('password', 'secret-password')
            ->call('login')
            ->assertHasErrors('email');
        $this->assertGuest();
    }

    public function test_logout(): void
    {
        $this->actingAs($this->admin());

        $this->post('/larachat/logout')->assertRedirect('/larachat');
        $this->assertGuest();
    }
}
