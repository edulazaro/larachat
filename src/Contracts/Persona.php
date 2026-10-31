<?php

namespace EduLazaro\Larachat\Contracts;

/**
 * Who the bot is on one site. The package owns the pipeline (guards, scope
 * filter, retrieval, lead capture, the JSON the model must answer with); the
 * persona owns what only the site can say about itself.
 */
interface Persona
{
    /** The bot's name, as visitors see it ("Nodo"). */
    public function name(): string;

    /** The person who takes over from the bot ("Edu"). */
    public function owner(): string;

    /** The business the chat belongs to ("Kenodo"). */
    public function site(): string;

    /** The address visitors are sent to when the chat closes, or null. */
    public function email(): ?string;

    /**
     * The fixed part of the system prompt: identity, scope, what the site
     * does, how to link, house rules. It must be the same for every message
     * in a locale, because it sits behind a prompt-cache breakpoint. The
     * output format and the lead-capture rules are added by the package.
     */
    public function systemPrompt(string $locale): string;

    /**
     * Instructions for the cheap model that decides whether a message is for
     * this chat at all. It must answer {"on_topic": true|false}.
     */
    public function scopePrompt(): string;

    /**
     * A map of the site's main pages with their URLs, added to the cached part
     * of the prompt so the model knows every section exists even when the
     * search did not bring it. Null for none.
     */
    public function siteMap(string $locale): ?string;

    /**
     * What the bot knows when retrieval is unavailable (no index yet, OpenAI
     * or Meilisearch down), so the chat never goes silent. Null for none.
     */
    public function fallbackKnowledge(string $locale): ?string;

    /**
     * Extra context for the page the visitor is on (a tool's own field labels,
     * for example), or null.
     */
    public function pageContext(?string $page, string $locale): ?string;

    /** The privacy policy the bot cites when it asks for contact details, or null. */
    public function privacyUrl(string $locale): ?string;

    /**
     * A fixed text the package shows without asking the model (refusals,
     * closing message, contact request...), in the visitor's locale.
     *
     * @param  array<string, string>  $replace
     */
    public function text(string $key, string $locale, array $replace = []): string;
}
