<?php

namespace EduLazaro\Larachat\Http\Controllers;

use EduLazaro\Larachat\Chatbot;
use EduLazaro\Larachat\Guard;
use EduLazaro\Larachat\Larachat;
use EduLazaro\Larachat\Leads;
use EduLazaro\Larachat\Models\ChatSession;
use EduLazaro\Larachat\Notifications\Notifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;

/**
 * The widget's API: open or restore a conversation, send a message, leave
 * contact details, poll for replies and hand the conversation back to the bot.
 */
class ChatController extends Controller
{
    public function __construct(protected Leads $leads)
    {
    }

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
                return response()->json([
                    'token' => $session->session_token,
                    'messages' => $this->present($session, $session->messages()->orderBy('id', 'asc')->get()),
                    'mode' => $session->mode,
                    'has_contact' => $session->hasContact(),
                    'lead' => (bool) $session->qualified_at,
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
                $response['ai_reply_id'] = $reply->id;
                $response['ai_reply_at'] = $reply->created_at;

                // The contact form, when this is one of the moments to ask for it.
                if (empty($result['off_topic'])) {
                    $form = ! empty($result['ask_contact'])
                        ? $this->leads->place($session, 'handoff')
                        : $this->leads->afterReply($session, $text, (bool) ($result['qualified'] ?? false));
                    if ($form) {
                        $response['form'] = $this->leads->present($form, $session);
                    }
                }

                $notifier->exchange($session, $text, $result['reply'], (bool) ($result['qualified'] ?? false), $visitorMessageCount === 0);
            } elseif (Larachat::humanAvailable()) {
                $session->update(['mode' => 'human']);
                $notifier->aiFailed($session, $text);
            }
        }

        $session->refresh();
        $response['mode'] = $session->mode;
        $response['has_contact'] = $session->hasContact();
        $response['lead'] = (bool) $session->qualified_at;

        return response()->json($response);
    }

    /**
     * The contact form, filled in. The details become the visitor's own
     * message (kind contact, values in meta), the conversation keeps them, the
     * open form closes and the bot thanks them by name.
     */
    public function contact(Request $request, Notifier $notifier): JsonResponse
    {
        $request->validate(['token' => 'required|string|size:48']);

        $session = ChatSession::where('session_token', $request->input('token'))->first();
        if (! $session) {
            return response()->json(['error' => 'Session not found', 'code' => 'session_not_found'], 404);
        }

        $locale = $session->locale();
        $persona = Larachat::persona();
        $required = (array) config('larachat.lead.required', ['name', 'email']);
        $rule = fn (string $field) => in_array($field, $required, true) ? 'required' : 'nullable';

        $data = Validator::make($request->only(['name', 'email', 'phone']), [
            'name' => [$rule('name'), 'string', 'max:80'],
            'email' => [$rule('email'), 'email', 'max:160'],
            'phone' => [$rule('phone'), 'string', 'max:32', 'regex:/^\+?[0-9 ().-]{7,24}$/'],
        ], [
            'name.required' => $persona->text('form_name_required', $locale),
            'email.required' => $persona->text('form_email_required', $locale),
            'email.email' => $persona->text('form_email_invalid', $locale),
            'phone.required' => $persona->text('form_phone_invalid', $locale),
            'phone.regex' => $persona->text('form_phone_invalid', $locale),
        ])->validate();

        $details = array_filter([
            'name' => isset($data['name']) ? trim(strip_tags($data['name'])) : null,
            'email' => isset($data['email']) ? mb_strtolower(trim($data['email'])) : null,
            'phone' => isset($data['phone']) ? trim($data['phone']) : null,
        ]);
        if (! isset($details['email']) && ! isset($details['phone'])) {
            return response()->json(['message' => $persona->text('form_email_required', $locale), 'errors' => ['email' => [$persona->text('form_email_required', $locale)]]], 422);
        }

        $hadContact = $session->hasContact();
        $visitor = $this->leads->contactMessage($session, $details);
        $session->update([
            'visitor_name' => $details['name'] ?? $session->visitor_name,
            'visitor_email' => $details['email'] ?? $session->visitor_email,
            'visitor_phone' => $details['phone'] ?? $session->visitor_phone,
            'last_message_at' => now(),
        ]);
        $this->leads->closeOpen($session);

        $thanks = $session->messages()->create([
            'sender' => 'assistant',
            'message' => $persona->text((isset($details['email']) ? 'contact_thanks' : 'contact_thanks_phone') . (isset($details['name']) ? '' : '_unnamed'), $locale, [
                'name' => isset($details['name']) ? explode(' ', $details['name'])[0] : '',
                'email' => $details['email'] ?? '',
                'phone' => $details['phone'] ?? '',
            ]),
        ]);

        if (! $hadContact && $session->fresh()->hasContact()) {
            $notifier->lead($session->fresh());
        }

        return response()->json([
            'ok' => true,
            'messages' => [$this->leads->present($visitor, $session), $this->leads->present($thanks, $session)],
        ]);
    }

    /**
     * A form asked for by the widget itself: the visitor closed the chat after
     * showing interest (close), or asked for a person who has not answered
     * within a few seconds (wait).
     */
    public function contactForm(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => 'required|string|size:48',
            'reason' => 'required|in:close,wait',
        ]);

        $session = ChatSession::where('session_token', $data['token'])->first();
        if (! $session) {
            return response()->json(['error' => 'Session not found', 'code' => 'session_not_found'], 404);
        }

        $allowed = $data['reason'] === 'close' ? (bool) $session->qualified_at : $session->mode === 'human';
        $form = $allowed && ! $this->leads->open($session) ? $this->leads->place($session, $data['reason']) : null;

        return response()->json(['form' => $form ? $this->leads->present($form, $session) : null]);
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

        $messages = $query->get();

        if ($messages->isNotEmpty()) {
            $session->messages()
                ->whereIn('sender', ['admin', 'assistant'])
                ->where('is_read', false)
                ->whereIn('id', $messages->pluck('id'))
                ->update(['is_read' => true]);

            $session->update(['unread_count' => 0]);
        }

        return response()->json([
            'messages' => $this->present($session, $messages),
            'mode' => $session->mode,
            'has_contact' => $session->hasContact(),
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

    /**
     * Messages as the widget gets them: forms that are closed (or no longer
     * needed) are left out.
     *
     * @param  iterable<\EduLazaro\Larachat\Models\ChatMessage>  $messages
     * @return list<array<string, mixed>>
     */
    protected function present(ChatSession $session, iterable $messages): array
    {
        $out = [];
        foreach ($messages as $message) {
            if ($item = $this->leads->present($message, $session)) {
                $out[] = $item;
            }
        }

        return $out;
    }
}
