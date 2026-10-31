<?php

namespace EduLazaro\Larachat\Tests\Feature;

use EduLazaro\Larachat\Models\ChatSession;
use EduLazaro\Larachat\Tests\TestCase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

class TelegramTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'larachat.notifications.telegram.enabled' => true,
            'larachat.notifications.telegram.token' => 'TOKEN',
            'larachat.notifications.telegram.chat_id' => '42',
            'larachat.notifications.telegram.webhook_secret' => 's3cret',
        ]);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
    }

    protected function hook(array $update, ?string $secret = 's3cret')
    {
        return $this->postJson('/larachat/telegram/webhook', $update, $secret === null ? [] : ['X-Telegram-Bot-Api-Secret-Token' => $secret]);
    }

    protected function chatSession(string $mode = 'ai'): ChatSession
    {
        return ChatSession::create(['session_token' => ChatSession::generateToken(), 'visitor_page' => '/es', 'mode' => $mode]);
    }

    public function test_updates_without_the_secret_are_refused(): void
    {
        $this->hook(['message' => ['chat' => ['id' => 42], 'text' => '/start']], null)->assertForbidden();
        $this->hook(['message' => ['chat' => ['id' => 42], 'text' => '/start']], 'wrong')->assertForbidden();

        config(['larachat.notifications.telegram.webhook_secret' => null]);
        $this->hook(['message' => ['chat' => ['id' => 42], 'text' => '/start']], '')->assertForbidden();
    }

    public function test_a_reply_from_the_owner_reaches_the_visitor(): void
    {
        $session = $this->chatSession();

        $this->hook(['message' => [
            'chat' => ['id' => 42],
            'text' => 'Hola, soy Edu',
            'reply_to_message' => ['text' => "💬 Chat #{$session->id}\n\n👤 Hola"],
        ]])->assertOk();

        $this->assertSame('human', $session->fresh()->mode);
        $this->assertSame('Hola, soy Edu', $session->messages()->where('sender', 'admin')->value('message'));
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'sendMessage') && str_contains($r['text'], "#{$session->id}"));
    }

    public function test_strangers_are_ignored_and_reported(): void
    {
        $session = $this->chatSession();

        $this->hook(['message' => ['chat' => ['id' => 7], 'from' => ['first_name' => 'Mallory <x>'], 'text' => '/start']])->assertOk();
        $this->hook(['message' => ['chat' => ['id' => 7], 'text' => 'hi', 'reply_to_message' => ['text' => "#{$session->id}"]]])->assertOk();

        $this->assertSame(0, $session->messages()->count());
        Http::assertSent(fn (Request $r) => str_contains($r['text'] ?? '', 'Mallory &lt;x&gt;'));
    }

    public function test_ai_command_and_buttons_switch_the_conversation(): void
    {
        $session = $this->chatSession('human');

        $this->hook(['message' => ['chat' => ['id' => 42], 'text' => "/ai {$session->id}"]])->assertOk();
        $this->assertSame('ai', $session->fresh()->mode);
        $this->assertStringContainsString('Edu', $session->messages()->where('sender', 'assistant')->value('message'));

        $this->hook(['callback_query' => ['id' => 'cb', 'data' => "human:{$session->id}", 'message' => ['chat' => ['id' => 42]]]])->assertOk();
        $this->assertSame('human', $session->fresh()->mode);

        // A button pressed from another chat is not obeyed.
        $this->hook(['callback_query' => ['id' => 'cb', 'data' => "ai:{$session->id}", 'message' => ['chat' => ['id' => 7]]]])->assertOk();
        $this->assertSame('human', $session->fresh()->mode);
    }

    public function test_visitor_messages_while_human_are_forwarded_escaped(): void
    {
        $token = $this->postJson('/api/chat/init', ['page' => '/'])->json('token');
        ChatSession::first()->update(['mode' => 'human']);
        $this->admin()->forceFill(['is_available' => true, 'last_seen_at' => now()])->save();

        $this->postJson('/api/chat/send', ['token' => $token, 'message' => 'Is <b>this</b> & that ok?', 'page' => '/'])->assertOk();

        Http::assertSent(fn (Request $r) => str_contains($r['text'] ?? '', 'Is &lt;b&gt;this&lt;/b&gt; &amp; that ok?') && $r['parse_mode'] === 'HTML');
    }
}
