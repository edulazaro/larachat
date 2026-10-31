<?php

namespace EduLazaro\Larachat\Console;

use EduLazaro\Larachat\Notifications\Telegram;
use Illuminate\Console\Command;

class TelegramCommand extends Command
{
    protected $signature = 'larachat:telegram {action=info : set, remove or info}';

    protected $description = 'Register, remove or inspect the Telegram webhook that lets you answer from Telegram';

    public function handle(Telegram $telegram): int
    {
        if (! config('larachat.notifications.telegram.token')) {
            $this->error('No Telegram token (larachat.notifications.telegram.token).');

            return self::FAILURE;
        }

        $result = match ($this->argument('action')) {
            'set' => $this->set($telegram),
            'remove' => $telegram->removeWebhook(),
            default => $telegram->webhookInfo(),
        };

        if ($result === null) {
            return self::FAILURE;
        }

        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return ($result['ok'] ?? false) ? self::SUCCESS : self::FAILURE;
    }

    /** @return array<string, mixed>|null */
    protected function set(Telegram $telegram): ?array
    {
        if (! config('larachat.notifications.telegram.webhook_secret')) {
            $this->error('Set larachat.notifications.telegram.webhook_secret first: the webhook refuses every update without it.');

            return null;
        }

        $url = route('larachat.telegram');
        $this->info('Webhook: ' . $url);

        return $telegram->setWebhook($url);
    }
}
