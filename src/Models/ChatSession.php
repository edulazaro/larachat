<?php

namespace EduLazaro\Larachat\Models;

use EduLazaro\Larachat\Larachat;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class ChatSession extends Model
{
    protected $table = 'larachat_sessions';

    protected $fillable = [
        'session_token', 'visitor_name', 'visitor_email', 'visitor_phone', 'visitor_ip',
        'visitor_page', 'telegram_chat_id', 'status', 'mode',
        'detected_locale', 'unread_count', 'last_message_at',
        'compressed_summary', 'compressed_until_id', 'blocked_at', 'qualified_at', 'contact_forms',
    ];

    protected $attributes = [
        'status' => 'open',
        'mode' => 'ai',
        'unread_count' => 0,
        'contact_forms' => 0,
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
        'blocked_at' => 'datetime',
        'qualified_at' => 'datetime',
        'contact_forms' => 'integer',
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }

    /** The last thing either side said: a contact form is not a message to preview. */
    public function latestMessage(): HasOne
    {
        return $this->hasOne(ChatMessage::class)->ofMany(
            ['id' => 'max'],
            fn ($query) => $query->where(fn ($q) => $q->whereNull('kind')->orWhere('kind', '!=', 'contact_form'))
        );
    }

    public function hasContact(): bool
    {
        return (bool) ($this->visitor_email || $this->visitor_phone);
    }

    public function locale(): string
    {
        return $this->detected_locale ?? Larachat::locale($this->visitor_page);
    }

    public function closedMessage(): string
    {
        return Larachat::persona()->text('closed', $this->locale());
    }

    /**
     * Ends the conversation for good: the visitor gets a closing message and
     * their IP cannot open a new one for the configured number of days.
     */
    public function block(): void
    {
        $this->messages()->create(['sender' => 'assistant', 'message' => $this->closedMessage()]);
        $this->update(['status' => 'closed', 'mode' => 'ai', 'blocked_at' => now(), 'last_message_at' => now()]);
    }

    public function unblock(): void
    {
        $this->update(['status' => 'open', 'blocked_at' => null]);
    }

    public static function ipBlocked(?string $ip): bool
    {
        return $ip && static::where('visitor_ip', $ip)
            ->where('blocked_at', '>=', now()->subDays((int) config('larachat.limits.ip_block_days', 30)))
            ->exists();
    }

    public static function generateToken(): string
    {
        return Str::random(48);
    }
}
