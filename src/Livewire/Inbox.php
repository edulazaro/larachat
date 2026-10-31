<?php

namespace EduLazaro\Larachat\Livewire;

use EduLazaro\Larachat\Larachat;
use EduLazaro\Larachat\Models\ChatMessage;
use EduLazaro\Larachat\Models\ChatSession;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The conversations: the list on the left, the open one on the right. Polled
 * every 5 seconds, which is also the heartbeat that keeps the owner counted
 * as available while the panel is open.
 */
#[Layout('larachat::layouts.panel')]
class Inbox extends Component
{
    #[Url(as: 'c')]
    public ?int $open = null;

    #[Url]
    public string $filter = 'all';

    public string $reply = '';

    public bool $available = false;

    public int $lastVisitorMessageId = 0;

    public function boot(): void
    {
        // Every request, not only the page load: Livewire's later requests
        // do not go through the panel's route middleware.
        abort_unless(Larachat::isAdmin(Auth::user()), 404);

        app()->setLocale(config('larachat.panel.locale', 'es'));
    }

    public function mount(): void
    {
        $this->available = (bool) Auth::user()->is_available;
        $this->seen();
        $this->lastVisitorMessageId = (int) ChatMessage::where('sender', 'visitor')->max('id');
    }

    public function heartbeat(): void
    {
        $this->seen();

        $latest = (int) ChatMessage::where('sender', 'visitor')->max('id');
        if ($latest > $this->lastVisitorMessageId) {
            $this->lastVisitorMessageId = $latest;
            $this->dispatch('chat-new');
        }
    }

    public function updatedAvailable(): void
    {
        Auth::user()->forceFill(['is_available' => $this->available, 'last_seen_at' => now()])->save();
    }

    public function select(int $id): void
    {
        $this->open = $id;
        $this->reply = '';
    }

    public function close(): void
    {
        $this->open = null;
    }

    public function send(): void
    {
        $session = $this->session();
        $this->validate(['reply' => 'required|string|max:2000']);

        if (! $session || $session->status === 'closed') {
            return;
        }

        $session->messages()->create(['sender' => 'admin', 'message' => trim($this->reply)]);
        $session->update(['mode' => 'human', 'last_message_at' => now()]);
        $this->reply = '';
        $this->dispatch('reply-sent');
    }

    public function take(): void
    {
        $this->session()?->update(['mode' => 'human']);
    }

    public function release(): void
    {
        $this->session()?->update(['mode' => 'ai']);
    }

    public function block(): void
    {
        $session = $this->session();
        if ($session && $session->status !== 'closed') {
            $session->block();
        }
    }

    public function unblock(): void
    {
        $this->session()?->unblock();
    }

    public function deleteConversation(): void
    {
        $this->session()?->delete();
        $this->open = null;
        $this->reply = '';
    }

    public function label(ChatSession $session): string
    {
        return $session->visitor_name
            ?: ($session->visitor_email ?: ($session->visitor_phone ?: __('larachat::panel.visitor', ['id' => $session->id])));
    }

    public function render()
    {
        $unread = fn ($q) => $q->where('sender', 'visitor')->where('is_read', false);

        $sessions = ChatSession::query()
            ->whereHas('messages', fn ($q) => $q->where('sender', 'visitor'))
            ->when($this->filter === 'human', fn ($q) => $q->where('mode', 'human')->where('status', 'open'))
            ->when($this->filter === 'unread', fn ($q) => $q->whereHas('messages', $unread))
            ->when($this->filter === 'blocked', fn ($q) => $q->whereNotNull('blocked_at'))
            ->when($this->filter === 'leads', fn ($q) => $q->where(fn ($q) => $q->whereNotNull('visitor_email')->orWhereNotNull('visitor_phone')))
            ->withCount(['messages as unread' => $unread])
            ->with('latestMessage')
            ->orderByDesc('last_message_at')
            ->limit(100)
            ->get();

        $current = $this->session();
        $messages = collect();
        if ($current) {
            $current->messages()->where('sender', 'visitor')->where('is_read', false)->update(['is_read' => true]);
            $messages = $current->messages()->orderBy('id')->get();
        }

        return view('larachat::livewire.inbox', [
            'sessions' => $sessions,
            'current' => $current,
            'messages' => $messages,
            'site' => Larachat::persona()->site(),
        ])->title(__('larachat::panel.conversations'));
    }

    protected function session(): ?ChatSession
    {
        return $this->open ? ChatSession::find($this->open) : null;
    }

    protected function seen(): void
    {
        Auth::user()->forceFill(['last_seen_at' => now()])->save();
    }
}
