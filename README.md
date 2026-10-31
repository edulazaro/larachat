![Larachat](art/banner.png)

# Larachat - a website chat for Laravel that a bot answers and you can take over

<p align="center">
    <a href="https://github.com/edulazaro/larachat/actions/workflows/tests.yml"><img src="https://github.com/edulazaro/larachat/actions/workflows/tests.yml/badge.svg" alt="Tests"></a>
    <a href="https://packagist.org/packages/edulazaro/larachat"><img src="https://img.shields.io/packagist/v/edulazaro/larachat" alt="Latest Stable Version"></a>
    <a href="https://packagist.org/packages/edulazaro/larachat"><img src="https://img.shields.io/packagist/dt/edulazaro/larachat" alt="Total Downloads"></a>
    <a href="https://packagist.org/packages/edulazaro/larachat"><img src="https://img.shields.io/packagist/php-v/edulazaro/larachat" alt="PHP Version"></a>
    <a href="https://github.com/edulazaro/larachat/blob/main/LICENSE.md"><img src="https://img.shields.io/packagist/l/edulazaro/larachat" alt="License"></a>
</p>

A chat for the public pages of a Laravel site. A bot answers from your own pages, a cheap
model turns away questions that are not for this chat before the expensive one is called,
deterministic guards stop prompt injection and abuse without spending a token, and when a
visitor asks for a person the conversation lands in a private inbox where you answer, from
the panel or from Telegram.

The package owns the parts that are the same on every site and expensive to get right: the
pipeline, the guards, retrieval, lead capture, the panel and the alerts. You write who the
bot is (a persona) and how the widget looks.

## What happens to a message

1. **Guards, no API call.** A conversation closed for abuse, a message with nothing in it
   ("x", "???", letters one by one), a prompt-injection attempt or a request for code gets a
   fixed answer. Injection strikes close the conversation for a day, and so do a few empty
   messages in a row (any real message starts that count again).
2. **Scope filter and embedding, in parallel.** A cheap model (`filter_model`) decides
   whether the message is for this chat at all, while the query is embedded. Off-topic gets a
   fixed answer and the main model is never called; a few of those (per conversation AND per
   IP) close the chat for a day.
3. **Retrieval.** Hybrid search (keywords plus vectors) over the chunks of your site in the
   visitor's language, at most two chunks per page, plus the first chunks of the page the
   visitor is on.
4. **The reply.** The main model answers as JSON: the reply, whether the visitor asked for a
   person, whether they showed commercial intent, and any contact details they gave. The
   fixed part of the prompt (your persona, the rules, the site map) sits behind a prompt-cache
   breakpoint, so after the first message in 30 minutes it is read at a twentieth of the price.
5. **After the reply.** Unsafe replies (code, quoted prompt sections) are replaced and count as
   a strike. Contact details written in a message are kept only if valid AND literally in what
   the visitor wrote. A handoff goes to you only if you are at the panel; otherwise the visitor
   gets the contact form.

## Getting the visitor's details

The model never asks for them. A line of text asking once is easy to ignore, and a visitor
who asks for a price and says goodbye leaves with nothing. The package decides, in code, when
to ask, and asks with a **contact form placed in the conversation**: a message of its own
(`kind` `contact_form`) that the widget draws as a form, which stays where it is until it is
filled and survives a reload.

It is placed when the visitor:

- shows real interest (the model's `qualified`), the first time;
- asks about prices, budgets or timelines (in English, Spanish, Catalan or French);
- says goodbye after showing interest;
- closes the chat after showing interest (the bubble then invites them to leave their details);
- asks for a person who is not at the panel, or who has not answered within a few seconds.

At most `lead.max_forms` times per conversation (3), and never two at once: a new one replaces
the one still empty. Each has a title that says why (*So Edu can send you a quote*). The form
asks for the fields in `lead.required` (name and email) and an optional phone. Characters that
cannot belong in a field never appear in it (letters for the name, digits and separators for
the phone, no spaces in the email), the format is checked before sending, and the server
applies the same rules. Sent, it
becomes the visitor's own message (*Here are my details: Laura · laura@acme.test*, kind
`contact`, the values in its `meta`), the conversation keeps them, the bot thanks them by name
and you get the lead alert.

The first sign of interest also sends you an alert while the visitor is still there, and the
panel's *Interested, no details* filter lists the conversations that showed interest and left
no way to reach them.

If OpenAI or the store fails, the reply falls back to the persona's fallback knowledge, so the
chat never goes silent. Long conversations are summarised by the cheap model.

## Install

```bash
composer require edulazaro/larachat
php artisan vendor:publish --tag=larachat-config
php artisan vendor:publish --tag=larachat-migrations
php artisan migrate
php artisan larachat:admin you@example.com
```

The migration creates `larachat_sessions` and `larachat_messages`, or completes them if they
already exist, and adds `is_available` and `last_seen_at` to `users`. It never changes
existing data, so after an upgrade that adds columns you publish it again and migrate: it only
adds what is missing.

Moving a site whose chat larachat was extracted from (tables `chat_sessions` and
`chat_messages`)? Rename them in a migration of your own dated before larachat's, and the
conversations carry over:

```php
Schema::rename('chat_sessions', 'larachat_sessions');
Schema::rename('chat_messages', 'larachat_messages');
```

**If your web server answers `.js` and `.css` requests itself** (a common nginx setup, which
returns its own 404 for a file that is not on disk), the routes that serve the widget script,
the panel's CSS and Livewire's own script are never reached. Publish those files to `public/`
on every install, and the package and Livewire use the copies:

```json
"post-autoload-dump": [
    "Illuminate\\Foundation\\ComposerScripts::postAutoloadDump",
    "@php artisan package:discover --ansi",
    "@php artisan vendor:publish --tag=livewire:assets --force --ansi",
    "@php artisan vendor:publish --tag=larachat-assets --force --ansi"
]
```

Add `public/vendor/livewire` and `public/vendor/larachat` to `.gitignore`: they are copies.

Add your email to `admins` in `config/larachat.php`, set `OPENAI_API_KEY`, index the site
(below) and put the widget in your layout:

```blade
@include('larachat::widget')
```

## Configuration

Everything lives in `config/larachat.php`; the only environment variables are the secrets
(`OPENAI_API_KEY`, and the Telegram ones if you use it). The parts to change on every site:

| Key | What it is |
|---|---|
| `persona` | Your persona class (below) |
| `locales`, `default_locale` | The site's locales and the one served without a URL prefix |
| `routes.login`, `routes.panel` | Both `larachat` by default (one address). **Change them**: the package is public, so its defaults are too |
| `admins` | The emails allowed into the panel, whatever other users exist |
| `knowledge.driver` | `json` (no server) or `meilisearch` |
| `knowledge.base_url`, `knowledge.content`, `knowledge.drop` | Your public URL, the element that holds each page's content, what to remove inside it |
| `limits.replies_per_day` | Model replies per day for the whole site (500): the ceiling on what the chat can cost. Past it the bot answers with a fixed text and the contact form until midnight, and you get one email |
| `lead.max_forms`, `lead.required` | How many contact forms per conversation (3) and the fields they require (name, email) |
| `notifications` | Email to send alerts to; Telegram on or off |
| `widget.storage_key` | Where the browser keeps the visitor's token (keep the old key when moving a site) |

## The persona

Extend `EduLazaro\Larachat\Persona` and point `larachat.persona` at it:

```php
use EduLazaro\Larachat\Persona;

class AcmePersona extends Persona
{
    protected string $name = 'Ada';             // the bot
    protected string $owner = 'Laura';          // who takes over
    protected string $site = 'Acme';            // the business
    protected ?string $email = 'hello@acme.test';

    public function systemPrompt(string $locale): string
    {
        return 'You are Ada, the AI assistant of Acme... (identity, scope, what you do, house rules)';
    }

    public function scopePrompt(): string
    {
        return 'You are the gatekeeper of the chat on acme.test... Reply only with JSON: {"on_topic": true} or {"on_topic": false}';
    }

    // Optional: siteMap(), fallbackKnowledge(), pageContext(), privacyUrl()
}
```

The package adds the lead-capture rules and the output format to your system prompt, so a
persona cannot break the JSON the pipeline reads. Keep `systemPrompt()` identical for every
message in a locale: it is what gets cached.

Fixed texts (refusals, the closing message, the contact request) come from the package's
lang files in English, Spanish, Catalan and French, with `:bot`, `:owner`, `:site` and
`:email` filled in from the persona. Override any of them in
`lang/vendor/larachat/{locale}/chat.php` (`php artisan vendor:publish --tag=larachat-lang`).

## Knowledge

```bash
php artisan larachat:index            # render, chunk, embed what changed, drop what is gone
php artisan larachat:index --dry-run  # render and chunk only
php artisan larachat:index --fresh    # embed everything again
php artisan larachat:index --path=/es/contact
```

Every URL of the sitemap (`knowledge.sitemap`: a file in `public/` or a path the app serves;
a sitemap index is followed one level) is rendered through the HTTP kernel, so the bot reads
exactly what visitors see. The content element is cut into sections by heading and packed
into chunks of about 1,400 characters. Only chunks whose content changed are embedded again,
so an unchanged site costs nothing. Pages with an `article:published_time` in the future are
indexed but not used until that date. Schedule it nightly and run it after each deploy.

**`json` driver**: the chunks live in a PHP file under `storage/` that opcache keeps compiled
in memory, so loading it costs nothing per request; vectors are packed binary. Fine for a few
thousand chunks.

**`meilisearch` driver**: `composer require edulazaro/larameili`, configure its connection,
and the chunks go to an index named `knowledge.meilisearch.index` (with larameili's prefix),
searched with Meilisearch's own hybrid search. Vectors are computed by the app, so Meilisearch
never holds an OpenAI key.

## The widget

```blade
@include('larachat::widget')
```

One widget, the same on every site: a launcher in the corner (a round button with a pulse and
sparks) and a panel above it with the bot's name and the site, the conversation, the contact
form when it is time, and a message box that grows with the text (Enter sends, Shift+Enter
breaks the line). On phones the panel takes the screen. What a site sets:

```php
'widget' => [
    'colors' => ['primary' => '#2563eb', 'primary_dark' => '#1d4ed8'],
    'radius' => 16,                           // the panel's corners; bubbles and fields follow
    'owner_avatar' => '/img/team/me.webp',    // your replies; without it, your initial
    'launcher' => null,                       // or a Blade view of your own (below)
],
```

Colours that are not a colour and a radius that is not a number of pixels are ignored. The
texts (greeting, header, placeholder, the bubble) come from the package's lang files and are
overridden in `lang/vendor/larachat/{locale}/widget.php`.

**A launcher of your own.** Point `widget.launcher` at a Blade view and it replaces the
standard button; everything else stays the standard widget. The view needs an element with
`data-lc="launcher"` (it opens the chat) and may have one with `data-lc="badge"` (the unread
count, which gets `.is-visible`). The root gets `.lc-open` while the chat is open, if you want
to hide your launcher then:

```blade
<button type="button" class="my-orb" data-lc="launcher">
    <canvas></canvas><span data-lc="badge">0</span>
</button>
```

The script and the stylesheet are served by the package with a versioned URL, so an update
reaches the site without publishing anything (or from `public/vendor/larachat` when they are
published, see Install). The widget survives `wire:navigate`; wrap it in `@persist('larachat')`
to keep it open across pages.

## The panel

`/larachat` shows the login to anyone who is not an admin and the inbox to you;
`/larachat/profile` changes your name, email and password (changing the email asks for the
current password and only takes an address in `admins`; a new password needs 12 characters). Give `routes.login` a path of its
own and the login moves there, while the panel answers 404 to guests and to users who are not
in `admins`, so only the login page shows there is one. Every panel page sends `noindex`, and
each action is checked against `admins` again, not only the page load. The login answers a
non-admin exactly as a wrong password and in the same time, is rate-limited per email and IP
and per email alone, and the password never travels back in Livewire's state. The widget sends
its token in a header when it polls, never in a URL that would end up in access logs.

The inbox lists every conversation with at least one visitor message, filters them (unread,
leads, with you, blocked), and lets you answer, take a conversation from the bot or hand it
back, block it (the visitor gets a closing message and their IP cannot open another one for
`ip_block_days`) or delete it (the visitor's widget starts again from scratch; deleting a
blocked conversation lifts the IP block). It beeps on new messages and, with the tab hidden,
updates the title and shows a browser notification.

The availability switch is what the bot reads before handing a conversation over. It only
counts while the panel has been seen open in the last few minutes, so a forgotten switch
turns itself off instead of leaving visitors waiting for nobody.

If you forget the password, run `php artisan larachat:admin you@example.com` again.

## Alerts

**Email** (`notifications.mail.to`): one message per new conversation, one when a visitor
first shows interest and has left no details yet (so you can step in), and one per lead, sent
after the response. These alerts are capped per visitor IP and day
(`notifications.mail.per_ip_per_day`, 10), by email and Telegram, so a script opening
conversations cannot flood your inbox; conversations you are in are never capped. Details are
only accepted through a form the chat placed, once per form, so invented leads cannot be posted
straight to the API.

**Telegram**: set `enabled`, the bot token, your chat id and a webhook secret, then
`php artisan larachat:telegram set`. You get new conversations, leads, handoffs and every
visitor message while a conversation is yours; with `all_messages` also every exchange with
the bot. Answer with Reply on a forwarded message; `/ai 123` or the buttons hand a
conversation either way. Only your chat is obeyed, and updates without the secret are refused
(there is no unprotected mode: without it, anyone who knew your chat id could reply to
visitors as you).

## No websockets, no queue

Everything runs on the plain request cycle, so there is no Reverb, Pusher or queue worker
to run:

- The widget polls for new messages once a conversation exists: every 3 seconds, slowing to
  10 and then 15 seconds while nothing arrives, and back to 3 as soon as something does. Each
  poll asks only for messages after the last one it has, and is rate-limited
  (`limits.poll_per_minute`).
- The panel refreshes with `wire:poll` every 5 seconds, which is also the heartbeat the
  availability switch reads.
- The bot answers within the visitor's request; emails and Telegram messages go out after
  the response has been sent.

## Testing

```bash
composer install
vendor/bin/phpunit
```

## Sponsors

Larachat is supported by the following sponsors. Thank you for keeping it growing:

<p>
  <a href="https://kenodo.com"><img src="art/logo-kenodo.png" width="24" alt="Kenodo"></a>&nbsp;<a href="https://kenodo.com">Kenodo</a>&nbsp;&nbsp;&nbsp;&nbsp;
  <a href="https://andorradev.com"><img src="art/logo-andorradev.png" width="24" alt="AndorraDev"></a>&nbsp;<a href="https://andorradev.com">AndorraDev</a>
</p>

## Author

Created by [Edu Lazaro](https://edulazaro.com)

## License

Larachat is open-sourced software licensed under the [MIT license](LICENSE.md).
