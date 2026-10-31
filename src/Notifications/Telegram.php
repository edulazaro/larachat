<?php

namespace EduLazaro\Larachat\Notifications;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * The few Telegram Bot API calls the chat needs. Messages are sent as HTML:
 * anything the visitor wrote must go through e() first, or a stray "<" makes
 * Telegram reject the whole message.
 */
class Telegram
{
    public function configured(): bool
    {
        return (bool) config('larachat.notifications.telegram.enabled')
            && $this->token() !== ''
            && $this->chatId() !== '';
    }

    public function chatId(): string
    {
        return (string) config('larachat.notifications.telegram.chat_id');
    }

    /**
     * @param  list<array{text: string, callback_data: string}>  $buttons
     */
    public function send(string $html, array $buttons = []): bool
    {
        if (! $this->configured()) {
            return false;
        }

        $payload = [
            'chat_id' => $this->chatId(),
            'text' => $html,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
        ];
        if ($buttons) {
            $payload['reply_markup'] = json_encode(['inline_keyboard' => [$buttons]]);
        }

        $response = Http::timeout(10)->post($this->api('sendMessage'), $payload);
        if (! $response->successful()) {
            Log::error('Larachat: Telegram sendMessage failed', ['status' => $response->status(), 'body' => $response->body()]);
        }

        return $response->successful();
    }

    public function answerCallback(string $callbackId, string $text = ''): bool
    {
        return Http::timeout(10)->post($this->api('answerCallbackQuery'), [
            'callback_query_id' => $callbackId,
            'text' => $text,
        ])->successful();
    }

    /** @return array<string, mixed> */
    public function setWebhook(string $url): array
    {
        return Http::post($this->api('setWebhook'), array_filter([
            'url' => $url,
            'allowed_updates' => ['message', 'callback_query'],
            'secret_token' => config('larachat.notifications.telegram.webhook_secret') ?: null,
        ]))->json() ?? [];
    }

    /** @return array<string, mixed> */
    public function removeWebhook(): array
    {
        return Http::post($this->api('deleteWebhook'))->json() ?? [];
    }

    /** @return array<string, mixed> */
    public function webhookInfo(): array
    {
        return Http::get($this->api('getWebhookInfo'))->json() ?? [];
    }

    public static function e(?string $text): string
    {
        return htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    protected function token(): string
    {
        return (string) config('larachat.notifications.telegram.token');
    }

    protected function api(string $method): string
    {
        return 'https://api.telegram.org/bot' . $this->token() . '/' . $method;
    }
}
