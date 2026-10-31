<?php

namespace EduLazaro\Larachat\Notifications;

use EduLazaro\Larachat\Larachat;
use EduLazaro\Larachat\Models\ChatSession;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Tells the owner what needs them, by email and/or Telegram, after the
 * response has gone out so the visitor's reply is never delayed. The panel
 * has every conversation anyway; this is only the nudge.
 */
class Notifier
{
    public function __construct(protected Telegram $telegram)
    {
    }

    /** The first message of a conversation. */
    public function newConversation(ChatSession $session, string $firstMessage): void
    {
        if (! $this->withinAlertCap($session)) {
            return;
        }

        $this->mail($session, $this->t('mail_new_subject', ['id' => $session->id]), [
            $this->t('mail_new_intro', ['site' => Larachat::persona()->site()]),
            '',
            $this->t('mail_first') . ': ' . $firstMessage,
            $this->t('mail_page') . ': ' . ($session->visitor_page ?: '-'),
            $this->t('mail_locale') . ': ' . $session->locale(),
        ]);

        // With every exchange forwarded, the first one already says it is new.
        if (! $this->allMessages()) {
            $this->telegram($session, $this->t('tg_new', ['id' => $session->id]) . $this->where($session)
                . "\n\n👤 " . Telegram::e($firstMessage), 'human');
        }
    }

    /** A reply from the bot, forwarded only when the site asks for every exchange. */
    public function exchange(ChatSession $session, string $visitorMessage, string $reply, bool $qualified, bool $first): void
    {
        if (! $this->allMessages()) {
            return;
        }

        // The first sign of interest has its own alert (interested()), sent once.
        $body = "\n\n👤 " . Telegram::e($visitorMessage) . "\n🤖 " . Telegram::e($reply);
        $this->telegram($session, ($first
            ? $this->t('tg_new', ['id' => $session->id]) . $this->where($session)
            : $this->t('tg_chat', ['id' => $session->id])) . $body, 'human');
    }

    /** The visitor asked for a person and the conversation is now yours. */
    public function handoff(ChatSession $session, string $visitorMessage): void
    {
        $this->telegram($session, $this->t('tg_handoff', ['id' => $session->id, 'owner' => Telegram::e(Larachat::persona()->owner())])
            . "\n\n👤 " . Telegram::e($visitorMessage), 'ai');
    }

    /** The model could not be reached and the conversation went to a person. */
    public function aiFailed(ChatSession $session, string $visitorMessage): void
    {
        $this->telegram($session, $this->t('tg_failed', ['id' => $session->id]) . $this->where($session)
            . "\n\n👤 " . Telegram::e($visitorMessage), 'ai');
    }

    /** A visitor message while you have the conversation, with the last few for context. */
    public function visitorMessage(ChatSession $session, string $visitorMessage, int $messageId): void
    {
        $recent = $session->messages()->where('id', '<', $messageId)->orderByDesc('id')->take(10)->get(['sender', 'message'])->reverse();
        $lines = $recent->map(fn ($m) => ($m->sender === 'visitor' ? '👤 ' : '🔵 ') . Telegram::e($m->message))->implode("\n");

        $this->telegram($session, $this->t('tg_chat', ['id' => $session->id]) . $this->where($session) . "\n\n"
            . ($lines !== '' ? $lines . "\n" : '') . '👤 ' . Telegram::e($visitorMessage) . ' ' . $this->t('tg_new_marker'), 'ai');
    }

    /**
     * The first sign of real interest in a conversation that has no contact
     * details yet: the owner can step in while the visitor is still there.
     */
    public function interested(ChatSession $session, string $visitorMessage): void
    {
        if (! $this->withinAlertCap($session)) {
            return;
        }

        $this->mail($session, $this->t('mail_interested_subject', ['id' => $session->id]), [
            $this->t('mail_interested_intro'),
            '',
            $this->t('mail_last') . ': ' . $visitorMessage,
            $this->t('mail_page') . ': ' . ($session->visitor_page ?: '-'),
        ]);

        $this->telegram($session, $this->t('tg_qualified', ['id' => $session->id]) . $this->where($session)
            . "\n\n👤 " . Telegram::e($visitorMessage), 'human');
    }

    /** The visitor left a way to reach them. */
    public function lead(ChatSession $session): void
    {
        if (! $this->withinAlertCap($session)) {
            return;
        }

        $who = $session->visitor_name ?: ($session->visitor_email ?: $session->visitor_phone);

        $details = [
            $this->t('name') . ': ' . ($session->visitor_name ?: '-'),
            $this->t('email') . ': ' . ($session->visitor_email ?: '-'),
            $this->t('mail_phone') . ': ' . ($session->visitor_phone ?: '-'),
            $this->t('mail_page') . ': ' . ($session->visitor_page ?: '-'),
        ];

        $this->mail($session, $this->t('mail_lead_subject', ['id' => $session->id, 'who' => $who]), array_merge([
            $this->t('mail_lead_intro'),
            '',
        ], $details));

        $this->telegram($session, $this->t('tg_lead', ['id' => $session->id]) . "\n\n"
            . implode("\n", array_map([Telegram::class, 'e'], $details)), $session->mode === 'human' ? 'ai' : 'human');
    }

    /** Today's reply budget is spent: one email that day, whatever happens after. */
    public function budgetSpent(int $limit): void
    {
        $to = config('larachat.notifications.mail.to');
        if (! $to || ! Cache::add('larachat-budget-alert:' . now()->toDateString(), true, now()->addDays(2))) {
            return;
        }

        $subject = $this->t('mail_budget_subject', ['count' => $limit]);
        $body = $this->t('mail_budget_intro', ['count' => $limit]) . "\n\n" . $this->t('mail_open') . ': ' . route('larachat.panel');

        dispatch(function () use ($to, $subject, $body) {
            try {
                Mail::raw($body, fn ($message) => $message->to($to)->subject($subject));
            } catch (\Throwable $e) {
                Log::error('Larachat: budget alert failed', ['error' => $e->getMessage()]);
            }
        })->afterResponse();
    }

    /**
     * Alerts per visitor IP and day (new conversation, interested, lead), so a
     * script opening conversations cannot flood the inbox. Conversations you
     * are in (handoffs, messages while it is yours) are never capped.
     */
    public function withinAlertCap(ChatSession $session): bool
    {
        $key = 'larachat-alerts:' . ($session->visitor_ip ?: 'unknown') . ':' . now()->toDateString();
        Cache::add($key, 0, now()->addDays(2));

        return (int) Cache::increment($key) <= (int) config('larachat.notifications.mail.per_ip_per_day', 10);
    }

    /**
     * @param  list<string>  $lines
     */
    protected function mail(ChatSession $session, string $subject, array $lines): void
    {
        $to = config('larachat.notifications.mail.to');
        if (! $to) {
            return;
        }

        $lines[] = '';
        $lines[] = $this->t('mail_open') . ': ' . route('larachat.panel', ['c' => $session->id]);
        $body = implode("\n", $lines);

        dispatch(function () use ($to, $subject, $body, $session) {
            try {
                Mail::raw($body, fn ($message) => $message->to($to)->subject($subject));
            } catch (\Throwable $e) {
                Log::error('Larachat: email alert failed', ['session_id' => $session->id, 'error' => $e->getMessage()]);
            }
        })->afterResponse();
    }

    /**
     * @param  'human'|'ai'  $button  which way the button under the message moves the conversation
     */
    protected function telegram(ChatSession $session, string $html, string $button): void
    {
        if (! $this->telegram->configured()) {
            return;
        }

        $persona = Larachat::persona();
        $buttons = [$button === 'human'
            ? ['text' => $this->t('tg_take', ['owner' => $persona->owner()]), 'callback_data' => 'human:' . $session->id]
            : ['text' => $this->t('tg_back', ['bot' => $persona->name()]), 'callback_data' => 'ai:' . $session->id]];
        $telegram = $this->telegram;

        dispatch(function () use ($telegram, $html, $buttons) {
            $telegram->send($html, $buttons);
        })->afterResponse();
    }

    protected function where(ChatSession $session): string
    {
        return ($session->visitor_ip ? ' · <code>' . Telegram::e($session->visitor_ip) . '</code>' : '')
            . ($session->visitor_page ? "\n📄 <code>" . Telegram::e($session->visitor_page) . '</code>' : '');
    }

    protected function allMessages(): bool
    {
        return (bool) config('larachat.notifications.telegram.all_messages', false);
    }

    /** @param  array<string, mixed>  $replace */
    protected function t(string $key, array $replace = []): string
    {
        return (string) __('larachat::panel.' . $key, $replace, config('larachat.panel.locale', 'es'));
    }
}
