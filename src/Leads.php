<?php

namespace EduLazaro\Larachat;

use EduLazaro\Larachat\Models\ChatMessage;
use EduLazaro\Larachat\Models\ChatSession;
use EduLazaro\Larachat\Notifications\Notifier;

/**
 * When the chat asks for the visitor's details, decided here in code and not
 * left to the model, which asked once in a line of text that was easy to
 * ignore and never again.
 *
 * The ask is a contact form, and the form is a message of the conversation
 * (kind contact_form) that the widget draws as a form: it stays where it is
 * until it is filled, and survives a reload. It is placed when the visitor
 * shows real interest, asks about prices or timelines, says goodbye, closes
 * the chat, or wants a person who is not there; at most `lead.max_forms`
 * times, and never two open at once (a new one replaces the empty one).
 * Filling it in posts the details as the visitor's own message (kind
 * contact), with the values in its meta.
 */
class Leads
{
    public const FORM = 'contact_form';

    public const CONTACT = 'contact';

    /** Prices, budgets and timelines, in the package's languages. */
    protected const PRICE = '/\b(precios?|presupuestos?|cu[aá]nto\s+(cuesta|cobr\w*|vale|sale|ser[ií]a|costar\w*|tard\w*|tiempo)|costes?|costos?|tarifas?|plazos?|prices?|pricing|costs?|quotes?|quotations?|budgets?|estimates?|how\s+much|timelines?|how\s+long|preus?|pressupost(os)?|quant\s+(costa|val|triga)|prix|tarifs?|devis|co[uû]ts?|d[eé]lais?|combien)\b/iu';

    /**
     * The visitor wants to be called, contacted or written to, or asks for the
     * form itself, in the package's languages. "¿Cómo te llamas?" is not one.
     */
    protected const CONTACT_REQUEST = '/\b(ll[aá]m(adme|arme|ame|enme|eis|éis|en|adnos|arnos)|me\s+(ll[aá]m(e|en|éis|eis|ad|ar[aá]s|ar[aá]n|ar[eé]is)|contact(e|en|éis|eis|ad|ar[aá]s|ar[aá]n|ar[eé]is)|escrib(a|an|áis|ais|id|ir[aá]s|ir[aá]n|ir[eé]is))|pod[eé]is\s+(llamar|contactar|escribir)|puedes\s+(llamar|contactar|escribir)(me)?|pueden\s+(llamar|contactar|escribir)(me)?|una\s+llamada|contact(adme|arme|enme|adnos|arnos)|ponerme\s+en\s+contacto|escr[ií]b(idme|eme|anme|idnos)|formularios?|call\s+(me|us)|give\s+me\s+a\s+call|call\s+back|contact\s+(me|us)|get\s+in\s+touch|reach\s+(me|out)|e-?mail\s+me|text\s+me|the\s+form|a\s+form|truca\W?m|trucar-me|truqueu-me|em\s+truqueu|contacteu-me|contactar-me|escriviu-me|m\W?escriviu|formulari|appelez-moi|appelle-moi|rappelez-moi|me\s+rappeler|contactez-moi|me\s+contacter|[eé]crivez-moi|formulaire)\b/iu';

    /** A goodbye, in the package's languages. */
    protected const GOODBYE = '/\b(adi[oó]s|chao|chau|hasta\s+(luego|pronto|otra|ma[nñ]ana)|nos\s+vemos|me\s+voy|bye|goodbye|see\s+you|that.?s\s+all|ad[eé]u|fins\s+(aviat|despr[eé]s|dem[aà])|au\s+revoir|[aà]\s+bient[oô]t|[aà]\s+plus)\b/iu';

    public function __construct(protected Notifier $notifier)
    {
    }

    public function asksAboutPrice(string $text): bool
    {
        return (bool) preg_match(self::PRICE, $text);
    }

    public function asksToBeContacted(string $text): bool
    {
        return (bool) preg_match(self::CONTACT_REQUEST, $text);
    }

    public function saysGoodbye(string $text): bool
    {
        return (bool) preg_match(self::GOODBYE, $text);
    }

    /**
     * After a reply from the model: records the first sign of interest (and
     * alerts the owner, who can step in while the visitor is still there) and
     * places a form if this is one of the moments to ask.
     */
    public function afterReply(ChatSession $session, string $visitorMessage, bool $qualified): ?ChatMessage
    {
        if ($session->hasContact()) {
            return null;
        }

        $contact = $this->asksToBeContacted($visitorMessage);
        $price = $this->asksAboutPrice($visitorMessage);
        $justInterested = false;

        if (($qualified || $price || $contact) && ! $session->qualified_at) {
            $session->update(['qualified_at' => now()]);
            $justInterested = true;
            $this->notifier->interested($session, $visitorMessage);
        }

        $reason = match (true) {
            $contact => 'contact_request',
            $price => 'price',
            $session->qualified_at && $this->saysGoodbye($visitorMessage) => 'goodbye',
            $justInterested => 'interest',
            default => null,
        };

        return $reason ? $this->place($session, $reason) : null;
    }

    /**
     * Puts a form at the end of the conversation, unless the visitor already
     * left their details or has been asked enough times. One still open is
     * closed first, so there is never more than one.
     */
    public function place(ChatSession $session, string $reason): ?ChatMessage
    {
        if ($session->hasContact() || (int) $session->contact_forms >= $this->maxForms()) {
            return null;
        }

        $this->closeOpen($session);
        $session->increment('contact_forms');

        return $session->messages()->create([
            'sender' => 'assistant',
            'kind' => self::FORM,
            // The title, so the transcript reads sensibly anywhere it is shown as text.
            'message' => $this->title($reason, $session->locale()),
            'meta' => ['reason' => $reason],
            'is_read' => true,
        ]);
    }

    /**
     * The open form, moved to the end of the conversation (the old one closes,
     * the count does not change), or a new one if there is none.
     */
    public function bringForward(ChatSession $session, string $reason): ?ChatMessage
    {
        $open = $this->open($session);
        if (! $open) {
            return $this->place($session, $reason);
        }

        $this->closeOpen($session);

        return $session->messages()->create([
            'sender' => 'assistant',
            'kind' => self::FORM,
            'message' => $open->message,
            'meta' => ['reason' => $open->meta['reason'] ?? $reason],
            'is_read' => true,
        ]);
    }

    /** The form waiting to be filled, if there is one. */
    public function open(ChatSession $session): ?ChatMessage
    {
        if ($session->hasContact()) {
            return null;
        }

        return $session->messages()
            ->where('kind', self::FORM)
            ->orderByDesc('id')
            ->get()
            ->first(fn (ChatMessage $form) => empty($form->meta['closed']));
    }

    public function closeOpen(ChatSession $session): void
    {
        $session->messages()->where('kind', self::FORM)->get()
            ->filter(fn (ChatMessage $form) => empty($form->meta['closed']))
            ->each(fn (ChatMessage $form) => $form->update(['meta' => ['closed' => true] + (array) $form->meta]));
    }

    /**
     * A message as the widget gets it. Forms carry their title and the
     * privacy line, and a closed form is not sent at all (null).
     *
     * @return array<string, mixed>|null
     */
    public function present(ChatMessage $message, ?ChatSession $session = null): ?array
    {
        $data = [
            'id' => $message->id,
            'sender' => $message->sender,
            'message' => $message->message,
            'created_at' => $message->created_at,
            'kind' => $message->kind,
        ];

        if ($message->kind !== self::FORM) {
            return $data;
        }

        $session ??= $message->session;
        if (! empty($message->meta['closed']) || $session->hasContact()) {
            return null;
        }

        $locale = $session->locale();

        return $data + [
            'title' => $this->title((string) ($message->meta['reason'] ?? 'interest'), $locale),
            'privacy' => $this->privacyLine($locale),
        ];
    }

    /** The details as the visitor's message: "These are my details: Laura · laura@acme.test". */
    public function contactMessage(ChatSession $session, array $details): ChatMessage
    {
        return $session->messages()->create([
            'sender' => 'visitor',
            'kind' => self::CONTACT,
            'message' => Larachat::persona()->text('contact_message', $session->locale(), [
                'details' => implode(' · ', array_filter([$details['name'] ?? null, $details['email'] ?? null, $details['phone'] ?? null])),
            ]),
            'meta' => array_filter($details),
        ]);
    }

    public function maxForms(): int
    {
        return (int) config('larachat.lead.max_forms', 3);
    }

    protected function title(string $reason, string $locale): string
    {
        $key = in_array($reason, ['interest', 'contact_request', 'price', 'goodbye', 'close', 'handoff', 'wait', 'busy'], true) ? $reason : 'interest';

        return Larachat::persona()->text('form_' . $key, $locale);
    }

    /** "We only use them to reply to you. Privacy policy", with the link when the site has one. */
    protected function privacyLine(string $locale): string
    {
        $persona = Larachat::persona();
        $url = $persona->privacyUrl($locale);
        $line = e($persona->text('form_privacy', $locale));

        return $url
            ? $line . ' <a href="' . e($url) . '" target="_blank" rel="noopener">' . e($persona->text('form_privacy_link', $locale)) . '</a>'
            : $line;
    }
}
