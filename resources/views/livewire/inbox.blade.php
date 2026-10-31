<div wire:poll.5s="heartbeat" @class(['lc-inbox', 'has-open' => $current])>
    <aside class="lc-list">
        <div class="lc-list-head">
            <label class="lc-toggle" title="{{ __('larachat::panel.available_hint') }}">
                <input type="checkbox" wire:model.live="available" x-on:change="$event.target.checked && window.lcAskNotify && lcAskNotify()">
                <span class="lc-switch"></span>
                {{ $available ? __('larachat::panel.available') : __('larachat::panel.unavailable') }}
            </label>
            <button type="button" id="lcSound" class="lc-sound" wire:ignore title="{{ __('larachat::panel.sound_hint') }}">{{ __('larachat::panel.sound') }}</button>
        </div>
        <div class="lc-filters">
            <select class="lc-select" wire:model.live="filter" aria-label="{{ __('larachat::panel.filter') }}">
                @foreach (['all', 'unread', 'leads', 'interested', 'human', 'blocked'] as $key)
                    <option value="{{ $key }}">{{ __('larachat::panel.filter_' . $key) }}</option>
                @endforeach
            </select>
        </div>
        <div class="lc-items">
            @forelse ($sessions as $s)
                <button type="button" wire:key="s-{{ $s->id }}" wire:click="select({{ $s->id }})"
                    @class(['lc-item', 'is-open' => $current?->id === $s->id, 'is-unread' => $s->unread > 0])>
                    <div class="lc-item-top">
                        <span class="lc-item-who">{{ $this->label($s) }}</span>
                        <span class="lc-item-time">{{ $s->last_message_at?->locale(app()->getLocale())->diffForHumans(short: true) }}</span>
                    </div>
                    <div class="lc-item-preview">{{ \Illuminate\Support\Str::limit($s->latestMessage?->message, 90) }}</div>
                    <div class="lc-tags">
                        <span class="lc-tag">{{ $s->locale() }}</span>
                        @if ($s->hasContact()) <span class="lc-tag lc-tag--lead">{{ __('larachat::panel.tag_lead') }}</span> @elseif ($s->qualified_at) <span class="lc-tag lc-tag--interested">{{ __('larachat::panel.tag_interested') }}</span> @endif
                        @if ($s->unread > 0) <span class="lc-tag lc-tag--unread">{{ trans_choice('larachat::panel.tag_new', $s->unread, ['count' => $s->unread]) }}</span> @endif
                        @if ($s->blocked_at) <span class="lc-tag lc-tag--blocked">{{ __('larachat::panel.tag_blocked') }}</span>
                        @elseif ($s->mode === 'human') <span class="lc-tag lc-tag--human">{{ __('larachat::panel.tag_human') }}</span> @endif
                    </div>
                </button>
            @empty
                <div class="lc-empty">{{ __('larachat::panel.no_conversations') }}</div>
            @endforelse
        </div>
    </aside>

    <section class="lc-conv">
        @if ($current)
            <div class="lc-conv-head">
                <div class="lc-conv-meta">
                    <strong>{{ $this->label($current) }}</strong>
                    @if ($current->visitor_email || $current->visitor_phone)
                        <span class="lc-contact">
                            @if ($current->visitor_email) <a href="mailto:{{ $current->visitor_email }}">{{ $current->visitor_email }}</a> @endif
                            @if ($current->visitor_phone) <a href="tel:{{ preg_replace('/[^\d+]/', '', $current->visitor_phone) }}">{{ $current->visitor_phone }}</a> @endif
                        </span>
                    @endif
                    {{ $current->visitor_page ?: '-' }} · {{ $current->visitor_ip }} · {{ __('larachat::panel.since') }} {{ $current->created_at->format('d/m/Y H:i') }}
                </div>
                <div class="lc-conv-actions">
                    <button type="button" class="lc-btn lc-back" wire:click="close">{{ __('larachat::panel.back') }}</button>
                    <button type="button" class="lc-btn lc-btn--danger" wire:click="deleteConversation"
                        wire:confirm="{{ __('larachat::panel.delete_confirm') }}{{ $current->blocked_at ? __('larachat::panel.delete_confirm_blocked') : '' }}">{{ __('larachat::panel.delete') }}</button>
                    @if ($current->blocked_at)
                        <button type="button" class="lc-btn" wire:click="unblock">{{ __('larachat::panel.unblock') }}</button>
                    @else
                        @if ($current->mode === 'human')
                            <button type="button" class="lc-btn" wire:click="release">{{ __('larachat::panel.release') }}</button>
                        @else
                            <button type="button" class="lc-btn" wire:click="take">{{ __('larachat::panel.take') }}</button>
                        @endif
                        <button type="button" class="lc-btn lc-btn--danger" wire:click="block"
                            wire:confirm="{{ __('larachat::panel.block_confirm', ['days' => (int) config('larachat.limits.ip_block_days', 30)]) }}">{{ __('larachat::panel.block') }}</button>
                    @endif
                </div>
            </div>

            <div class="lc-msgs" x-data x-init="$el.scrollTop = $el.scrollHeight; new MutationObserver(() => $el.scrollTop = $el.scrollHeight).observe($el, { childList: true })">
                @foreach ($messages as $m)
                    @if ($m->kind === \EduLazaro\Larachat\Leads::FORM)
                        <div wire:key="m-{{ $m->id }}" class="lc-msg-event">📋 {{ __('larachat::panel.form_shown') }} · {{ __('larachat::panel.form_reason_' . ($m->meta['reason'] ?? 'interest')) }} · {{ $m->created_at->format('d/m H:i') }}</div>
                        @continue
                    @endif
                    <div wire:key="m-{{ $m->id }}" class="lc-msg lc-msg--{{ $m->sender }}{{ $m->off_topic ? ' lc-msg--offtopic' : '' }}{{ $m->kind === \EduLazaro\Larachat\Leads::CONTACT ? ' lc-msg--contact' : '' }}">@if($m->off_topic)<span class="lc-offtopic">{{ __('larachat::panel.off_topic') }}</span>
@endif{{ $m->message }}<small>{{ __('larachat::panel.sender_' . $m->sender) }} · {{ $m->created_at->format('d/m H:i') }}</small></div>
                @endforeach
            </div>

            @if ($current->status === 'closed')
                <div class="lc-note">{{ __('larachat::panel.closed_note') }}</div>
            @else
                <form class="lc-reply" wire:submit="send" wire:key="reply-{{ $current->id }}">
                    {{-- wire:ignore.self: the 5s poll must not reset the height the box grew to. --}}
                    <textarea class="lc-input" rows="1" wire:model="reply" wire:ignore.self
                        x-data x-on:input="$el.style.height = 'auto'; $el.style.height = Math.min($el.scrollHeight + 2, 200) + 'px'"
                        x-init="$wire.on('reply-sent', () => $el.style.height = 'auto')" placeholder="{{ __('larachat::panel.reply_placeholder') }}" title="{{ __('larachat::panel.reply_hint') }}"
                        x-on:keydown.enter="if (!$event.shiftKey) { $event.preventDefault(); $wire.send(); }"></textarea>
                    <button type="submit" class="lc-btn lc-btn--primary">{{ __('larachat::panel.send') }}</button>
                </form>
                @error('reply') <div class="lc-error lc-error--pad">{{ $message }}</div> @enderror
                @if ($current->mode === 'ai')
                    <div class="lc-note">{{ __('larachat::panel.bot_note') }}</div>
                @endif
            @endif
        @else
            <div class="lc-empty">{{ __('larachat::panel.choose') }}</div>
        @endif
    </section>
</div>

@script
<script>
    window.lcAskNotify = function () {
        if ('Notification' in window && Notification.permission === 'default') Notification.requestPermission();
    };

    // Browsers keep audio locked until the user interacts with the page, so a
    // context created inside the poll handler stays silent. One shared context,
    // unlocked on the first click or key press, plays every alert afterwards.
    const AudioCtx = window.AudioContext || window.webkitAudioContext;
    let audio = null;
    const soundBtn = () => document.getElementById('lcSound');
    const refreshSoundBtn = () => { const b = soundBtn(); if (b) b.hidden = !!(audio && audio.state === 'running'); };
    const unlock = () => {
        if (!AudioCtx) return;
        if (!audio) audio = new AudioCtx();
        if (audio.state !== 'running') audio.resume().then(refreshSoundBtn).catch(() => {});
        refreshSoundBtn();
    };
    const beep = () => {
        if (!audio || audio.state !== 'running') return;
        [[880, 0], [1320, 0.18]].forEach(([freq, at]) => {
            const t = audio.currentTime + at, osc = audio.createOscillator(), gain = audio.createGain();
            osc.frequency.value = freq;
            gain.gain.setValueAtTime(0.0001, t);
            gain.gain.exponentialRampToValueAtTime(0.3, t + 0.02);
            gain.gain.exponentialRampToValueAtTime(0.0001, t + 0.3);
            osc.connect(gain).connect(audio.destination);
            osc.start(t);
            osc.stop(t + 0.32);
        });
    };
    ['pointerdown', 'keydown'].forEach(ev => document.addEventListener(ev, unlock));
    document.addEventListener('click', e => { if (e.target.closest('#lcSound')) { unlock(); setTimeout(beep, 50); } });
    refreshSoundBtn();

    const baseTitle = document.title;
    const newTitle = @js(__('larachat::panel.new_title', ['count' => '__N__', 'site' => $site]));
    let unseen = 0;
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) { unseen = 0; document.title = baseTitle; }
    });

    $wire.on('chat-new', () => {
        beep();
        if (document.hidden) {
            unseen++;
            document.title = newTitle.replace('__N__', unseen);
            if ('Notification' in window && Notification.permission === 'granted') {
                new Notification(@js($site), { body: @js(__('larachat::panel.new_body')) });
            }
        }
    });
</script>
@endscript
