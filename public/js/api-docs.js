/*
 * WaDesk API reference renderer.
 *
 * Pulls the live OpenAPI 3 document that Scramble generates at /docs/api.json
 * and renders a fully WaDesk-branded reference into the page (the visual shell
 * + intro live in the blade; this fills the sidebar nav + endpoint blocks).
 * Kept dependency-free and outside the Vite/Tailwind build on purpose — the
 * markup is generated at runtime so it never needs class purging.
 */
(function () {
    'use strict';

    var SPEC_URL = (window.WADESK_API && window.WADESK_API.specUrl) || '/docs/api.json';

    // Preferred display order for the endpoint groups (anything else appended).
    // Names MUST match the tag names emitted by the spec (Scramble uses the
    // controller name minus "Controller" — singular).
    var GROUP_ORDER = [
        'Message', 'Template', 'Media', 'Contact', 'ContactGroup', 'Broadcast',
        'Campaign', 'Scheduled', 'AutoReply', 'Flow', 'Device', 'Webhook',
        'Deal', 'Conversation', 'Account',
    ];

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    function el(html) {
        var d = document.createElement('div');
        d.innerHTML = html.trim();
        return d.firstChild;
    }

    // ---- OpenAPI helpers ----
    function resolveRef(node, spec) {
        if (node && node.$ref) {
            var path = node.$ref.replace(/^#\//, '').split('/');
            var cur = spec;
            for (var i = 0; i < path.length; i++) cur = cur && cur[path[i]];
            return cur || {};
        }
        return node || {};
    }

    // Realistic demo value chosen from the property NAME first, then its type —
    // so example payloads read like real data, not {"name":"string"}.
    function demoValue(key, schema, t) {
        var k = String(key || '').toLowerCase();
        if (schema && schema.enum && schema.enum.length) return schema.enum[0];
        if (schema && schema.format === 'date-time') return '2026-06-13T10:24:00Z';

        if (/(^|_)id$/.test(k)) return 1024;
        if (k.indexOf('email') > -1) return 'jane@example.com';
        if (k === 'to' || k.indexOf('phone') > -1 || k.indexOf('mobile') > -1 || k.indexOf('number') > -1) return '919812345678';
        if (k === 'first_name') return 'Jane';
        if (k === 'last_name') return 'Doe';
        if (k === 'middle_name') return '';
        if (k === 'keyword') return 'hello';
        if (k === 'template_name') return 'order_update';
        if (k === 'template_body' || k === 'body') return 'Your order {{1}} is on the way!';
        if (k === 'campaign_name' || k === 'broadcast_name') return 'June Promo';
        if (k.indexOf('slug') > -1) return 'acme-corp';
        if (k.indexOf('name') > -1) return 'Acme Corp';
        if (k.indexOf('timezone') > -1) return 'Asia/Kolkata';
        if (k.indexOf('currency') > -1) return 'USD';
        if (k.indexOf('language') > -1) return 'en_US';
        if (k.indexOf('url') > -1) return 'https://example.com/image.jpg';
        if (k.indexOf('category') > -1) return 'MARKETING';
        if (k.indexOf('status') > -1) return 'sent';
        if (k.indexOf('type') > -1) return 'text';
        if (k === 'code') return 'invalid_request';
        if (k.indexOf('message') > -1 || k.indexOf('body') > -1 || k.indexOf('text') > -1) return 'Hello from WaDesk';
        if (k.indexOf('latitude') > -1 || k === 'lat') return 19.0760;
        if (k.indexOf('longitude') > -1 || k === 'lng' || k === 'lon') return 72.8777;
        if (k.indexOf('count') > -1 || k.indexOf('total') > -1) return 12;
        if (k.indexOf('_at') > -1 || k.indexOf('date') > -1 || k.indexOf('time') > -1) return '2026-06-13T10:24:00Z';
        if (k.indexOf('is_') === 0 || k.indexOf('active') > -1 || k.indexOf('enabled') > -1) return true;

        if (t === 'integer' || t === 'number') return 1;
        if (t === 'boolean') return true;
        return 'example';
    }

    function sampleFor(schema, spec, depth, key) {
        depth = depth || 0;
        schema = resolveRef(schema, spec);
        if (!schema || depth > 6) return null;
        if (schema.example !== undefined) return schema.example;
        if (schema.default !== undefined) return schema.default;

        var t = schema.type;
        if (Array.isArray(t)) t = t.filter(function (x) { return x !== 'null'; })[0] || t[0];

        if (schema.properties || t === 'object') {
            var o = {};
            var props = schema.properties || {};
            Object.keys(props).forEach(function (k) {
                o[k] = sampleFor(props[k], spec, depth + 1, k);
            });
            return o;
        }
        if (t === 'array') {
            var items = schema.items;
            var itemSchema = Array.isArray(items) ? (items[0] || {}) : (items || {});
            var sample = sampleFor(itemSchema, spec, depth + 1, key);
            return (sample == null) ? [] : [sample];
        }
        return demoValue(key, schema, t);
    }

    // Friendly label + canonical example body for a status code when the spec
    // doesn't carry one (errors use the API's standard envelope).
    function respLabel(code) {
        return ({
            '200': 'OK', '201': 'Created', '202': 'Accepted', '204': 'No content',
            '400': 'Bad request', '401': 'Unauthenticated', '402': 'Payment required',
            '403': 'Forbidden', '404': 'Not found', '409': 'Conflict',
            '422': 'Validation error', '429': 'Too many requests', '500': 'Server error',
        })[code] || '';
    }
    function canonicalEnvelope(code) {
        switch (code) {
            case '401': return { error: { code: 'missing_api_key', message: 'No API key provided. Send it as "Authorization: Bearer <key>".' } };
            case '402': return { error: { code: 'out_of_credits', message: 'Out of message credits.' } };
            case '403': return { error: { code: 'forbidden', message: 'You do not have access to this resource.' } };
            case '404': return { error: { code: 'not_found', message: 'Resource not found.' } };
            case '422': return { message: 'The given data was invalid.', errors: { field: ['The field is required.'] } };
            case '429': return { error: { code: 'rate_limited', message: 'Too many requests.' } };
            case '500': return { error: { code: 'server_error', message: 'Something went wrong.' } };
        }
        if (code[0] === '2') return { data: {} };
        return { error: { code: 'error', message: 'Request failed.' } };
    }
    // Hand-authored realistic demo resources per tag. Scramble's inferred
    // response schemas are too thin to make good examples, so for success
    // responses we render these instead — wrapped in the { data, meta } envelope.
    var DEMO = {
        Account: {
            account: { name: 'Jane Doe', email: 'jane@example.com' },
            workspace: { id: 1, name: 'Acme Corp', slug: 'acme-corp', timezone: 'Asia/Kolkata', currency: 'USD' },
            plan: { id: 3, name: 'Pro', is_free: false },
            limits: { monthly_messages_limit: null, contacts_limit: null, device_limit: null, user_seat_limit: null, flow_limit: null },
            features: { broadcast: true, campaign: true, autoflow: true, schedulemessage: true, autoreply: true, template: true, access_ai_agents: true, access_outbound_webhooks: true }
        },
        Usage: {
            period: { label: 'June 2026', resets_on: 'Jul 1', days_left: 18 },
            messages: { used: 979, limit: null, unlimited: true, remaining: null, percent: 0 },
            credits: 1250,
            meters: {
                monthly_messages_limit: { label: 'Messages this month', used: 979, limit: null, unlimited: true, percent: 0 },
                contacts_limit: { label: 'Contacts', used: 223, limit: null, unlimited: true, percent: 0 },
                device_limit: { label: 'Connected numbers', used: 1, limit: null, unlimited: true, percent: 0 }
            }
        },
        Message: { id: 90432, to: '+919812345678', type: 'text', status: 'sent', body: 'Hello from WaDesk', created_at: '2026-06-13T10:24:00.000000Z' },
        Contact: {
            id: 1024, name: 'Jane Doe', phone: '+919812345678', email: 'jane@example.com',
            tags: ['VIP'], attributes: [], created_at: '2026-06-13T10:24:00+00:00'
        },
        ContactGroup: { id: 42, name: 'VIP customers', note: 'Top spenders', color: '#075E54', contacts_count: 318, created_at: '2026-06-13T10:24:00+00:00' },
        Template: {
            id: 135, name: 'order_update', type: 'standard', category: 'marketing', language: 'en_US',
            header: 'Order shipped', header_location: null, body: 'Hi {{1}}, your order {{2}} is on the way!',
            footer: 'WaDesk', buttons: [{ type: 'url', text: 'Track order', value: 'https://example.com/track' }],
            carousel_data: [], attachment_type: null, status: 'approved',
            created_at: '2026-06-13T10:24:00+00:00', updated_at: '2026-06-13T10:24:00+00:00'
        },
        Broadcast: {
            id: 781, name: 'June Promo', status: 'completed', template_id: 135, device_id: 54, total_recipients: 1200,
            counts: { sent: 1180, delivered: 1120, read: 760, failed: 20, clicked: 90, pending: 0 },
            scheduled_at: null, completed_at: '2026-06-13T10:30:00+00:00',
            created_at: '2026-06-13T10:24:00+00:00', updated_at: '2026-06-13T10:30:00+00:00'
        },
        Campaign: {
            id: 305, name: 'June Promo', type: 'template', status: 'running', device_id: 54, template_id: 135, flow_id: null,
            ab_testing: false, ab_split: null,
            counts: { total_recipients: 1200, sent: 540, delivered: 510, read: 320, failed: 6, clicked: 88, responded: 41 },
            metrics: { delivery_rate: 94.4, read_rate: 59.2, click_rate: 16.3, response_rate: 7.6 },
            schedule_type: 'now', scheduled_for: null, timezone: 'Asia/Kolkata',
            created_at: '2026-06-13T10:24:00+00:00', updated_at: '2026-06-13T10:24:00+00:00', completed_at: null
        },
        Scheduled: {
            id: 412, name: 'Restock Notification', type: 'text', status: 'completed', message: 'Your item is back in stock!',
            template_id: null, device_id: 54, recipient_count: 113, run_at: '2026-06-14T09:00:00+00:00', next_run_at: null,
            timezone: 'Asia/Kolkata', total_sent: 113, total_delivered: 107, total_failed: 6, created_at: '2026-06-13T10:24:00+00:00'
        },
        AutoReply: {
            id: 95, keyword: 'hello, hi', matching_method: 'fuzzy', fuzzy_similarity: 80, device_id: 54,
            reply_type: 'custom', flow_id: null, message_type: 'text', status: true,
            replies: [{ id: 25, type: 'text', content: 'Hi! How can we help?', url: null, template_id: null, original_name: null, is_selected: true }],
            created_at: '2026-06-13T10:24:00.000000Z', updated_at: '2026-06-13T10:24:00.000000Z'
        },
        Flow: { id: 39, name: 'Welcome flow', trigger: 'keyword', status: 'live', category: 'onboarding', subscribers_count: 214, created_at: '2026-06-13T10:24:00+00:00', updated_at: '2026-06-13T10:24:00+00:00' },
        Device: { id: 54, name: 'Sales line', phone: '919812345678', engine: 'unofficial', status: 'connected', active: true, last_seen_at: '2026-06-13T10:24:00+00:00' },
        Webhook: { id: 9, url: 'https://example.com/webhooks/wadesk', events: ['message_received', 'message_sent'], active: true, created_at: '2026-06-13T10:24:00+00:00' }
    };
    var EXTRA = {
        FlowSubscriber: { id: 600, contact_id: 1024, contact_name: 'Jane Doe', contact_phone: '919812345678', status: 'active', enrolled_at: '2026-06-13T10:24:00+00:00', failed_at: null, failure_reason: null },
        Recipient: { contact_id: 1024, status: 'delivered', error: null, wa_message_id: 'SUKIB402F5F613E7B963', sent_at: '2026-06-13 10:24:04', delivered_at: '2026-06-13 10:24:05', read_at: null },
        CampaignStats: {
            campaigns: { total: 21, active: 3, completed: 14, scheduled: 1, failed: 0 },
            messages: { total_recipients: 2098, sent: 1924, delivered: 1815, read: 1266, failed: 103, clicked: 203, responded: 214 },
            rates: { delivery_rate: 94.33, read_rate: 69.75, click_rate: 10.55, response_rate: 11.12 }
        },
        Categories: [{ id: 1, name: 'Marketing' }, { id: 2, name: 'Authentication' }, { id: 3, name: 'Utility' }]
    };

    function isListGet(method, path) {
        if (method !== 'get') return false;
        if (/\{[a-z_]+\}$/i.test(path)) return false;           // ends with /{id} → single
        if (/(\/me|\/usage|\/statistics|\/categories)$/.test(path)) return false;
        return true;                                            // top-level collection
    }

    function successExample(tag, method, path) {
        if (/\/enroll$/.test(path)) return { data: { enrolled: true, flow_id: 39, contact_id: 1024 } };
        if (/\/subscribers$/.test(path)) return { data: [EXTRA.FlowSubscriber], meta: { count: 1 } };
        if (/\/recipients$/.test(path)) return { data: [EXTRA.Recipient], meta: { count: 1, stats: { sent: 540, delivered: 510, read: 320, failed: 6 } } };
        if (/\/stop$/.test(path)) return { data: Object.assign({}, DEMO[tag] || { id: 1024 }, { status: 'cancelled' }) };
        if (/\/statistics$/.test(path)) return { data: EXTRA.CampaignStats };
        if (/\/categories$/.test(path)) return { data: EXTRA.Categories };
        if (method === 'delete') return { data: { deleted: true, message: 'Deleted successfully.' } };
        if (path === '/me') return { data: DEMO.Account };
        if (path === '/usage') return { data: DEMO.Usage };
        var resource = DEMO[tag] || { id: 1024 };
        if (isListGet(method, path)) return { data: [resource], meta: listMeta(tag) };
        return { data: resource };
    }

    // List endpoints return slightly different meta shapes — contacts paginate,
    // webhooks include the catalogue of subscribable events.
    function listMeta(tag) {
        if (tag === 'Contact') return { page: 1, per_page: 25, total: 1, last_page: 1 };
        if (tag === 'Webhook') return {
            count: 1,
            available_events: ['message_received', 'message_sent', 'message_delivered', 'message_read', 'message_failed',
                'broadcast_status_updated', 'campaign_status_updated', 'campaign_contact_clicked', 'campaign_contact_replied',
                'contact_opt_in', 'contact_updated', 'device_status_updated']
        };
        return { count: 1 };
    }

    function responseExample(code, r, spec, ctx) {
        if (code[0] === '2') return successExample(ctx.tag, ctx.method, ctx.path);
        return canonicalEnvelope(code);
    }

    function highlightJson(obj) {
        var json = JSON.stringify(obj, null, 2);
        return esc(json)
            .replace(/&quot;([^&]+?)&quot;(\s*:)/g, '<span class="tok-key">&quot;$1&quot;</span>$2')
            .replace(/: (&quot;.*?&quot;)/g, ': <span class="tok-str">$1</span>')
            .replace(/: (-?\d+\.?\d*)/g, ': <span class="tok-num">$1</span>')
            .replace(/: (true|false|null)/g, ': <span class="tok-bool">$1</span>');
    }

    function copyIcon() {
        return '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6">' +
            '<rect x="5" y="5" width="8" height="9" rx="1.5"/><path d="M3 11V3a1 1 0 0 1 1-1h6"/></svg>';
    }

    // displayHtml = already-escaped/highlighted markup shown inside <pre>;
    // copyText = the raw plain text placed on the clipboard.
    function codeBlock(displayHtml, copyText) {
        var wrap = el('<div class="code-wrap"></div>');
        wrap.innerHTML = '<button class="copybtn" type="button" title="Copy">' + copyIcon() + '</button>' +
            '<pre class="code">' + displayHtml + '</pre>';
        wrap.querySelector('.copybtn').addEventListener('click', function () {
            navigator.clipboard && navigator.clipboard.writeText(copyText);
            var b = this;
            b.innerHTML = '<svg viewBox="0 0 16 16" fill="none" stroke="#25D366" stroke-width="2"><path d="M3 8.5l3.5 3.5L13 5"/></svg>';
            setTimeout(function () { b.innerHTML = copyIcon(); }, 1400);
        });
        return wrap;
    }

    // Resolve a requestBody to the one content type we should document.
    // JSON wins when present; otherwise take whatever the spec offers (file
    // uploads arrive as multipart/form-data). Returns {type, schema}.
    function pickBody(requestBody, spec) {
        var rb = resolveRef(requestBody, spec);
        var content = rb && rb.content;
        if (!content) return { type: null, schema: null };

        var order = ['application/json', 'multipart/form-data', 'application/x-www-form-urlencoded'];
        var type = null;
        for (var i = 0; i < order.length; i++) {
            if (content[order[i]]) { type = order[i]; break; }
        }
        if (!type) type = Object.keys(content)[0] || null;
        if (!type) return { type: null, schema: null };

        return { type: type, schema: resolveRef(content[type], spec).schema || null };
    }

    function buildCurl(method, url, bodyExample, bodyType, bodySchema, spec) {
        var lines = ["curl -X " + method.toUpperCase() + " '" + url + "' \\"];
        lines.push("  -H 'Authorization: Bearer YOUR_API_KEY' \\");

        var hasBody = bodyExample && Object.keys(bodyExample).length;
        if (hasBody && bodyType && bodyType.indexOf('multipart/') === 0) {
            // -F, not -d: curl sets the multipart boundary itself, so we must
            // NOT send a Content-Type header here. Binary fields become @file
            // so the example is copy-paste runnable.
            var props = (resolveRef(bodySchema, spec) || {}).properties || {};
            Object.keys(bodyExample).forEach(function (k) {
                var p = resolveRef(props[k] || {}, spec);
                var isFile = p.format === 'binary' || p.format === 'base64';
                var v = isFile ? '@/path/to/file' : String(bodyExample[k]);
                lines.push("  -F '" + k + "=" + v + "' \\");
            });
        } else if (hasBody) {
            lines.push("  -H 'Content-Type: application/json' \\");
            lines.push("  -d '" + JSON.stringify(bodyExample) + "'");
        }

        // Drop the trailing backslash on whatever ended up last.
        lines[lines.length - 1] = lines[lines.length - 1].replace(/ \\$/, '');
        return lines.join('\n');
    }

    function paramRows(params, spec) {
        if (!params || !params.length) return '';
        var rows = params.map(function (p) {
            p = resolveRef(p, spec);
            var sch = p.schema || {};
            var type = Array.isArray(sch.type) ? sch.type[0] : (sch.type || 'string');
            return '<tr><td><span class="pname">' + esc(p.name) + '</span> ' +
                (p.required ? '<span class="req">required</span>' : '') +
                '<div class="ptype">' + esc(p.in) + ' &middot; ' + esc(type) + '</div></td>' +
                '<td class="pdesc">' + esc(p.description || '') + '</td></tr>';
        }).join('');
        return '<div class="sec-label">Parameters</div><table class="params"><thead><tr><th>Name</th><th>Description</th></tr></thead><tbody>' + rows + '</tbody></table>';
    }

    function bodyRows(schema, spec) {
        schema = resolveRef(schema, spec);
        var props = schema.properties || {};
        var required = schema.required || [];
        var keys = Object.keys(props);
        if (!keys.length) return '';
        var rows = keys.map(function (k) {
            var ps = resolveRef(props[k], spec);
            var type = Array.isArray(ps.type) ? ps.type[0] : (ps.type || 'string');
            if (type === 'array') type = 'array';
            var enumHint = ps.enum ? ' (' + ps.enum.map(esc).join(', ') + ')' : '';
            return '<tr><td><span class="pname">' + esc(k) + '</span> ' +
                (required.indexOf(k) > -1 ? '<span class="req">required</span>' : '') +
                '<div class="ptype">' + esc(type) + '</div></td>' +
                '<td class="pdesc">' + esc(ps.description || '') + esc(enumHint) + '</td></tr>';
        }).join('');
        return '<div class="sec-label">Body parameters</div><table class="params"><thead><tr><th>Field</th><th>Description</th></tr></thead><tbody>' + rows + '</tbody></table>';
    }

    function respClass(code) {
        if (code[0] === '2') return 'resp-2xx';
        if (code[0] === '5') return 'resp-5xx';
        return 'resp-4xx';
    }

    function slug(s) { return String(s).toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, ''); }


    function renderEndpoint(op, method, path, baseUrl, spec, tag) {
        var verb = method.toLowerCase();
        var id = 'op-' + verb + '-' + slug(path);
        // Take whichever content type the spec actually used. Endpoints that
        // accept a file upload (POST /messages, POST /media) are emitted as
        // multipart/form-data, NOT application/json — reading only the JSON key
        // rendered them with no body parameters and a curl with no -d at all.
        // NOTE: named bodySpec, not body — a `var body` div is declared below and
        // the two collided. Since `var` is function-scoped, `body.type` at the curl
        // call was reading .type off the DIV (undefined), so multipart endpoints
        // (POST /messages, POST /media) were documented with a JSON curl.
        var bodySpec = pickBody(op.requestBody, spec);
        var bodySchema = bodySpec.schema;
        var example = bodySchema ? sampleFor(bodySchema, spec) : null;

        var fullUrl = baseUrl + path;
        var card = el('<div class="endpoint" id="' + id + '"></div>');

        var head = el(
            '<div class="head">' +
            '<span class="verb-badge b-' + verb + '">' + esc(method) + '</span>' +
            '<span class="path"><span class="pre">' + esc(baseUrl.replace(/^https?:\/\/[^/]+/, '')) + '</span>' + esc(path) + '</span>' +
            '<span class="summary">' + esc(op.summary || '') + '</span>' +
            '<svg class="chev" width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 4l4 4-4 4"/></svg>' +
            '</div>'
        );
        head.addEventListener('click', function () { card.classList.toggle('open'); });
        card.appendChild(head);

        var body = el('<div class="body"></div>');
        if (op.description && op.description !== op.summary) {
            body.appendChild(el('<p class="desc">' + esc(op.description) + '</p>'));
        }

        var pr = paramRows(op.parameters, spec);
        if (pr) body.appendChild(el('<div>' + pr + '</div>'));

        var br = bodyRows(resolveRef(bodySchema || {}, spec), spec);
        if (br) body.appendChild(el('<div>' + br + '</div>'));

        body.appendChild(el('<div class="sec-label">Example request</div>'));
        var curl = buildCurl(method, fullUrl, example, bodySpec.type, bodySchema, spec);
        body.appendChild(codeBlock(esc(curl), curl));

        if (example && Object.keys(example).length) {
            body.appendChild(el('<div class="sec-label">Request body</div>'));
            body.appendChild(codeBlock(highlightJson(example), JSON.stringify(example, null, 2)));
        }

        var resp = op.responses || {};
        var codes = Object.keys(resp);
        if (codes.length) {
            body.appendChild(el('<div class="sec-label">Responses</div>'));
            codes.forEach(function (c) {
                var r = resolveRef(resp[c], spec);
                var block = el('<div class="resp-block"></div>');
                block.appendChild(el('<div class="resp-row"><span class="resp-code ' + respClass(c) + '">' + esc(c) +
                    '</span><span class="resp-desc">' + esc(r.description || respLabel(c)) + '</span></div>'));
                var ex = responseExample(c, r, spec, { tag: tag, method: verb, path: path });
                if (ex) block.appendChild(codeBlock(highlightJson(ex), JSON.stringify(ex, null, 2)));
                body.appendChild(block);
            });
        }

        card.appendChild(body);
        return { node: card, id: id };
    }

    function orderedGroups(map) {
        var keys = Object.keys(map);
        keys.sort(function (a, b) {
            var ia = GROUP_ORDER.indexOf(a), ib = GROUP_ORDER.indexOf(b);
            if (ia === -1) ia = 999; if (ib === -1) ib = 999;
            if (ia !== ib) return ia - ib;
            return a.localeCompare(b);
        });
        return keys;
    }

    function render(spec) {
        var nav = document.getElementById('api-nav');
        var main = document.getElementById('api-reference');
        nav.innerHTML = '';
        main.innerHTML = '';

        var baseUrl = (spec.servers && spec.servers[0] && spec.servers[0].url) ||
            (location.origin + '/api/v1');
        baseUrl = baseUrl.replace(/\/$/, '');
        // The OpenAPI server URL can carry the docs mount prefix (e.g.
        // ".../docs/api/v1") when the spec was generated/cached while served
        // under /docs. The real, callable API lives at /api/v1 — never under
        // /docs — so strip that segment. Fixes both the path badge AND the
        // curl example, which would otherwise show an uncallable /docs/api/v1 URL.
        baseUrl = baseUrl.replace(/\/docs(?=\/api\/)/, '');

        // Group operations by their first tag.
        var groups = {};
        Object.keys(spec.paths || {}).forEach(function (path) {
            // Backstop: never show internal integrations (the Sheets add-on lives
            // under /api/v1 but is not part of the customer API).
            if (path.toLowerCase().indexOf('sheets') > -1) return;
            var methods = spec.paths[path];
            Object.keys(methods).forEach(function (m) {
                if (['get', 'post', 'put', 'patch', 'delete'].indexOf(m) === -1) return;
                var op = methods[m];
                var tag = (op.tags && op.tags[0]) || 'General';
                if (String(tag).toLowerCase().indexOf('sheet') > -1) return;
                (groups[tag] = groups[tag] || []).push({ op: op, m: m, path: path });
            });
        });

        // Per-service overview text, from the OpenAPI `tags` array (Scramble
        // fills each tag's description from the controller's class-level
        // docblock). We show only the FIRST paragraph so internal
        // implementation notes further down the docblock never leak to
        // customers. Keyed case-insensitively by tag name.
        var tagDesc = {};
        (spec.tags || []).forEach(function (t) {
            if (!t || !t.name || !t.description) return;
            var first = String(t.description).split(/\n\s*\n/)[0].replace(/\s+/g, ' ').trim();
            if (first) tagDesc[t.name.toLowerCase()] = first;
        });

        var navLinks = [];
        orderedGroups(groups).forEach(function (tag) {
            var items = groups[tag];
            // keep a stable order: by path then a sensible verb order
            var verbRank = { post: 0, get: 1, put: 2, patch: 3, delete: 4 };
            items.sort(function (a, b) {
                if (a.path !== b.path) return a.path.localeCompare(b.path);
                return (verbRank[a.m] || 9) - (verbRank[b.m] || 9);
            });

            var groupId = 'grp-' + slug(tag);
            var desc = tagDesc[String(tag).toLowerCase()];
            main.appendChild(el('<div class="group-head" id="' + groupId + '"><h2>' + esc(tag) + '</h2>' +
                (desc ? '<p class="group-desc">' + esc(desc) + '</p>' : '') + '</div>'));

            var navGroup = el('<div class="nav-group"><div class="label">' + esc(tag) + '</div></div>');

            items.forEach(function (it) {
                var rendered = renderEndpoint(it.op, it.m, it.path, baseUrl, spec, tag);
                main.appendChild(rendered.node);

                var link = el('<a class="nav-link" href="#' + rendered.id + '">' +
                    '<span class="verb v-' + it.m + '">' + esc(it.m.toUpperCase()) + '</span>' +
                    '<span class="txt">' + esc(it.path) + '</span></a>');
                link.addEventListener('click', function () {
                    var card = document.getElementById(rendered.id);
                    if (card) card.classList.add('open');
                });
                navGroup.appendChild(link);
                navLinks.push({ link: link, id: rendered.id });
            });

            nav.appendChild(navGroup);
        });

        // sidebar filter
        var search = document.getElementById('api-search');
        if (search) {
            search.addEventListener('input', function () {
                var q = this.value.toLowerCase();
                navLinks.forEach(function (n) {
                    var show = n.link.textContent.toLowerCase().indexOf(q) > -1;
                    n.link.style.display = show ? '' : 'none';
                });
            });
        }

        // active-link highlight on scroll
        var byId = {};
        navLinks.forEach(function (n) { byId[n.id] = n.link; });
        var obs = new IntersectionObserver(function (entries) {
            entries.forEach(function (e) {
                if (e.isIntersecting && byId[e.target.id]) {
                    navLinks.forEach(function (n) { n.link.classList.remove('active'); });
                    byId[e.target.id].classList.add('active');
                }
            });
        }, { rootMargin: '-60px 0px -70% 0px' });
        navLinks.forEach(function (n) {
            var c = document.getElementById(n.id);
            if (c) obs.observe(c);
        });
    }

    function init() {
        var main = document.getElementById('api-reference');
        fetch(SPEC_URL, { headers: { 'Accept': 'application/json' }, credentials: 'include' })
            .then(function (r) {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.json();
            })
            .then(render)
            .catch(function (err) {
                if (main) {
                    main.innerHTML = '<div class="errbox">Could not load the API specification (' +
                        esc(err.message) + ').<br>Make sure <code>' + esc(SPEC_URL) + '</code> is reachable.</div>';
                }
            });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
