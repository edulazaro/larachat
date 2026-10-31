<?php

namespace EduLazaro\Larachat\Http\Controllers;

use EduLazaro\Larachat\Larachat;
use EduLazaro\Larachat\Models\ChatSession;
use EduLazaro\Larachat\Notifications\Telegram;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

/**
 * Lets the owner answer from Telegram: a Reply to a forwarded message goes to
 * the visitor, "/ai 123" hands a conversation back to the bot, and the buttons
 * under each alert switch it either way. Only the configured chat is obeyed.
 */
class TelegramWebhookController extends Controller
{
    public function __invoke(Request $request, Telegram $telegram): JsonResponse
    {
        // Without a secret anyone who knows the chat id could forge "admin"
        // replies to visitors, so there is no unprotected mode.
        $secret = (string) config('larachat.notifications.telegram.webhook_secret');
        if ($secret === '' || ! hash_equals($secret, (string) $request->header('X-Telegram-Bot-Api-Secret-Token', ''))) {
            Log::warning('Larachat: Telegram webhook with a missing or wrong secret token', ['ip' => $request->ip()]);

            return response()->json(['error' => 'forbidden'], 403);
        }

        if ($callback = $request->input('callback_query')) {
            return $this->callback($callback, $telegram);
        }

        $message = $request->input('message');
        if (! is_array($message)) {
            return response()->json(['ok' => true]);
        }

        $chatId = (string) ($message['chat']['id'] ?? '');
        $text = trim((string) ($message['text'] ?? ''));

        // Anyone else who opens the bot is ignored; on /start the owner is told who it was.
        if ($chatId !== $telegram->chatId()) {
            if ($text === '/start') {
                $this->stranger($message, $telegram);
            }

            return response()->json(['ok' => true]);
        }

        if ($text === '/start') {
            $telegram->send($this->t('tg_configured', ['chat' => Telegram::e($chatId)]));

            return response()->json(['ok' => true]);
        }

        if ($text === '/chatid') {
            $telegram->send($this->t('tg_chat_id', ['chat' => Telegram::e($chatId)]));

            return response()->json(['ok' => true]);
        }

        if (preg_match('/^\/ai\s+(\d+)$/i', $text, $m)) {
            $this->toAi((int) $m[1], $telegram);

            return response()->json(['ok' => true]);
        }

        $original = (string) ($message['reply_to_message']['text'] ?? '');
        if ($original === '') {
            $telegram->send($this->t('tg_how'));

            return response()->json(['ok' => true]);
        }

        if (! preg_match('/#(\d+)/', $original, $m)) {
            $telegram->send($this->t('tg_no_ref'));

            return response()->json(['ok' => true]);
        }

        $session = ChatSession::find((int) $m[1]);
        if (! $session) {
            $telegram->send($this->t('tg_not_found', ['id' => (int) $m[1]]));

            return response()->json(['ok' => true]);
        }

        if ($session->status === 'closed') {
            $telegram->send($this->t('tg_closed', ['id' => $session->id]));

            return response()->json(['ok' => true]);
        }

        if ($text === '') {
            return response()->json(['ok' => true]);
        }

        $wasAi = $session->mode === 'ai';
        $session->messages()->create(['sender' => 'admin', 'message' => $text]);
        $session->update([
            'mode' => 'human',
            'last_message_at' => now(),
            'unread_count' => $session->unread_count + 1,
        ]);

        $telegram->send(
            $this->t($wasAi ? 'tg_now_human' : 'tg_sent', ['id' => $session->id]),
            [['text' => $this->t('tg_back', ['bot' => Larachat::persona()->name()]), 'callback_data' => 'ai:' . $session->id]]
        );

        return response()->json(['ok' => true]);
    }

    /** @param  array<string, mixed>  $callback */
    protected function callback(array $callback, Telegram $telegram): JsonResponse
    {
        $data = (string) ($callback['data'] ?? '');
        $id = (string) ($callback['id'] ?? '');

        // Buttons pressed elsewhere (a forwarded alert) are not obeyed either.
        if ((string) ($callback['message']['chat']['id'] ?? $callback['from']['id'] ?? '') !== $telegram->chatId()) {
            return response()->json(['ok' => true]);
        }

        if (preg_match('/^ai:(\d+)$/', $data, $m)) {
            $telegram->answerCallback($id, $this->t('tg_switching_ai'));
            $this->toAi((int) $m[1], $telegram);

            return response()->json(['ok' => true]);
        }

        if (preg_match('/^human:(\d+)$/', $data, $m)) {
            $session = ChatSession::find((int) $m[1]);
            if (! $session) {
                $telegram->answerCallback($id, strip_tags($this->t('tg_not_found', ['id' => (int) $m[1]])));

                return response()->json(['ok' => true]);
            }

            $session->update(['mode' => 'human']);
            $telegram->answerCallback($id, $this->t('tg_human_on'));
            $telegram->send(
                $this->t('tg_now_human', ['id' => $session->id]),
                [['text' => $this->t('tg_back', ['bot' => Larachat::persona()->name()]), 'callback_data' => 'ai:' . $session->id]]
            );

            return response()->json(['ok' => true]);
        }

        $telegram->answerCallback($id, $this->t('tg_unknown'));

        return response()->json(['ok' => true]);
    }

    protected function toAi(int $sessionId, Telegram $telegram): void
    {
        $session = ChatSession::find($sessionId);
        if (! $session) {
            $telegram->send($this->t('tg_not_found', ['id' => $sessionId]));

            return;
        }

        $session->update(['mode' => 'ai']);
        // The bot says it is back, so the visitor knows who answers now.
        $session->messages()->create([
            'sender' => 'assistant',
            'message' => Larachat::persona()->text('back_to_ai', $session->locale()),
        ]);
        $session->update(['unread_count' => $session->unread_count + 1, 'last_message_at' => now()]);

        $telegram->send(
            $this->t('tg_now_ai', ['id' => $session->id]),
            [['text' => $this->t('tg_take', ['owner' => Larachat::persona()->owner()]), 'callback_data' => 'human:' . $session->id]]
        );
    }

    /** @param  array<string, mixed>  $message */
    protected function stranger(array $message, Telegram $telegram): void
    {
        $from = $message['from'] ?? $message['chat'] ?? [];
        $name = trim(($from['first_name'] ?? '') . ' ' . ($from['last_name'] ?? ''));

        $telegram->send($this->t('tg_stranger', [
            'name' => $name !== '' ? Telegram::e($name) : $this->t('tg_no_name'),
            'user' => ! empty($from['username']) ? '@' . Telegram::e($from['username']) : $this->t('tg_no_user'),
            'lang' => Telegram::e((string) ($from['language_code'] ?? '?')),
            'chat' => Telegram::e((string) ($message['chat']['id'] ?? '?')),
        ]));
    }

    /** @param  array<string, mixed>  $replace */
    protected function t(string $key, array $replace = []): string
    {
        return (string) __('larachat::panel.' . $key, $replace, config('larachat.panel.locale', 'es'));
    }
}
