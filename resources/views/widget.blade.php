{{--
    larachat's default widget: a launcher in the corner and a panel above it.
    To give it your own look, publish it (php artisan vendor:publish --tag=larachat-widget)
    and change the markup and classes freely: the script only looks for the data-lc
    attributes and the templates. Colours come from CSS variables (--lc-w-*).
--}}
@php($w = \EduLazaro\Larachat\Widget::class)
{!! $w::styles() !!}
<div class="lc-w" data-larachat data-larachat-config="{{ $w::config() }}">
    <button type="button" class="lc-w-launcher" data-lc="launcher" aria-label="{{ $w::text('open') }}">
        <svg viewBox="0 0 24 24" width="26" height="26" aria-hidden="true"><path fill="currentColor" d="M4 4h16a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H8l-4 4V6a2 2 0 0 1 2-2Zm3 6v2h2v-2H7Zm4 0v2h2v-2h-2Zm4 0v2h2v-2h-2Z"/></svg>
        <span class="lc-w-badge" data-lc="badge">0</span>
    </button>

    <div class="lc-w-nudge" data-lc="nudge">
        <span data-lc="nudge-text">{{ $w::text('nudge') }}</span> <span class="lc-w-nudge-close" data-lc="nudge-close">&times;</span>
    </div>

    <div class="lc-w-panel" data-lc="panel" role="dialog" aria-label="{{ \EduLazaro\Larachat\Larachat::persona()->site() }}">
        <div class="lc-w-head">
            <div>
                <div class="lc-w-name">{{ \EduLazaro\Larachat\Larachat::persona()->name() }}</div>
                <div class="lc-w-site">{{ \EduLazaro\Larachat\Larachat::persona()->site() }}</div>
            </div>
            <button type="button" class="lc-w-close" data-lc="close" aria-label="{{ $w::text('close') }}">&times;</button>
        </div>

        <div class="lc-w-messages" data-lc="messages">
            <div class="lc-w-row lc-w-row--bot" data-lc-static>
                <div class="lc-w-bubble lc-w-bubble--bot">{{ $w::text('greeting') }}<div class="lc-w-time">{{ now()->format('H:i') }}</div></div>
            </div>
        </div>

        <div class="lc-w-foot">
            <div class="lc-w-mode" data-lc="mode-bar" hidden>
                <span class="lc-w-mode-text" data-lc="mode-text"></span>
                <button type="button" class="lc-w-mode-btn" data-lc="mode-btn"><span data-lc="mode-btn-text"></span></button>
            </div>
            <div class="lc-w-compose">
                <textarea rows="1" maxlength="{{ (int) config('larachat.limits.message_chars', 1000) }}" data-lc="input" placeholder="{{ $w::text('placeholder') }}" autocomplete="off"></textarea>
                <button type="button" class="lc-w-send" data-lc="send" aria-label="{{ $w::text('submit') }}">
                    <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="currentColor" d="M3 20.5 21 12 3 3.5v6.6l12 1.9-12 1.9v6.6Z"/></svg>
                </button>
            </div>
        </div>
    </div>

    <template data-lc-template="visitor">
        <div class="lc-w-row lc-w-row--visitor"><div class="lc-w-bubble lc-w-bubble--visitor"><span data-lc-slot="text"></span><div class="lc-w-time" data-lc-slot="time"></div></div></div>
    </template>
    <template data-lc-template="bot">
        <div class="lc-w-row lc-w-row--bot"><div><div class="lc-w-sender" data-lc-slot="sender">{{ \EduLazaro\Larachat\Larachat::persona()->name() }}</div><div class="lc-w-bubble lc-w-bubble--bot"><span data-lc-slot="text"></span><div class="lc-w-time" data-lc-slot="time"></div></div></div></div>
    </template>
    <template data-lc-template="admin">
        <div class="lc-w-row lc-w-row--bot"><div><div class="lc-w-sender" data-lc-slot="sender"></div><div class="lc-w-bubble lc-w-bubble--admin"><span data-lc-slot="text"></span><div class="lc-w-time" data-lc-slot="time"></div></div></div></div>
    </template>
    <template data-lc-template="typing">
        <div class="lc-w-row lc-w-row--bot"><div class="lc-w-typing"><span></span><span></span><span></span></div></div>
    </template>
    {{-- The contact form, placed in the conversation by the server: the title says why. --}}
    <template data-lc-template="contact">
        <div class="lc-w-form">
            <div class="lc-w-form-title" data-lc-slot="title"></div>
            <label>{{ $w::text('form_name') }}<input type="text" name="name" maxlength="80" autocomplete="name"></label>
            <label>{{ $w::text('form_email') }}<input type="email" name="email" maxlength="160" autocomplete="email" inputmode="email"></label>
            <label>{{ $w::text('form_phone') }}<input type="tel" name="phone" maxlength="32" autocomplete="tel" inputmode="tel" pattern="[0-9+() .\-]*"></label>
            <div class="lc-w-form-error" data-lc-slot="error" role="alert"></div>
            <button type="button" data-lc="contact-send">{{ $w::text('form_send') }}</button>
            <div class="lc-w-form-privacy" data-lc-slot="privacy"></div>
        </div>
    </template>
</div>
{!! $w::script() !!}
