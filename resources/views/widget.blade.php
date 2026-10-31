{{--
    larachat's chat widget: a launcher in the corner and a panel above it. It is the
    same on every site; what a site sets is in config/larachat.php (widget.colors,
    widget.radius, widget.owner_avatar and, for a launcher of its own,
    widget.launcher) and the texts in lang/vendor/larachat. The script only looks
    for the data-lc attributes and the templates.
--}}
@php
    $w = \EduLazaro\Larachat\Widget::class;
    $persona = \EduLazaro\Larachat\Larachat::persona();
    $launcher = config('larachat.widget.launcher');
    $avatar = config('larachat.widget.owner_avatar');
    $bot = '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 2a1 1 0 0 1 1 1v1.06A7 7 0 0 1 19 11v5a3 3 0 0 1-3 3H8a3 3 0 0 1-3-3v-5a7 7 0 0 1 6-6.94V3a1 1 0 0 1 1-1Zm-3 9.5a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3Zm6 0a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3ZM2 12a1 1 0 0 1 2 0v3a1 1 0 0 1-2 0v-3Zm18 0a1 1 0 0 1 2 0v3a1 1 0 0 1-2 0v-3Z"/></svg>';
@endphp
{!! $w::styles() !!}
<div class="lc-w" data-larachat data-larachat-config="{{ $w::config() }}">
    @if ($launcher)
        <div class="lc-w-launcher-own" data-lc="launcher-wrap">@include($launcher)</div>
    @else
        <div class="lc-w-particles" data-lc="particles" aria-hidden="true"></div>
        <button type="button" class="lc-w-launcher" data-lc="launcher" aria-label="{{ $w::text('open') }}">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M4 3h16a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H9l-5 4v-4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Zm3.5 6.5a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3Zm4.5 0a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3Zm4.5 0a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3Z"/></svg>
            <span class="lc-w-badge" data-lc="badge">0</span>
        </button>
    @endif

    <div class="lc-w-nudge" data-lc="nudge">
        <span data-lc="nudge-text">{{ $w::text('nudge') }}</span> <span class="lc-w-nudge-close" data-lc="nudge-close">&times;</span>
    </div>

    <div class="lc-w-panel" data-lc="panel" role="dialog" aria-label="{{ $persona->name() }}">
        <div class="lc-w-head">
            <div class="lc-w-head-avatar">{!! $bot !!}</div>
            <div class="lc-w-head-info">
                <div class="lc-w-head-name">{{ $persona->name() }} <span>{{ $w::text('by') }}</span></div>
                <div class="lc-w-head-status">{{ $w::text('status') }}</div>
            </div>
            <button type="button" class="lc-w-close" data-lc="close" aria-label="{{ $w::text('close') }}">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M6.7 5.3 12 10.6l5.3-5.3a1 1 0 1 1 1.4 1.4L13.4 12l5.3 5.3a1 1 0 0 1-1.4 1.4L12 13.4l-5.3 5.3a1 1 0 0 1-1.4-1.4l5.3-5.3-5.3-5.3a1 1 0 0 1 1.4-1.4Z"/></svg>
            </button>
        </div>

        <div class="lc-w-messages" data-lc="messages">
            <div class="lc-w-welcome" data-lc-static>{{ $w::text('welcome') }}</div>
            <div class="lc-w-row" data-lc-static>
                <div class="lc-w-avatar lc-w-avatar--bot">{!! $bot !!}</div>
                <div>
                    <div class="lc-w-sender">{{ $persona->name() }}</div>
                    {{-- The time is filled in by the browser: the server's clock is in another zone. --}}
                    <div class="lc-w-bubble lc-w-bubble--bot">{{ $w::text('greeting') }}<div class="lc-w-time" data-lc-slot="time"></div></div>
                </div>
            </div>
        </div>

        <div class="lc-w-mode" data-lc="mode-bar" hidden>
            <span class="lc-w-mode-text" data-lc="mode-text"></span>
            <button type="button" class="lc-w-mode-btn" data-lc="mode-btn">{!! $bot !!} <span data-lc="mode-btn-text"></span></button>
        </div>
        {{-- Several lines: the box grows as the visitor types (Enter sends, Shift+Enter breaks the line). --}}
        <div class="lc-w-input">
            <textarea rows="1" maxlength="{{ (int) config('larachat.limits.message_chars', 1000) }}" data-lc="input" placeholder="{{ $w::text('placeholder') }}" autocomplete="off"></textarea>
            <button type="button" data-lc="send" aria-label="{{ $w::text('submit') }}">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M3.4 20.4 21 12 3.4 3.6 3.4 10l12.6 2-12.6 2Z"/></svg>
            </button>
        </div>
    </div>

    <template data-lc-template="visitor">
        <div class="lc-w-bubble lc-w-bubble--visitor"><span data-lc-slot="text"></span><div class="lc-w-time" data-lc-slot="time"></div></div>
    </template>
    <template data-lc-template="bot">
        <div class="lc-w-row"><div class="lc-w-avatar lc-w-avatar--bot">{!! $bot !!}</div><div><div class="lc-w-sender">{{ $persona->name() }}</div><div class="lc-w-bubble lc-w-bubble--bot"><span data-lc-slot="text"></span><div class="lc-w-time" data-lc-slot="time"></div></div></div></div>
    </template>
    <template data-lc-template="admin">
        <div class="lc-w-row"><div class="lc-w-avatar lc-w-avatar--owner">@if ($avatar)<img src="{{ $avatar }}" alt="">@else{{ mb_substr($persona->owner(), 0, 1) }}@endif</div><div><div class="lc-w-sender" data-lc-slot="sender">{{ $persona->owner() }}</div><div class="lc-w-bubble lc-w-bubble--bot"><span data-lc-slot="text"></span><div class="lc-w-time" data-lc-slot="time"></div></div></div></div>
    </template>
    <template data-lc-template="typing">
        <div class="lc-w-row"><div class="lc-w-avatar lc-w-avatar--bot">{!! $bot !!}</div><div><div class="lc-w-sender">{{ $persona->name() }}</div><div class="lc-w-typing"><span></span><span></span><span></span></div></div></div>
    </template>
    {{-- The contact form, placed in the conversation by the server: the title says why. --}}
    <template data-lc-template="contact">
        <div class="lc-w-form">
            <div class="lc-w-form-title" data-lc-slot="title"></div>
            <div class="lc-w-form-grid">
                <label>{{ $w::text('form_name') }}<input type="text" name="name" maxlength="80" autocomplete="name"></label>
                <label>{{ $w::text('form_email') }}<input type="email" name="email" maxlength="160" autocomplete="email" inputmode="email"></label>
                <label class="lc-w-form-wide">{{ $w::text('form_phone') }}<input type="tel" name="phone" maxlength="32" autocomplete="tel" inputmode="tel" pattern="[0-9+() .\-]*"></label>
            </div>
            <div class="lc-w-form-error" data-lc-slot="error" role="alert"></div>
            <div class="lc-w-form-foot">
                <button type="button" data-lc="contact-send">{{ $w::text('form_send') }}</button>
                <span class="lc-w-form-privacy" data-lc-slot="privacy"></span>
            </div>
        </div>
    </template>
</div>
{!! $w::script() !!}
