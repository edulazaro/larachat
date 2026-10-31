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
 *   [data-lc="nudge"], [data-lc="nudge-close"]   the "need help?" bubble, .is-visible;
 *                              an optional [data-lc="nudge-text"] inside it takes other texts
 *   [data-lc="mode-bar"], [data-lc="mode-text"], [data-lc="mode-btn"], [data-lc="mode-btn-text"]
 *
 * and builds messages from <template data-lc-template="visitor|bot|admin|typing|contact">,
 * filling [data-lc-slot="text|time|sender"] (admin falls back to bot).
 *
 * The contact template is the contact form, which the server places in the
 * conversation as a message of its own when it is time to ask for the
 * visitor's details: [data-lc-slot="title"], [data-lc-slot="privacy"],
 * inputs named name, email and phone, a [data-lc-slot="error"] and a
 * [data-lc="contact-send"] button. There is never more than one on screen,
 * and it stays where it is until it is sent.
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
            nudge: q('nudge'), nudgeClose: q('nudge-close'), nudgeText: q('nudge-text'), modeBar: q('mode-bar'),
            modeText: q('mode-text'), modeBtn: q('mode-btn'), modeBtnText: q('mode-btn-text'),
            particles: q('particles')
        };
        var storeKey = cfg.storageKey || 'larachat_token';

        var token = read(localStorage, storeKey), lastId = 0, pollTimer = null, sending = false,
            started = false, mode = 'ai', interval = 3000, idlePolls = 0, nudgeDismissed = false,
            restored = false, hasContact = false, lead = false, waitTimer = null, alive = true,
            shown = {};

        function read(store, key) { try { return store.getItem(key); } catch (e) { return null; } }
        function write(store, key, value) { try { value === null ? store.removeItem(key) : store.setItem(key, value); } catch (e) {} }

        function isOpen() { return el.panel && el.panel.classList.contains('is-open'); }
        function post(url, data) {
            return fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }, body: JSON.stringify(data) })
                .then(function (r) { return r.json(); });
        }
        // The token goes in a header on GET requests: in the URL it would be in every access log.
        function get(url) { return fetch(url, { headers: { 'Accept': 'application/json', 'X-Larachat-Token': token || '' } }).then(function (r) { return r.json(); }); }
        function page() { return window.location.pathname; }

        function init() {
            if (!token) return;
            post(cfg.urls.init, { token: token, page: page() }).then(function (data) {
                token = data.token; write(localStorage, storeKey, token);
                if (data.mode) mode = data.mode;
                if (data.has_contact) hasContact = true;
                if (data.lead) lead = true;
                if (data.messages && data.messages.length > 0) {
                    started = true;
                    data.messages.forEach(show);
                    updateMode(); startPolling();
                }
            }).catch(function (e) { console.error('Chat init failed:', e); });
        }

        function fromTemplate(name) {
            var tpl = root.querySelector('template[data-lc-template="' + name + '"]');
            return tpl ? tpl.content.firstElementChild.cloneNode(true) : null;
        }
        function slot(node, name) { return node.matches && node.matches('[data-lc-slot="' + name + '"]') ? node : node.querySelector('[data-lc-slot="' + name + '"]'); }

        // A message from the server, once: the reply to a send can also come back
        // in a poll that crossed it, and showing it twice is what this prevents.
        function show(m) {
            if (!m || shown[m.id]) return;
            shown[m.id] = true;
            if (m.id > lastId) lastId = m.id;
            if (m.kind === 'contact_form') { showForm(m); return; }
            add(m.sender === 'visitor' ? escapeHtml(m.message) : formatReply(m.message), m.sender, m.created_at);
        }

        // Characters each form field may not hold, stripped as they are typed: letters for
        // the name, what an address can contain for the email, digits and separators for the phone.
        var ALLOWED = {
            name: /[^\p{L}\p{M} .'\-]/gu,
            email: /[^A-Za-z0-9._%+\-@]/g,
            phone: /[^0-9+() .\-]/g
        };
        var required = cfg.required || ['name', 'email'];

        // The same rules the server applies, checked before sending: [field, message] or null.
        function check(d) {
            if (required.indexOf('name') !== -1 && !d.name) return ['name', T.form_name_required || ''];
            if (required.indexOf('email') !== -1 && !d.email) return ['email', T.form_email_required || ''];
            if (d.email && !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(d.email)) return ['email', T.form_email_invalid || ''];
            var digits = d.phone.replace(/\D/g, '').length;
            if (required.indexOf('phone') !== -1 && !d.phone) return ['phone', T.form_phone_invalid || ''];
            if (d.phone && (digits < 7 || digits > 15)) return ['phone', T.form_phone_invalid || ''];
            return null;
        }

        // Sparks around the standard launcher while the chat is closed, in its colour.
        var sparks = null, still = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        function spark() {
            if (isOpen() || !el.particles) return;
            var p = document.createElement('span'), size = 3 + Math.random() * 4, angle = Math.random() * Math.PI * 2, dist = 30 + Math.random() * 40;
            p.className = 'lc-w-particle';
            p.style.width = p.style.height = size + 'px';
            p.style.left = (20 + Math.random() * 44) + 'px';
            p.style.top = (20 + Math.random() * 44) + 'px';
            p.style.filter = 'brightness(' + (1 + Math.random() * 0.6).toFixed(2) + ')';
            p.style.setProperty('--lc-w-px', Math.cos(angle) * dist + 'px');
            p.style.setProperty('--lc-w-py', Math.sin(angle) * dist + 'px');
            p.addEventListener('animationend', function () { p.remove(); });
            el.particles.appendChild(p);
        }
        function startSparks() { if (el.particles && !still && !sparks) sparks = setInterval(spark, 400); }
        function stopSparks() { if (sparks) { clearInterval(sparks); sparks = null; } if (el.particles) el.particles.innerHTML = ''; }

        function openForm() { return root.querySelector('[data-lc-form]'); }
        function removeForm() { var f = openForm(); if (f) f.remove(); }

        // The contact form, placed by the server as a message of the conversation.
        function showForm(form) {
            if (hasContact) return;
            var card = fromTemplate('contact');
            if (!card) return;
            removeForm();
            card.setAttribute('data-lc-form', form.id);
            var title = slot(card, 'title'), privacy = slot(card, 'privacy'), err = slot(card, 'error'),
                btn = card.querySelector('[data-lc="contact-send"]');
            if (title) title.textContent = form.title || '';
            if (privacy) privacy.innerHTML = form.privacy || '';
            // A field without a label of its own says what it is inside it.
            ['name', 'email', 'phone'].forEach(function (n) { var i = card.querySelector('[name="' + n + '"]'); if (i && !i.placeholder && !i.closest('label')) i.placeholder = T['form_' + n] || ''; });
            var label = btn ? (btn.textContent.trim() || T.form_send || '') : '';
            if (btn) btn.textContent = label;
            // What each field may hold is enforced while typing, not only when sending:
            // characters that cannot belong there never appear.
            Object.keys(ALLOWED).forEach(function (n) {
                var i = card.querySelector('[name="' + n + '"]');
                if (!i) return;
                i.addEventListener('input', function () {
                    var clean = i.value.replace(ALLOWED[n], '');
                    if (clean !== i.value) {
                        var at = i.selectionStart - (i.value.length - clean.length);
                        i.value = clean;
                        try { i.setSelectionRange(at, at); } catch (e) {}
                    }
                });
            });
            var submit = function () {
                var v = function (n) { var i = card.querySelector('[name="' + n + '"]'); return i ? i.value.trim() : ''; };
                var payload = { token: token, name: v('name'), email: v('email'), phone: v('phone') };
                var problem = check(payload);
                if (problem) {
                    if (err) err.textContent = problem[1];
                    var bad = card.querySelector('[name="' + problem[0] + '"]');
                    if (bad) bad.focus();
                    return;
                }
                btn.disabled = true; btn.textContent = T.form_sending || label; if (err) err.textContent = '';
                fetch(cfg.urls.contact, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }, body: JSON.stringify(payload) })
                    .then(function (r) { return r.json().then(function (data) { return { status: r.status, data: data }; }); })
                    .then(function (res) {
                        btn.disabled = false; btn.textContent = label;
                        if (res.status === 422) {
                            var errors = res.data.errors || {};
                            var first = Object.keys(errors)[0];
                            if (err) err.textContent = first ? errors[first][0] : (res.data.message || T.form_error || '');
                            var field = first && card.querySelector('[name="' + first + '"]');
                            if (field) field.focus();
                            return;
                        }
                        if (!res.data.ok) { if (err) err.textContent = T.form_error || ''; return; }
                        hasContact = true; card.remove(); clearTimeout(waitTimer);
                        (res.data.messages || []).forEach(show);
                    })
                    .catch(function () { btn.disabled = false; btn.textContent = label; if (err) err.textContent = T.form_error || ''; });
            };
            btn && btn.addEventListener('click', submit);
            card.addEventListener('keydown', function (e) { if (e.key === 'Enter' && e.target.tagName === 'INPUT') { e.preventDefault(); submit(); } });
            el.messages.appendChild(card); scrollDown();
        }

        // Asks the server for a form the widget itself decides on: the visitor
        // closed the chat after showing interest, or a person has not answered.
        function requestForm(reason) {
            if (hasContact || !token || openForm()) return Promise.resolve(null);
            return post(cfg.urls.form, { token: token, reason: reason }).then(function (data) {
                if (data && data.form) { show(data.form); return data.form; }
                return null;
            }).catch(function () { return null; });
        }

        function waitForHuman() {
            clearTimeout(waitTimer);
            if (mode !== 'human' || hasContact) return;
            waitTimer = setTimeout(function () { if (mode === 'human') requestForm('wait'); }, (cfg.waitSeconds || 10) * 1000);
        }

        // The conversation was deleted from the panel: forget it and start from scratch.
        function reset() {
            stopPolling();
            write(localStorage, storeKey, null);
            token = null; lastId = 0; mode = 'ai'; started = false; hasContact = false; lead = false; shown = {}; clearTimeout(waitTimer);
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
            return get(cfg.urls.poll + '?after=' + lastId).then(function (data) {
                if (data.code === 'session_not_found') { reset(); return; }
                if (data.mode && data.mode !== mode) { mode = data.mode; updateMode(); }
                if (data.has_contact) { hasContact = true; removeForm(); }
                var fresh = (data.messages || []).filter(function (m) { return !shown[m.id]; });
                if (fresh.length > 0) {
                    if (fresh.some(function (m) { return m.sender === 'admin'; })) clearTimeout(waitTimer);
                    interval = 3000; idlePolls = 0;
                    fresh.forEach(show);
                    if (!isOpen() && el.badge) {
                        el.badge.textContent = parseInt(el.badge.textContent || '0', 10) + fresh.length;
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
                startSparks();
                // Leaving after showing interest, without a way to be reached: the form
                // waits in the conversation and the bubble says so.
                if (lead && !hasContact) {
                    requestForm('close').then(function (form) { if (form || openForm()) showNudge(T.nudge_contact, true); });
                }
            } else {
                if (!restored) { restored = true; init(); }
                el.panel.classList.add('is-open'); root.classList.add('lc-open');
                if (cfg.lockScroll) document.body.style.overflow = 'hidden';
                stopSparks();
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
                if (data.message) show(data.message);
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
                if (data.id) shown[data.id] = true;
                if (data.mode) {
                    var prev = mode; mode = data.mode;
                    if (prev === 'ai' && mode === 'human' && !data.ai_reply) add(escapeHtml(T.transferring || ''), 'assistant');
                    updateMode();
                }
                if (data.ai_reply) show({ id: data.ai_reply_id, sender: 'assistant', message: data.ai_reply, created_at: data.ai_reply_at });
                if (data.lead) lead = true;
                if (data.has_contact) { hasContact = true; removeForm(); }
                if (data.form) show(data.form);
                waitForHuman();
                startPolling();
            }).catch(function () { sending = false; hideTyping(); add(escapeHtml(T.send_error || 'Error'), 'assistant'); });
        }

        function showNudge(text, always) {
            if (!el.nudge || isOpen()) return;
            if (!always && (nudgeDismissed || read(sessionStorage, storeKey + '_nudge') === '1')) return;
            if (text && el.nudgeText) el.nudgeText.textContent = text;
            el.nudge.classList.add('is-visible');
        }
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

        // The greeting's time comes from the browser: rendered by the server it was
        // in the server's time zone.
        Array.prototype.forEach.call(root.querySelectorAll('[data-lc-static] [data-lc-slot="time"]'), function (t) { if (!t.textContent.trim()) t.textContent = now(); });
        startSparks();

        // The chat always starts closed. An existing conversation is restored in the
        // background (history, mode, polling) so replies arrive and bump the badge.
        if (token && !restored) { restored = true; init(); }
        else { setTimeout(showNudge, (cfg.nudgeSeconds || 5) * 1000); }

        root.__larachat = {
            open: function () { if (!isOpen()) toggle(); },
            close: function () { if (isOpen()) toggle(); },
            stop: function () { alive = false; stopPolling(); stopSparks(); clearTimeout(waitTimer); }
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
