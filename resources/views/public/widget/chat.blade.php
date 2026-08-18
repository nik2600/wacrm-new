<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $widget->name }}</title>
    <style>
        * {
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            padding: 0;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            height: 100%;
        }

        body {
            display: flex;
            flex-direction: column;
            background: #fff;
        }

        .header {
            height: 56px;
            display: flex;
            align-items: center;
            padding: 0 16px;
            font-weight: 600;
            font-size: 14px;
        }

        .body {
            flex: 1;
            padding: 14px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .bubble {
            max-width: 78%;
            padding: 8px 12px;
            border-radius: 14px;
            font-size: 13.5px;
            line-height: 1.4;
            word-wrap: break-word;
            box-shadow: 0 1px 2px rgba(0, 0, 0, .06);
        }

        .bubble.in {
            align-self: flex-start;
        }

        .bubble.out {
            align-self: flex-end;
            background: #DCF8C6;
            color: #1f2937;
        }

        .typing {
            align-self: flex-start;
            font-size: 11.5px;
            color: #6b7280;
            padding: 4px 8px;
            font-style: italic;
        }

        .intake {
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 10px;
        }

        .intake label {
            display: block;
            font-size: 11px;
            color: #6b7280;
            margin-bottom: 3px;
        }

        .intake input {
            width: 100%;
            padding: 6px 8px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 12.5px;
            margin-bottom: 6px;
        }

        .footer {
            padding: 10px;
            border-top: 1px solid #e5e7eb;
            background: #fff;
        }

        .input-row {
            display: flex;
            gap: 8px;
        }

        .input-row input {
            flex: 1;
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 20px;
            font-size: 13px;
            outline: none;
        }

        .input-row input:focus {
            border-color: #075E54;
        }

        .input-row button {
            padding: 0 16px;
            border: 0;
            border-radius: 20px;
            font-weight: 600;
            font-size: 12.5px;
            cursor: pointer;
        }

        .input-row button:disabled {
            opacity: .5;
            cursor: not-allowed;
        }

        .wa-cta {
            margin-top: 10px;
            display: block;
            text-align: center;
            padding: 8px 12px;
            border-radius: 10px;
            background: #25D366;
            color: #fff;
            font-weight: 600;
            font-size: 12.5px;
            text-decoration: none;
        }
    </style>
</head>

<body>
    <header class="header" id="hdr"></header>
    <div class="body" id="msgs"></div>
    <footer class="footer">
        <div id="intake" class="intake" style="display:none"></div>
        <div class="input-row">
            <input id="txt" type="text" placeholder="{{ __('Type a message…') }}" autocomplete="off" />
            <button id="send" type="button" disabled></button>
        </div>
    </footer>

    <script>
        (function() {
            const TOKEN = "{{ $widget->embed_token }}";
            const API_CFG = "{{ url('/widget/' . $widget->embed_token . '/api/config') }}";
            const API_MSG = "{{ url('/widget/' . $widget->embed_token . '/api/message') }}";
            const API_HIS = "{{ url('/widget/' . $widget->embed_token . '/api/history') }}";

            // Persist the visitor id in localStorage (NOT a cookie). The widget
            // runs in a cross-site iframe where 3rd-party cookies are blocked, so
            // the cookie was lost on reload and every visit started a brand-new
            // conversation. localStorage is the iframe's own-origin storage and
            // survives reloads; we send the id as ?visitor_uuid= so the server
            // stitches back to the same conversation + its message history.
            const VKEY = 'wsnap_vid_' + TOKEN;
            const storedUuid = () => { try { return localStorage.getItem(VKEY) || ''; } catch (e) { return ''; } };
            const storeUuid  = (u) => { try { if (u) localStorage.setItem(VKEY, u); } catch (e) {} };
            const withVid = (url) => {
                const u = storedUuid();
                return u ? (url + (url.indexOf('?') >= 0 ? '&' : '?') + 'visitor_uuid=' + encodeURIComponent(u)) : url;
            };

            let cfg = null;
            let visitorUuid = null;
            let lastSeenMsgId = 0;
            let intakeDone = false;

            const $ = (id) => document.getElementById(id);

            async function loadConfig() {
                const res = await fetch(withVid(API_CFG), {
                    credentials: 'include'
                });
                const data = await res.json();
                cfg = data.widget;
                const vis = data.visitor;
                visitorUuid = vis?.uuid || null;
                storeUuid(visitorUuid); // remember this visitor across reloads
                paintChrome();

                // Repaint the prior conversation on reload. Only show the
                // welcome message for a brand-new visitor with no history.
                const transcript = data.transcript || [];
                if (transcript.length) {
                    transcript.forEach((m) => {
                        addBubble(m.dir === 'out' ? 'out' : 'in', m.body, {
                            id: m.id
                        });
                        if ((m.id || 0) > lastSeenMsgId) lastSeenMsgId = m.id;
                    });
                } else if (cfg.welcome_message) {
                    addBubble('in', cfg.welcome_message);
                }

                if (cfg.whatsapp_url && cfg.mode !== 'ai') addWaCta();
                if ((cfg.collect_name || cfg.collect_email || cfg.collect_phone) && !(vis?.name || vis?.email || vis
                        ?.phone)) {
                    renderIntake();
                } else {
                    intakeDone = true;
                    enableSend();
                }
            }

            function paintChrome() {
                document.body.style.background = (cfg.body_bg_kind === 'image' && cfg.body_bg_image_url) ?
                    '#fff' :
                    (cfg.body_bg_color || '#ECE5DD');
                const msgs = $('msgs');
                if (cfg.body_bg_kind === 'image' && cfg.body_bg_image_url) {
                    // Sanitise the URL before injecting into a CSS string — a
                    // payload like `bg.png') ; ...evil(` would break out of the
                    // url() literal and execute as arbitrary CSS. Reject anything
                    // that isn't a clean http(s) URL, and additionally escape any
                    // single quotes / parens that survive validation.
                    const raw = String(cfg.body_bg_image_url || '');
                    let safeUrl = '';
                    try {
                        const u = new URL(raw, window.location.href);
                        if (u.protocol === 'http:' || u.protocol === 'https:') {
                            safeUrl = u.href.replace(/['"()\\]/g, encodeURIComponent);
                        }
                    } catch (_e) {
                        /* invalid URL → safeUrl stays empty */ }
                    if (safeUrl) {
                        msgs.style.backgroundImage = "url('" + safeUrl + "')";
                        msgs.style.backgroundSize = 'cover';
                    } else {
                        msgs.style.background = cfg.body_bg_color || '#ECE5DD';
                    }
                } else {
                    msgs.style.background = cfg.body_bg_color || '#ECE5DD';
                }
                const hdr = $('hdr');
                hdr.style.background = cfg.header_bg || '#075E54';
                hdr.style.color = cfg.header_text || '#FFFFFF';
                hdr.textContent = cfg.header_title || 'Chat';
                const btn = $('send');
                btn.textContent = cfg.button_label || 'Send';
                btn.style.background = cfg.button_bg || '#075E54';
                btn.style.color = cfg.button_text || '#FFFFFF';
            }

            function addBubble(dir, text, extras) {
                const m = $('msgs');
                const b = document.createElement('div');
                b.className = 'bubble ' + dir;
                if (dir === 'in') {
                    b.style.background = cfg.bubble_color || '#FFFFFF';
                    b.style.color = cfg.bubble_text || '#222222';
                }
                b.textContent = text;
                if (extras?.id) b.dataset.id = extras.id;
                m.appendChild(b);
                m.scrollTop = m.scrollHeight;
            }

            function addWaCta() {
                const a = document.createElement('a');
                a.href = cfg.whatsapp_url;
                a.target = '_blank';
                a.rel = 'noopener';
                a.className = 'wa-cta';
                a.textContent = 'Open WhatsApp';
                $('msgs').appendChild(a);
            }

            function renderIntake() {
                const i = $('intake');
                let html =
                    '<div style="font-size:12px;color:#374151;margin-bottom:6px;font-weight:600">Tell us a bit about you</div>';
                if (cfg.collect_name) html += '<label>Name</label><input id="i-name" type="text"/>';
                if (cfg.collect_email) html += '<label>Email</label><input id="i-email" type="email"/>';
                if (cfg.collect_phone) html += '<label>Phone</label><input id="i-phone" type="tel"/>';
                html +=
                    '<button id="i-go" type="button" style="width:100%;padding:7px;background:#075E54;color:#fff;border:0;border-radius:6px;font-weight:600;font-size:12.5px;cursor:pointer">Continue</button>';
                i.innerHTML = html;
                i.style.display = 'block';
                $('i-go').onclick = () => {
                    intakeDone = true;
                    i.style.display = 'none';
                    enableSend();
                    $('txt').focus();
                };
            }

            function enableSend() {
                $('send').disabled = false;
            }

            async function send() {
                const txt = $('txt');
                const body = (txt.value || '').trim();
                if (!body) return;
                txt.value = '';
                addBubble('out', body);
                const payload = {
                    body
                };
                const vid = storedUuid();
                if (vid) payload.visitor_uuid = vid; // stitch to the same conversation
                if (intakeDone) {
                    const n = $('i-name');
                    if (n && n.value) payload.name = n.value;
                    const e = $('i-email');
                    if (e && e.value) payload.email = e.value;
                    const p = $('i-phone');
                    if (p && p.value) payload.phone = p.value;
                }
                const typing = document.createElement('div');
                typing.className = 'typing';
                typing.textContent = 'Typing…';
                $('msgs').appendChild(typing);
                $('msgs').scrollTop = $('msgs').scrollHeight;

                try {
                    const res = await fetch(API_MSG, {
                        method: 'POST',
                        credentials: 'include',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify(payload),
                    });
                    const json = await res.json();
                    typing.remove();
                    if (json.reply?.body) {
                        addBubble('in', json.reply.body, {
                            id: json.reply.id
                        });
                        lastSeenMsgId = Math.max(lastSeenMsgId, json.reply.id || 0);
                    }
                } catch (e) {
                    typing.remove();
                    addBubble('in', "Sorry, that didn't go through. Please try again.");
                }
            }

            $('send').addEventListener('click', send);
            $('txt').addEventListener('keydown', (e) => {
                if (e.key === 'Enter') send();
            });

            // Poll for human-agent replies that landed via the team inbox.
            async function poll() {
                try {
                    const res = await fetch(withVid(API_HIS + '?since=' + lastSeenMsgId), {
                        credentials: 'include'
                    });
                    const json = await res.json();
                    (json.messages || []).forEach((m) => {
                        // Render EVERY business reply we haven't shown yet — not just
                        // from==='agent'. A human team-inbox reply carries no AI
                        // agent_id, so history() labels it 'assistant' (same as the
                        // widget's own AI reply); the old from==='agent' filter dropped
                        // it, so a human agent's reply only appeared on page reload.
                        // The `since` cursor already excludes the AI reply shown inline
                        // on send; we dedupe by id as a belt-and-suspenders guard so
                        // nothing renders twice.
                        if (!document.querySelector('[data-id="' + m.id + '"]')) {
                            addBubble('in', m.body, {
                                id: m.id
                            });
                        }
                        if (m.id > lastSeenMsgId) lastSeenMsgId = m.id;
                    });
                } catch {}
            }
            setInterval(poll, 4000);

            loadConfig();
        })();
    </script>
</body>

</html>
