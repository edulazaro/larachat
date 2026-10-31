<?php

namespace EduLazaro\Larachat\Http\Controllers;

use EduLazaro\Larachat\Chatbot;
use EduLazaro\Larachat\Guard;
use EduLazaro\Larachat\Larachat;
use EduLazaro\Larachat\Models\ChatSession;
use EduLazaro\Larachat\Notifications\Notifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * The widget's API: open or restore a conversation, send a message, leave
 * contact details, poll for replies and hand the conversation back to the bot.
 */
class ChatController extends Controller
{
    public function init(Request $request): JsonResponse
    {
        // Lenient: an unknown or malformed token just means a new conversation.
        $request->validate([
            'token' => 'nullable|string|max:128',
            'page' => 'nullable|string|max:512',
        ]);

        $token = $request->input('token');

        if ($token && strlen($token) === 48) {
            $session = ChatSession::where('session_token', $token)->first();
            if ($session) {
                $messages = $session->messages()
                    ->orderBy('created_at', 'asc')
                    ->get(['id', 'sender', 'message', 'created_at']);

                return response()->json([
                    'token' => $session->session_token,
                    'messages' => $messages,
                    'mode' => $session->mode,
                    'has_contact' => $session->hasContact(),
                ]);
            }
        }

        $session = ChatSession::create([
            'session_token' => ChatSession::generateToken(),
            'visitor_ip' => $request->ip(),
            'visitor_page' => $request->input('page'),
            'status' => ChatSession::ipBlocked($request->ip()) ? 'closed' : 'open',
        ]);

        return response()->json([
            'token' => $session->session_token,
            'messages' => [],
            'mode' => $session->mode,
        ]);
    }

    public function send(Request $request, Chatbot $chatbot, Guard $guard, Notifier $notifier): JsonResponse
    {
        $request->validate([
            'token' => 'required|string|size:48',
            'message' => 'required|string|max:' . (int) config('larachat.limits.message_chars', 1000),
            'page' => 'nullable|string|max:255|starts_with:/',
        ]);

        $session = ChatSession::where('session_token', $request->input('token'))->first();
        if (! $session) {
            return response()->json(['error' => 'Session not found', 'code' => 'session_not_found'], 404);
        }

        if ($session->status === 'closed') {
            return response()->json(['error' => $session->closedMessage()], 403);
        }

        $text = (string) $request->input('message');
        $visitorMessageCount = $session->messages()->where('sender', 'visitor')->count();

        if ($visitorMessageCount >= (int) config('larachat.limits.messages_per_session', 50)) {
            return response()->json(['error' => Larachat::persona()->text('session_limit', $session->locale())], 429);
        }

        // A conversation that opens with a single letter is not a visitor to be
        // told about: the alert is what an attacker is really spending, one per
        // session they open. The conversation still shows up in the panel.
        if ($visitorMessageCount === 0 && ! $guard->isNoise($text)) {
            $notifier->newConversation($session, $text);
        }

        $message = $session->messages()->create(['sender' => 'visitor', 'message' => $text]);

        $session->update([
            'last_message_at' => now(),
            'visitor_page' => $request->input('page', $session->visitor_page),
        ]);

        $response = ['id' => $message->id, 'created_at' => $message->created_at];

        // Nobody is at the panel any more: the bot picks the conversation back up.
        if ($session->mode === 'human' && ! Larachat::humanAvailable()) {
            $session->update(['mode' => 'ai']);
        }

        if ($session->mode === 'human') {
            $notifier->visitorMessage($session, $text, $message->id);
        } elseif ($chatbot->isEnabled()) {
            $dailyKey = 'larachat-ai-day:' . $request->ip();
            try {
                if (RateLimiter::tooManyAttempts($dailyKey, (int) config('larachat.limits.replies_per_ip_per_day', 60))) {
                    $result = ['reply' => $guard->refusal('limit', $session->locale())];
                } else {
                    RateLimiter::hit($dailyKey, 86400);
                    $result = $chatbot->generateResponse($session, $text);
                }
            } catch (\Exception $e) {
                Log::error('Larachat: AI error', ['session_id' => $session->id, 'error' => $e->getMessage()]);
                $result = null;
            }

            if ($result && ! empty($result['reply'])) {
                if (! empty($result['off_topic'])) {
                    $message->update(['off_topic' => true]);
                }
                $reply = $session->messages()->create([
                    'sender' => 'assistant',
                    'message' => $result['reply'],
                    'sources' => $result['sources'] ?? null,
                ]);

                $response['ai_reply'] = $result['reply'];
                $response['ask_contact'] = (bool) ($result['ask_contact'] ?? false);
                $response['ai_reply_id'] = $reply->id;
                $response['ai_reply_at'] = $reply->created_at;

                $notifier->exchange($session, $text, $result['reply'], (bool) ($result['qualified'] ?? false), $visitorMessageCount === 0);
            } elseif (Larachat::humanAvailable()) {
                $session->update(['mode' => 'human']);
                $notifier->aiFailed($session, $text);
            }
        }

        $session->refresh();
        $response['mode'] = $session->mode;
        $response['has_contact'] = $session->hasContact();

        return response()->json($response);
    }

    /** Contact details left through the widget's form when nobody can answer. */
    public function contact(Request $request, Notifier $notifier): JsonResponse
    {
        $data = $request->validate([
            'token' => 'required|string|size:48',
            'name' => 'nullable|string|max:80',
            'email' => 'nullable|email|max:160|required_without:phone',
            'phone' => ['nullable', 'string', 'max:32', 'regex:/^\+?[0-9 ().-]{7,24}$/', 'required_without:email'],
        ]);

        $session = ChatSession::where('session_token', $data['token'])->first();
        if (! $session) {
            return response()->json(['error' => 'Session not found', 'code' => 'session_not_found'], 404);
        }

        $hadContact = $session->hasContact();
        $session->update(array_filter([
            'visitor_name' => isset($data['name']) ? trim(strip_tags($data['name'])) : null,
            'visitor_email' => isset($data['email']) ? mb_strtolower($data['email']) : null,
            'visitor_phone' => $data['phone'] ?? null,
        ]));

        $thanks = Larachat::persona()->text('contact_thanks', $session->locale());
        $message = $session->messages()->create(['sender' => 'assistant', 'message' => $thanks]);
        $session->update(['last_message_at' => now()]);

        if (! $hadContact && $session->fresh()->hasContact()) {
            $notifier->lead($session->fresh());
        }

        return response()->json(['ok' => true, 'message' => $thanks, 'id' => $message->id, 'created_at' => $message->created_at]);
    }

    public function poll(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string|size:48',
            'after' => 'nullable|integer|min:0',
        ]);

        $session = ChatSession::where('session_token', $request->input('token'))->first();
        if (! $session) {
            return response()->json(['error' => 'Session not found', 'code' => 'session_not_found'], 404);
        }

        $query = $session->messages()->whereIn('sender', ['admin', 'assistant'])->orderBy('id', 'asc');

        $after = (int) $request->input('after', 0);
        if ($after > 0) {
            $query->where('id', '>', $after);
        }

        $messages = $query->get(['id', 'sender', 'message', 'created_at']);

        if ($messages->isNotEmpty()) {
            $session->messages()
                ->whereIn('sender', ['admin', 'assistant'])
                ->where('is_read', false)
                ->whereIn('id', $messages->pluck('id'))
                ->update(['is_read' => true]);

            $session->update(['unread_count' => 0]);
        }

        return response()->json([
            'messages' => $messages,
            'mode' => $session->mode,
        ]);
    }

    public function switchToAi(Request $request, Chatbot $chatbot): JsonResponse
    {
        $request->validate(['token' => 'required|string|size:48']);

        $session = ChatSession::where('session_token', $request->input('token'))->first();
        if (! $session) {
            return response()->json(['error' => 'Session not found', 'code' => 'session_not_found'], 404);
        }

        if (! $chatbot->isEnabled()) {
            return response()->json(['error' => 'AI not available'], 503);
        }

        $session->update(['mode' => 'ai']);

        $text = Larachat::persona()->text('hello_again', $session->locale());
        $message = $session->messages()->create(['sender' => 'assistant', 'message' => $text]);

        return response()->json([
            'mode' => 'ai',
            'message' => [
                'id' => $message->id,
                'sender' => 'assistant',
                'message' => $text,
                'created_at' => $message->created_at,
            ],
        ]);
    }
}
