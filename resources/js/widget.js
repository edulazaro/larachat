/*
 * larachat widget: the visitor's side of the chat.
 *
 * The logic lives here and the look in each site's own markup. The script
 * finds the widget by attributes, never by class names:
 *
 *   [data-larachat]            root, with data-larachat-config (JSON from the Blade view)
 *   [data-lc="launcher"]       opens and closes (any element; also [data-lc="launcher-wrap"])
 *   [data-lc="panel"]          gets .is-open while open; the root gets .lc-open
 *   [data-lc="close"]          closes
 *   [data-lc="messages"]       where messages go; children with data-lc-static are kept on reset
 *   [data-lc="input"], [data-lc="send"]
 *   [data-lc="badge"]          unread count, .is-visible when there is one
 *   [data-lc="nudge"], [data-lc="nudge-close"]   the "need help?" bubble, .is-visible
 *   [data-lc="mode-bar"], [data-lc="mode-text"], [data-lc="mode-btn"], [data-lc="mode-btn-text"]
 *
 * and builds messages from <template data-lc-template="visitor|bot|admin|typing|contact">,
 * filling [data-lc-slot="text|time|sender"] (admin falls back to bot). The
 * contact template holds inputs named name, email and phone, a
 * [data-lc-slot="error"] and a [data-lc="contact-send"] button.
 *
 * It survives Livewire's wire:navigate: a root is set up once, polling stops
 * before a page swap, and a root that is still there (inside @persist) is not
 * set up again.
 */
(function () {
    if (window.Larachat) {
        return;
    }

    function setup(root) {
        if (!root || root.__larachat) {
            return;
        }

        var cfg = JSON.parse(root.getAttribute('data-larachat-config') || '{}');
        var T = cfg.texts || {};
        var q = function (name) { return root.querySelector('[data-lc="' + name + '"]'); };
        var el = {
            wrap: q('launcher-wrap'), launcher: q('launcher'), panel: q('panel'), close: q('close'),
            messages: q('messages'), input: q('input'), send: q('send'), badge: q('badge'),
            nudge: q('nudge'), nudgeClose: q('nudge-close'), modeBar: q('mode-bar'),
            modeText: q('mode-text'), modeBtn: q('mode-btn'), modeBtnText: q('mode-btn-text')
        };
        var storeKey = cfg.storageKey || 'larachat_token';

        var token = read(localStorage, storeKey), lastId = 0, pollTimer = null, sending = false,
            started = false, mode = 'ai', interval = 3000, idlePolls = 0, nudgeDismissed = false,
            restored = false, hasContact = false, waitTimer = null, alive = true;

        function read(store, key) { try { return store.getItem(key); } catch (e) { return null; } }
        function write(store, key, value) { try { value === null ? store.removeItem(key) : store.setItem(key, value); } catch (e) {} }

        function isOpen() { return el.panel && el.panel.classList.contains('is-open'); }
        function post(url, data) {
            return fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }, body: JSON.stringify(data) })
                .then(function (r) { return r.json(); });
        }
        function get(url) { return fetch(url, { headers: { 'Accept': 'application/json' } }).then(function (r) { return r.json(); }); }
        function page() { return window.location.pathname; }

        function init() {
            if (!token) return;
            post(cfg.urls.init, { token: token, page: page() }).then(function (data) {
                token = data.token; write(localStorage, storeKey, token);
                if (data.mode) mode = data.mode;
                if (data.has_contact) hasContact = true;
                if (data.messages && data.messages.length > 0) {
                    started = true;
                    data.messages.forEach(function (m) {
                        add(m.sender === 'visitor' ? escapeHtml(m.message) : formatReply(m.message), m.sender, m.created_at);
                        if (m.id > lastId) lastId = m.id;
                    });
                    updateMode(); startPolling();
                }
            }).catch(function (e) { console.error('Chat init failed:', e); });
        }

        function fromTemplate(name) {
            var tpl = root.querySelector('template[data-lc-template="' + name + '"]');
            return tpl ? tpl.content.firstElementChild.cloneNode(true) : null;
        }
        function slot(node, name) { return node.matches && node.matches('[data-lc-slot="' + name + '"]') ? node : node.querySelector('[data-lc-slot="' + name + '"]'); }

        // Nobody can answer now: ask for a way to reach the visitor instead of leaving them waiting.
        function showContact() {
            if (hasContact || !token || root.querySelector('[data-lc-contact]')) return;
            var card = fromTemplate('contact');
            if (!card) return;
            card.setAttribute('data-lc-contact', '');
            var text = slot(card, 'text'), err = slot(card, 'error'), btn = card.querySelector('[data-lc="contact-send"]');
            if (text) text.textContent = T.unavailable || '';
            ['name', 'email', 'phone'].forEach(function (n) { var i = card.querySelector('[name="' + n + '"]'); if (i && !i.placeholder) i.placeholder = T[n] || ''; });
            if (btn && !btn.textContent.trim()) btn.textContent = T.send || '';
            btn && btn.addEventListener('click', function () {
                var v = function (n) { var i = card.querySelector('[name="' + n + '"]'); return i ? i.value.trim() : ''; };
                var name = v('name'), email = v('email'), phone = v('phone');
                if (!email && !phone) { if (err) err.textContent = T.need_one || ''; return; }
                btn.disabled = true; if (err) err.textContent = '';
                var payload = { token: token };
                if (name) payload.name = name; if (email) payload.email = email; if (phone) payload.phone = phone;
                post(cfg.urls.contact, payload).then(function (data) {
                    btn.disabled = false;
                    if (!data.ok) { if (err) err.textContent = T.error || ''; return; }
                    hasContact = true; card.remove(); clearTimeout(waitTimer);
                    add(formatReply(data.message), 'assistant', data.created_at);
                    if (data.id > lastId) lastId = data.id;
                }).catch(function () { btn.disabled = false; if (err) err.textContent = T.error || ''; });
            });
            el.messages.appendChild(card); scrollDown();
        }

        function waitForHuman() {
            clearTimeout(waitTimer);
            if (mode !== 'human' || hasContact) return;
            waitTimer = setTimeout(function () { if (mode === 'human') showContact(); }, (cfg.waitSeconds || 10) * 1000);
        }

        // The conversation was deleted from the panel: forget it and start from scratch.
        function reset() {
            stopPolling();
            write(localStorage, storeKey, null);
            token = null; lastId = 0; mode = 'ai'; started = false; hasContact = false; clearTimeout(waitTimer);
            Array.prototype.slice.call(el.messages.children).forEach(function (c) { if (!c.hasAttribute('data-lc-static')) c.remove(); });
            updateMode();
        }

        function ensureSession() {
            return new Promise(function (resolve) {
                if (token) { resolve(); return; }
                post(cfg.urls.init, { token: null, page: page() }).then(function (data) {
                    token = data.token; write(localStorage, storeKey, token); started = true; resolve();
                });
            });
        }

        function startPolling() { stopPolling(); interval = 3000; idlePolls = 0; schedule(); }
        function stopPolling() { if (pollTimer) { clearTimeout(pollTimer); pollTimer = null; } }
        function schedule() { if (alive) pollTimer = setTimeout(function () { poll().then(schedule); }, interval); }
        function poll() {
            if (!token || !started) return Promise.resolve();
            return get(cfg.urls.poll + '?token=' + encodeURIComponent(token) + '&after=' + lastId).then(function (data) {
                if (data.code === 'session_not_found') { reset(); return; }
                if (data.mode && data.mode !== mode) { mode = data.mode; updateMode(); }
                if (data.messages && data.messages.length > 0) {
                    if (data.messages.some(function (m) { return m.sender === 'admin'; })) clearTimeout(waitTimer);
                    interval = 3000; idlePolls = 0;
                    data.messages.forEach(function (m) {
                        add(formatReply(m.message), m.sender, m.created_at);
                        if (m.id > lastId) lastId = m.id;
                    });
                    if (!isOpen() && el.badge) {
                        el.badge.textContent = parseInt(el.badge.textContent || '0', 10) + data.messages.length;
                        el.badge.classList.add('is-visible');
                    }
                } else {
                    idlePolls++;
                    interval = idlePolls >= 6 ? 15000 : (idlePolls >= 3 ? 10000 : 3000);
                }
            }).catch(function () { return Promise.resolve(); });
        }

        function toggle() {
            if (isOpen()) {
                el.panel.classList.remove('is-open'); root.classList.remove('lc-open');
                if (cfg.lockScroll) document.body.style.overflow = '';
            } else {
                if (!restored) { restored = true; init(); }
                el.panel.classList.add('is-open'); root.classList.add('lc-open');
                if (cfg.lockScroll) document.body.style.overflow = 'hidden';
                if (el.badge) { el.badge.classList.remove('is-visible'); el.badge.textContent = '0'; }
                scrollDown();
                setTimeout(function () { el.input && el.input.focus(); }, 400);
                hideNudge();
            }
        }

        function scrollDown() { el.messages.scrollTop = el.messages.scrollHeight; }
        function pad(n) { return n.toString().padStart(2, '0'); }
        function now() { var d = new Date(); return pad(d.getHours()) + ':' + pad(d.getMinutes()); }
        function time(d) { if (!d) return now(); var t = new Date(d); return isNaN(t.getTime()) ? now() : pad(t.getHours()) + ':' + pad(t.getMinutes()); }
        // Text goes into element content only, never into an attribute, so quotes need no escaping.
        function escapeHtml(s) { var d = document.createElement('div'); d.appendChild(document.createTextNode(s)); return d.innerHTML; }
        // Markdown links and bare URLs both become links. URLs stop at quotes and angle
        // brackets so they cannot break out of href, and trailing punctuation stays outside.
        function formatReply(s) {
            var r = escapeHtml(s), links = [];
            function link(u, t) { links.push('<a href="' + u + '" target="_blank" rel="noopener noreferrer">' + t + '</a>'); return '\u0000' + (links.length - 1) + '\u0000'; }
            r = r.replace(/\[([^\]]+)\]\((https?:\/\/[^\s)"'<>]+)\)/g, function (m, t, u) { return link(u, t); });
            r = r.replace(/https?:\/\/[^\s"'<>\u0000]+/g, function (u) { var tail = ''; while (/[.,;:!?)\]]$/.test(u)) { tail = u.slice(-1) + tail; u = u.slice(0, -1); } return link(u, u) + tail; });
            r = r.replace(/\u0000(\d+)\u0000/g, function (m, i) { return links[+i]; });
            r = r.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
            return r.replace(/\n/g, '<br>');
        }

        function add(html, sender, createdAt) {
            var node = fromTemplate(sender === 'visitor' ? 'visitor' : (sender === 'admin' ? 'admin' : 'bot')) || fromTemplate('bot');
            if (!node) return;
            var text = slot(node, 'text'), when = slot(node, 'time'), who = slot(node, 'sender');
            if (text) text.innerHTML = html;
            if (when) when.textContent = time(createdAt);
            if (who && sender === 'admin') who.textContent = cfg.owner || who.textContent;
            el.messages.appendChild(node);
            scrollDown();
        }

        function showTyping() {
            var node = fromTemplate('typing');
            if (!node) return;
            node.setAttribute('data-lc-typing', '');
            el.messages.appendChild(node); scrollDown();
        }
        function hideTyping() { var t = root.querySelector('[data-lc-typing]'); if (t) t.remove(); }

        function updateMode() {
            if (!el.modeBar) return;
            if (mode === 'human') {
                el.modeBar.hidden = false;
                if (el.modeText) el.modeText.textContent = T.talking || '';
                if (el.modeBtnText) el.modeBtnText.textContent = T.back || '';
            } else {
                el.modeBar.hidden = true;
            }
        }

        function switchToAi() {
            if (!token) return;
            el.modeBtn.disabled = true;
            post(cfg.urls.switch, { token: token }).then(function (data) {
                if (data.mode) mode = data.mode;
                if (data.message) { add(formatReply(data.message.message), data.message.sender, data.message.created_at); if (data.message.id > lastId) lastId = data.message.id; }
                updateMode(); el.modeBtn.disabled = false;
            }).catch(function () { el.modeBtn.disabled = false; });
        }

        function sendMessage() {
            var text = el.input.value.trim();
            if (!text || sending) return;
            sending = true; el.input.value = ''; grow();
            add(escapeHtml(text), 'visitor'); showTyping();
            ensureSession().then(function () {
                return post(cfg.urls.send, { token: token, message: text, page: page() });
            }).then(function (data) {
                if (data.code === 'session_not_found') {
                    reset(); add(escapeHtml(text), 'visitor'); showTyping();
                    return ensureSession().then(function () { return post(cfg.urls.send, { token: token, message: text, page: page() }); });
                }
                return data;
            }).then(function (data) {
                sending = false; hideTyping();
                if (data.error) { add(escapeHtml(data.error), 'assistant'); return; }
                if (data.mode) {
                    var prev = mode; mode = data.mode;
                    if (prev === 'ai' && mode === 'human' && !data.ai_reply) add(escapeHtml(T.transferring || ''), 'assistant');
                    updateMode();
                }
                if (data.ai_reply) { add(formatReply(data.ai_reply), 'assistant', data.ai_reply_at); if (data.ai_reply_id > lastId) lastId = data.ai_reply_id; }
                if (data.has_contact) hasContact = true;
                if (data.ask_contact) showContact();
                waitForHuman();
                startPolling();
            }).catch(function () { sending = false; hideTyping(); add(escapeHtml(T.send_error || 'Error'), 'assistant'); });
        }

        function showNudge() { if (!el.nudge || nudgeDismissed || isOpen() || read(sessionStorage, storeKey + '_nudge') === '1') return; el.nudge.classList.add('is-visible'); }
        function hideNudge() { el.nudge && el.nudge.classList.remove('is-visible'); }
        function dismissNudge() { nudgeDismissed = true; write(sessionStorage, storeKey + '_nudge', '1'); hideNudge(); }

        // The box grows with the text up to its max-height, then scrolls.
        function grow() {
            el.input.style.height = 'auto';
            var max = parseInt(getComputedStyle(el.input).maxHeight, 10) || 160;
            el.input.style.height = Math.min(el.input.scrollHeight + 2, max) + 'px';
            el.input.style.overflowY = el.input.scrollHeight + 2 > max ? 'auto' : 'hidden';
        }

        el.launcher && el.launcher.addEventListener('click', function (e) { e.stopPropagation(); toggle(); });
        el.wrap && el.wrap.addEventListener('click', function () { if (!isOpen()) toggle(); });
        el.close && el.close.addEventListener('click', toggle);
        el.send && el.send.addEventListener('click', sendMessage);
        if (el.input) {
            el.input.addEventListener('input', grow);
            el.input.addEventListener('keydown', function (e) { if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) { e.preventDefault(); sendMessage(); } });
        }
        el.modeBtn && el.modeBtn.addEventListener('click', switchToAi);
        el.nudgeClose && el.nudgeClose.addEventListener('click', function (e) { e.stopPropagation(); dismissNudge(); });
        el.nudge && el.nudge.addEventListener('click', function () { hideNudge(); if (!isOpen()) toggle(); });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && isOpen()) toggle(); });

        // The chat always starts closed. An existing conversation is restored in the
        // background (history, mode, polling) so replies arrive and bump the badge.
        if (token && !restored) { restored = true; init(); }
        else { setTimeout(showNudge, (cfg.nudgeSeconds || 5) * 1000); }

        root.__larachat = {
            open: function () { if (!isOpen()) toggle(); },
            close: function () { if (isOpen()) toggle(); },
            stop: function () { alive = false; stopPolling(); clearTimeout(waitTimer); }
        };
    }

    function setupAll() {
        Array.prototype.forEach.call(document.querySelectorAll('[data-larachat]'), setup);
    }

    window.Larachat = {
        setup: setupAll,
        open: function () { var r = document.querySelector('[data-larachat]'); r && r.__larachat && r.__larachat.open(); }
    };

    // Before a wire:navigate swap, stop the widgets that are about to be replaced;
    // a widget inside @persist stays, keeps its state and is not set up again.
    document.addEventListener('livewire:navigating', function () {
        Array.prototype.forEach.call(document.querySelectorAll('[data-larachat]'), function (root) {
            if (!root.closest('[x-persist]') && root.__larachat) root.__larachat.stop();
        });
    });
    document.addEventListener('livewire:navigated', setupAll);

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', setupAll);
    } else {
        setupAll();
    }
})();
