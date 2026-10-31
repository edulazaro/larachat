<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Persona
    |--------------------------------------------------------------------------
    |
    | Who the bot is on this site: a class implementing
    | EduLazaro\Larachat\Contracts\Persona (extend EduLazaro\Larachat\Persona).
    | It holds what no config value can: the system prompt, the scope prompt
    | and, optionally, a site map and page-specific context.
    |
    */

    'persona' => null,

    /*
    |--------------------------------------------------------------------------
    | Locales
    |--------------------------------------------------------------------------
    |
    | The site's locales and the one served without a prefix in the URL. The
    | visitor's locale is read from the first segment of the page they are on:
    | /es/... is "es", anything without a known prefix is the default.
    |
    */

    'locales' => ['en'],

    'default_locale' => 'en',

    /*
    |--------------------------------------------------------------------------
    | Routes
    |--------------------------------------------------------------------------
    |
    | The package is public, so these defaults are public too: change login and
    | panel on each site. With the same path for both, /larachat shows guests
    | the login and admins the inbox; with a separate login page, guests get a
    | 404 on the panel, so only the login shows that there is one (the profile
    | always answers 404 to them). The Telegram webhook path is the one registered
    | with Telegram; keep the old one when moving a site that already has it.
    |
    */

    'routes' => [
        // The same path as the panel puts the login form there for guests;
        // a different one gives the login a page of its own.
        'login' => 'larachat',
        'panel' => 'larachat',
        'api' => 'api/chat',
        'telegram_webhook' => 'larachat/telegram/webhook',
        'assets' => 'larachat/assets',
    ],

    /*
    |--------------------------------------------------------------------------
    | Panel
    |--------------------------------------------------------------------------
    |
    | Only these emails get past the login, even if other users exist in the
    | users table. Create one with `php artisan larachat:admin you@example.com`.
    |
    */

    'admins' => [],

    'panel' => [
        'name' => env('APP_NAME', 'Laravel'),
        'locale' => 'es',
        'favicon' => null,
        // Minutes since the panel was last seen open for the owner to count as available.
        'available_minutes' => 3,
    ],

    /*
    |--------------------------------------------------------------------------
    | Widget
    |--------------------------------------------------------------------------
    |
    | storage_key is where the browser keeps the visitor's conversation token
    | (keep the old key when moving a site, or its visitors lose their chats).
    | lock_scroll stops the page scrolling while the chat is open, for a
    | full-screen widget.
    |
    */

    'widget' => [
        'storage_key' => 'larachat_token',
        'lock_scroll' => false,
        'nudge_seconds' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | OpenAI
    |--------------------------------------------------------------------------
    |
    | The reply comes from `model`; the scope filter and conversation summaries
    | from the cheaper `filter_model`. Embeddings are cut to 512 dimensions:
    | a third of the size of the full vector, and plenty for a site.
    |
    */

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => 'gpt-6.1-sol',
        'reasoning_effort' => 'low',
        'max_tokens' => 2000,
        'filter_model' => 'gpt-6-luna',
        'embedding_model' => 'text-embedding-3-small',
        'embedding_dimensions' => 512,
    ],

    /*
    |--------------------------------------------------------------------------
    | Knowledge
    |--------------------------------------------------------------------------
    |
    | `php artisan larachat:index` renders every page of the sitemap through
    | the HTTP kernel, keeps the main content, cuts it by heading into chunks
    | and embeds them. The driver is where the chunks live and how they are
    | searched: "json" needs no server (a PHP file opcache keeps in memory),
    | "meilisearch" needs edulazaro/larameili and does hybrid search there.
    |
    */

    'knowledge' => [
        'driver' => 'json',
        'json' => [
            'path' => storage_path('app/larachat/index.php'),
        ],
        'meilisearch' => [
            // Prefixed with larameili's MEILISEARCH_PREFIX.
            'index' => 'chunks',
        ],
        // A path served by the app (rendered through the kernel) or a file in public/.
        'sitemap' => 'sitemap.xml',
        // Public address of the site, used in the URLs the bot cites.
        'base_url' => env('APP_URL'),
        // The element that holds the page's content; everything else is dropped.
        'content' => 'main',
        // Elements removed inside it, by tag, id or class (".chat-widget", "#cookies").
        'drop' => ['header', 'nav', 'footer', '.breadcrumb'],
        'chunk_chars' => 1400,
        'retrieved_chunks' => 8,
        'semantic_ratio' => 0.6,
    ],

    /*
    |--------------------------------------------------------------------------
    | Limits
    |--------------------------------------------------------------------------
    */

    'limits' => [
        'init_per_minute' => 5,
        'send_per_minute' => 8,
        'poll_per_minute' => 60,
        'switch_per_minute' => 5,
        'replies_per_ip_per_day' => 60,
        'messages_per_session' => 50,
        'message_chars' => 1000,
        // Prompt injection or unsafe replies before a conversation is closed for a day.
        'strikes' => 3,
        // Off-topic messages before the visitor (conversation AND IP) is closed for a day.
        'off_topic' => 3,
        'ip_block_days' => 30,
        'history_tokens' => 4000,
        'recent_messages' => 4,
        // Seconds the widget waits for an answer from you before asking for contact details.
        'human_wait_seconds' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    |
    | The panel always has every conversation. Mail sends one email per new
    | conversation and one per lead. Telegram also forwards each message while
    | you have the conversation, and you can answer from Telegram with Reply.
    |
    */

    'notifications' => [
        'mail' => [
            'to' => null,
        ],
        'telegram' => [
            'enabled' => false,
            'token' => env('TELEGRAM_BOT_TOKEN'),
            'chat_id' => env('TELEGRAM_ADMIN_CHAT_ID'),
            'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),
            // Also forward every exchange with the bot, not only what needs you.
            'all_messages' => false,
        ],
    ],

];
