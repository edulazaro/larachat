<?php

namespace EduLazaro\Larachat\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessage extends Model
{
    protected $table = 'larachat_messages';

    protected $fillable = [
        'chat_session_id', 'sender', 'message', 'is_read', 'off_topic', 'sources',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'off_topic' => 'boolean',
        'sources' => 'array',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(ChatSession::class, 'chat_session_id');
    }
}
