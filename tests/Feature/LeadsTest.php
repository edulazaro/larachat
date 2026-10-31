<?php

namespace EduLazaro\Larachat\Tests\Feature;

use EduLazaro\Larachat\Leads;
use EduLazaro\Larachat\Livewire\Inbox;
use EduLazaro\Larachat\Models\ChatSession;
use EduLazaro\Larachat\Tests\Fixtures\FakesOpenAi;
use EduLazaro\Larachat\Tests\TestCase;
use Livewire\Livewire;

class LeadsTest extends TestCase
{
    use FakesOpenAi;

    protected function startChat(string $page = '/es'): string
    {
        return $this->postJson('/api/chat/init', ['token' => null, 'page' => $page])->json('token');
    }

    protected function say(string $token, string $message)
    {
        return $this->postJson('/api/chat/send', ['token' => $token, 'message' => $message, 'page' => '/es'])->assertOk();
    }

    /** @return list<array<string, mixed>> the forms the widget would draw on a reload */
    protected function openForms(string $token): array
    {
        return array_values(array_filter(
            $this->postJson('/api/chat/init', ['token' => $token, 'page' => '/es'])->json('messages'),
            fn ($m) => $m['kind'] === Leads::FORM
        ));
    }

    /**
     * The conversation that lost a perfect lead on kenodo.com (2026-10-31): a
     * streaming platform for horror films, a question about the price, "all of
     * it", and a goodbye. The form now appears on interest, on the price
     * question and on the goodbye, never twice at once, and never more than
     * three times.
     */
    public function test_the_lead_that_got_away_is_asked_at_every_moment_that_matters(): void
    {
        $this->fakeOpenAi([
            ['reply' => '¡Hola! ¿En qué puedo ayudarte?'],
            ['reply' => 'Desarrollamos plataformas de streaming.'],
            ['reply' => 'Una plataforma de terror suena muy bien.', 'qualified' => true],
            ['reply' => 'El precio depende del alcance.', 'qualified' => true],
            ['reply' => '¡Perfecto!', 'qualified' => true],
            ['reply' => '¡Genial!', 'qualified' => true],
            ['reply' => 'Tener los derechos ayuda.', 'qualified' => true],
            ['reply' => '¡Hasta luego!', 'qualified' => true],
            ['reply' => 'Adiós.', 'qualified' => true],
        ]);
        config(['larachat.limits.send_per_minute' => 100]);
        $token = $this->startChat();

        $turns = [
            'hola tio' => null,
            'que sabes del streaming?' => null,
            'una plataforma de streaming como lo ves? de pelis de terror' => 'interest',
            'me darias precio?' => 'price',
            'ok' => null,
            'todo' => null,
            'ok si' => null,
            'vale, venga chao' => 'goodbye',
            'adiós' => null,
        ];

        foreach ($turns as $message => $reason) {
            $response = $this->say($token, $message);
            $this->assertStringNotContainsString('privacy', (string) $response->json('ai_reply'), 'the reply itself never asks for details');

            if ($reason === null) {
                $this->assertNull($response->json('form'), "no form after \"{$message}\"");
            } else {
                $this->assertSame(__('larachat::chat.form_' . $reason, ['owner' => 'Edu'], 'es'), $response->json('form.title'), "form after \"{$message}\"");
                $this->assertCount(1, $this->openForms($token), 'never two forms open at once');
            }
        }

        $session = ChatSession::first();
        $this->assertNotNull($session->qualified_at);
        $this->assertSame(3, $session->contact_forms);

        // Filled in: the form goes, the details stay, and no form comes back.
        $this->postJson('/api/chat/contact', ['token' => $token, 'name' => 'Laura', 'email' => 'laura@terror.test'])->assertOk();
        $this->assertSame([], $this->openForms($token));
        $this->assertTrue($session->fresh()->hasContact());
    }

    public function test_a_question_about_the_price_is_interest_on_its_own(): void
    {
        $this->fakeOpenAi(['reply' => 'Depende del alcance.']);
        $token = $this->startChat();

        $response = $this->say($token, '¿Cuánto cuesta una app como esta?');

        $this->assertSame(__('larachat::chat.form_price', ['owner' => 'Edu'], 'es'), $response->json('form.title'));
        $this->assertTrue($response->json('lead'));
        $this->assertNotNull(ChatSession::first()->qualified_at);
    }

    public function test_the_widget_asks_for_a_form_when_an_interested_visitor_closes_the_chat_or_nobody_answers(): void
    {
        $this->fakeOpenAi(['reply' => 'Claro.']);
        $token = $this->startChat();
        $this->say($token, 'Hola');

        $this->postJson('/api/chat/contact-form', ['token' => $token, 'reason' => 'close'])->assertOk()->assertJsonPath('form', null);

        ChatSession::first()->update(['qualified_at' => now()]);
        $this->postJson('/api/chat/contact-form', ['token' => $token, 'reason' => 'close'])->assertOk()
            ->assertJsonPath('form.title', __('larachat::chat.form_close', ['owner' => 'Edu'], 'es'));
        $this->postJson('/api/chat/contact-form', ['token' => $token, 'reason' => 'close'])->assertOk()->assertJsonPath('form', null);

        $other = $this->startChat();
        $this->postJson('/api/chat/contact-form', ['token' => $other, 'reason' => 'wait'])->assertOk()->assertJsonPath('form', null);
        ChatSession::where('session_token', $other)->first()->update(['mode' => 'human']);
        $this->postJson('/api/chat/contact-form', ['token' => $other, 'reason' => 'wait'])->assertOk()
            ->assertJsonPath('form.title', __('larachat::chat.form_wait', ['owner' => 'Edu'], 'es'));
    }

    public function test_forms_are_not_sent_to_the_model_as_something_anybody_said(): void
    {
        $this->fakeOpenAi(['reply' => 'Te preparamos un presupuesto.', 'qualified' => true]);
        $token = $this->startChat();

        $this->say($token, 'Queremos una plataforma de reservas');
        $this->say($token, 'Para tres hoteles');

        $last = collect($this->sent)->where('step', 'reply')->last()['body']['messages'];
        $said = implode("\n", array_map(fn ($m) => is_string($m['content']) ? $m['content'] : '', $last));
        $this->assertStringNotContainsString(__('larachat::chat.form_interest', ['owner' => 'Edu'], 'es'), $said);
    }

    public function test_the_inbox_shows_who_got_away_and_where_the_form_appeared(): void
    {
        $this->actingAs($this->admin());
        $gone = ChatSession::create(['session_token' => str_repeat('a', 48), 'qualified_at' => now(), 'last_message_at' => now()]);
        $gone->messages()->create(['sender' => 'visitor', 'message' => 'Quiero una plataforma']);
        app(Leads::class)->place($gone, 'price');
        $kept = ChatSession::create(['session_token' => str_repeat('b', 48), 'qualified_at' => now(), 'visitor_email' => 'ok@acme.test', 'last_message_at' => now()]);
        $kept->messages()->create(['sender' => 'visitor', 'message' => 'Hola']);

        Livewire::test(Inbox::class)->set('filter', 'interested')
            ->assertSee('Quiero una plataforma')->assertDontSee('ok@acme.test');

        $this->get('/larachat?c=' . $gone->id)->assertOk()
            ->assertSee(__('larachat::panel.form_shown'))->assertSee(__('larachat::panel.form_reason_price'));
    }

    public function test_the_form_refuses_strange_characters(): void
    {
        config(['larachat.limits.switch_per_minute' => 100]);
        $token = $this->startChat();
        $send = fn (array $fields) => $this->postJson('/api/chat/contact', ['token' => $token] + $fields);

        $send(['name' => 'Laura 123', 'email' => 'laura@acme.test'])->assertStatus(422)->assertJsonPath('errors.name.0', 'Escribe solo letras en el nombre.');
        $send(['name' => '<script>', 'email' => 'laura@acme.test'])->assertStatus(422)->assertJsonPath('errors.name.0', 'Escribe solo letras en el nombre.');
        $send(['name' => 'Laura', 'email' => 'laura@acme.test', 'phone' => 'sesenta'])->assertStatus(422)->assertJsonPath('errors.phone.0', 'Ese teléfono no parece correcto.');
        $send(['name' => 'Laura', 'email' => 'laura@acme.test', 'phone' => '.......'])->assertStatus(422)->assertJsonPath('errors.phone.0', 'Ese teléfono no parece correcto.');
        $send(['name' => 'Laura', 'email' => 'laura@acme.test', 'phone' => '600 11'])->assertStatus(422);

        $send(['name' => "María-José O'Neill", 'email' => 'mj@acme.test', 'phone' => '+376 (600) 111-222'])->assertOk();
    }

    public function test_prices_and_goodbyes_are_recognised_in_every_language(): void
    {
        $leads = app(Leads::class);

        foreach (['¿me darías precio?', '¿Cuánto cuesta?', 'what would it cost', 'Do you have a quote?', 'quin és el pressupost?', 'Quel est le prix ?', 'plazos de entrega'] as $text) {
            $this->assertTrue($leads->asksAboutPrice($text), $text);
        }
        foreach (['vale, venga chao', 'Adiós', 'hasta luego', 'ok bye', 'adéu', 'au revoir'] as $text) {
            $this->assertTrue($leads->saysGoodbye($text), $text);
        }
        foreach (['hola tio', 'que sabes del streaming?', 'todo'] as $text) {
            $this->assertFalse($leads->asksAboutPrice($text) || $leads->saysGoodbye($text), $text);
        }
    }
}
