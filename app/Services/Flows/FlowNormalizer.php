<?php

namespace App\Services\Flows;

/**
 * Convert React flow-builder format → Node-runtime format.
 *
 * The React builder (resources/js/charts/user-flows-builder.js) saves
 * flows as { flowNodes:[{id, type:'message', data:{...}}], flowEdges:[{source, sourceHandle, target}], vars }.
 *
 * The Node runtime (node/services/flowService.js::executeFlowNode)
 * expects { flowNodes:[{id, flowNodeType:'Message', flowReplies:[...], ...}],
 *          flowEdges:[{sourceNodeId:'X_1', targetNodeId:'Y_1'}] }.
 *
 * This normalizer is the single point of truth. The /api/flows/{id}
 * endpoint Node calls runs everything through here so storage shape
 * never has to match what Node consumes — and the runtime never has
 * to learn the React builder's vocabulary.
 *
 * Unmapped types fall through as-is so Node logs a warning instead of
 * crashing — same behaviour you'd get from a typo.
 */
class FlowNormalizer
{
    /**
     * React `type` value → Node `flowNodeType`. Only types Node has a
     * handler for are mapped; the rest fall through to Node's default
     * (skip + warn) which is the safe behaviour.
     */
    public const TYPE_MAP = [
        'message'          => 'Message',
        'sequence'         => 'Message',  // multi-reply node — emits a Message
                                          // with multiple flowReplies the Node
                                          // executor walks in order.
        'media'            => 'Media',
        'google_meet'      => 'GoogleMeet',
        'wa_form'          => 'WaForm',
        'template'         => 'Template',
        'buttons'          => 'InteractiveButtons',
        'list'             => 'List',
        'ask'              => 'Question',
        'condition'        => 'Condition',
        'delay'            => 'TimeDelay',
        'webhook'          => 'Webhook',
        'code'             => 'Code',
        // Call flow (AI voice IVR) — walked by node/services/callFlowRuntime.js.
        'cf_say'           => 'CallSay',
        'cf_listen'        => 'CallListen',
        'cf_ai'            => 'CallAI',
        'cf_menu'          => 'CallMenu',
        'cf_search'        => 'CallSearch',
        'cf_transfer'      => 'CallTransfer',
        'cf_hangup'        => 'CallHangup',
        'mysql'            => 'MySQL',
        'subflow'          => 'SubFlow',
        'assign'           => 'AssignAgent',
        'tag'              => 'TagContact',
        // Commerce nodes — single dispatcher handles all three at runtime
        // (WABA conv → product_list / product native; Baileys → card stack
        // + checkout-link). The TYPE_MAP value tells the Node executor
        // which provider's checkout-link minter to ask Laravel for.
        'whatsapp_shop'    => 'CommerceShop',
        'woocommerce'      => 'CommerceShop',
        'shopify'          => 'CommerceShop',
        'ai'               => 'ChatGPT',
        'cta'              => 'CTA',
        'location'         => 'Location',
        'poll'             => 'Poll',
        'chatbot'          => 'Chatbot',
        'book_appointment' => 'BookAppointment',
        // Google integrations — workspace OAuth (shared with Calendar)
        // powers all three. Sheets has write + read modes; Docs copies a
        // template & fills placeholders; Forms sends a link and pauses.
        'google_sheets'    => 'GoogleSheets',
        'google_docs'      => 'GoogleDocs',
        'google_form'      => 'GoogleForm',
        // Sales Pipeline (CRM) — create a deal or move an existing deal to a
        // stage. The Node executor calls back to /api/flow-node/deal-action.
        'deal'             => 'Deal',
        // 'trigger' is the start node — Node walks from flowNodes[0]
        // unconditionally; no PascalCase type needed.
        // 'end' is terminal — falling through to default ends the flow.
    ];

    /**
     * React source-handle name → Node port index. Used to convert
     * `{source: A, sourceHandle: 'yes'}` into `sourceNodeId: 'A_1'`.
     * Index is 1-based to match Node's existing convention.
     *
     * Keyed by node `type` so the same handle name can mean different
     * indexes on different nodes (e.g. condition's 'yes' = 1 but
     * buttons' 'p0' = 1).
     */
    public const PORT_MAP = [
        'condition' => ['yes' => 1, 'no' => 2],
        'book_appointment' => ['booked' => 1, 'no_slots' => 2],
        // Commerce nodes — purchased port fires when the provider's
        // order.created webhook lands and pings Node; abandoned port
        // fires when the wait window expires with no purchase. Without
        // these entries both handles fall through to port 1 (default),
        // making the abandoned branch unreachable at runtime — the
        // customer who didn't buy gets silently dropped from the flow.
        'whatsapp_shop'    => ['purchased' => 1, 'abandoned' => 2],
        'woocommerce'      => ['purchased' => 1, 'abandoned' => 2],
        'shopify'          => ['purchased' => 1, 'abandoned' => 2],
        // Google Form node — fires `submitted` when the Apps Script
        // webhook lands; `timeout` is currently a passive placeholder
        // (no timer runs yet — sessions just expire).
        'google_form'      => ['submitted' => 1, 'timeout' => 2],
        // Deal node — `created` fires when Laravel confirms the create/move,
        // `error` when the workspace has no pipeline/stage or the call fails.
        'deal'             => ['created' => 1, 'error' => 2],
        // multi-option nodes (buttons/list/poll) use p0, p1, p2... → 1, 2, 3...
    ];

    public function normalize(array $flow): array
    {
        $reactNodes = $flow['flowNodes'] ?? [];
        $reactEdges = $flow['flowEdges'] ?? [];

        // Move the pinned start node (isStart=true) to index 0 so the
        // Node runtime — which walks from flowNodes[0] — begins there.
        // Fall back to the first legacy 'trigger' node, then to keeping
        // the original order for backwards compatibility.
        $startIdx = null;
        foreach ($reactNodes as $i => $n) {
            if (!empty($n['isStart'])) { $startIdx = $i; break; }
        }
        if ($startIdx === null) {
            foreach ($reactNodes as $i => $n) {
                if (($n['type'] ?? null) === 'trigger') { $startIdx = $i; break; }
            }
        }
        if ($startIdx !== null && $startIdx !== 0) {
            $start = $reactNodes[$startIdx];
            array_splice($reactNodes, $startIdx, 1);
            array_unshift($reactNodes, $start);
        }

        $nodes = array_map(fn ($n) => $this->normalizeNode($n), $reactNodes);
        $edges = array_map(fn ($e) => $this->normalizeEdge($e, $reactNodes), $reactEdges);

        return [
            'flowNodes' => $nodes,
            'flowEdges' => $edges,
            'vars'      => $flow['vars'] ?? [],
        ];
    }

    private function normalizeNode(array $n): array
    {
        $type = (string) ($n['type'] ?? $n['flowNodeType'] ?? '');
        $data = (array) ($n['data'] ?? []);
        $pascal = self::TYPE_MAP[$type] ?? $n['flowNodeType'] ?? ucfirst($type);

        // Carry everything React stored, then layer Node-shape fields on top.
        $out = array_merge($n, ['flowNodeType' => $pascal]);

        // Per-type field shaping. We only translate fields that Node's
        // executor reads directly — anything else stays in `data` for
        // downstream handlers that want it.
        switch ($type) {
            case 'message':
                $out['flowReplies'] = [[
                    'flowReplyType' => 'Text',
                    'data'          => (string) ($data['text'] ?? ''),
                ]];
                break;

            case 'sequence':
                // Multi-reply node. Each `data.replies[i]` becomes one
                // flowReplies entry. Node's executeMessageNode walks the
                // array in order, so the customer receives them in the
                // sequence the builder shows.
                $reps = (array) ($data['replies'] ?? []);
                $out['flowReplies'] = [];
                foreach ($reps as $r) {
                    $kind = strtolower((string) ($r['type'] ?? 'text'));
                    $typeMap = [
                        'text'     => 'Text',
                        'image'    => 'Image',
                        'video'    => 'Video',
                        'audio'    => 'Audio',
                        'document' => 'Document',
                    ];
                    $pascalReply = $typeMap[$kind] ?? 'Text';
                    $entry = ['flowReplyType' => $pascalReply];
                    if ($pascalReply === 'Text') {
                        $entry['data'] = (string) ($r['text'] ?? '');
                    } else {
                        // For media replies the Node bridge needs the
                        // URL + optional filename/caption. `data` holds
                        // the URL (normalizeMediaUrls in FlowsController
                        // will absolutise relative paths) and we surface
                        // filename + caption alongside it.
                        $entry['data']     = $this->absoluteUrl((string) ($r['url'] ?? $r['mediaUrl'] ?? $r['fileUrl'] ?? ''));
                        $entry['filename'] = (string) ($r['filename'] ?? '');
                        $entry['caption']  = (string) ($r['caption'] ?? '');
                    }
                    $out['flowReplies'][] = $entry;
                }
                break;

            case 'media':
                // Standalone media node — single image/video/audio/document.
                // The builder saves `kind`, `url`, `filename`, `caption`.
                // Mirror them at the top level so Node's executeMediaNode
                // reads them without rummaging through `data`.
                $out['mediaKind']     = strtolower((string) ($data['kind'] ?? $data['mediaKind'] ?? 'image'));
                // Tolerant URL read — builder versions have saved the file under
                // url / mediaUrl / fileUrl / media / file. Then ABSOLUTISE it:
                // a relative "/storage/.." can't be fetched by Baileys/WABA, so
                // the node silently sent nothing. This is the media-node fix.
                $rawMediaUrl = (string) ($data['url'] ?? $data['mediaUrl'] ?? $data['fileUrl'] ?? $data['media'] ?? $data['file'] ?? '');
                $out['mediaUrl']      = $this->absoluteUrl($rawMediaUrl);
                $out['mediaCaption']  = (string) ($data['caption'] ?? '');
                $out['mediaFilename'] = (string) ($data['filename'] ?? '');
                $out['mediaMimetype'] = (string) ($data['mimetype'] ?? '');
                break;

            case 'wa_form':
                // Sends a published WaForm to the customer as an
                // interactive Meta Flow message. The form must be
                // status=published with a meta_flow_id — Node hits
                // the same `messages` API the existing template/
                // catalog nodes use.
                $out['formId']      = (string) ($data['formId'] ?? '');
                $out['bodyText']    = (string) ($data['bodyText'] ?? 'Please tap below to fill out our form.');
                $out['ctaLabel']    = (string) ($data['ctaLabel'] ?? 'Open form');
                $out['flowVariable']= self::cleanVar($data['flowVariable'] ?? null, 'form_submission');
                if ($out['formId'] !== '') {
                    $form = \App\Models\WaForm::find($out['formId']);
                    if ($form && $form->isLive()) {
                        $out['metaFlowId']  = (string) $form->meta_flow_id;
                        $out['formTitle']   = (string) $form->title;
                    } else {
                        $out['metaFlowId']  = null;
                        $out['formTitle']   = $form?->title ?? '';
                    }
                }
                break;

            case 'google_meet':
                // Hands a meeting link to the customer mid-flow. Title
                // gets merge-tagged at send time; leadMinutes shifts the
                // meeting start forward (so the customer has time to
                // read the message before joining). messageTemplate is
                // the bot bubble that wraps the URL.
                $out['title']            = (string) ($data['title'] ?? 'WhatsApp consultation');
                $out['durationMinutes']  = (int)    ($data['durationMinutes'] ?? 30);
                $out['leadMinutes']      = (int)    ($data['leadMinutes'] ?? 5);
                $out['sendCalendarInvite'] = (bool) ($data['sendCalendarInvite'] ?? false);
                $out['messageTemplate']  = (string) ($data['messageTemplate']
                    ?? "Your meeting link: {{meet_link}}\nStarts at {{meet_start}}");
                break;

            case 'google_sheets':
                // Append-row OR lookup-row against a workspace Google Sheet.
                // `mode`: write|read. `columns`: array of {header, value}
                // for write mode — `value` strings can contain {{vars}}.
                // For read mode we use `matchColumn` + `matchValue` to
                // find a row and stash all cells under {{saveAs.*}}.
                $out['mode']        = (string) ($data['mode'] ?? 'write');
                $out['sheetId']     = (string) ($data['sheetId'] ?? '');
                $out['tabName']     = (string) ($data['tabName'] ?? '');
                $out['columns']     = is_array($data['columns'] ?? null) ? $data['columns'] : [];
                $out['matchColumn'] = (string) ($data['matchColumn'] ?? '');
                $out['matchValue']  = (string) ($data['matchValue'] ?? '');
                $out['saveAs']      = self::cleanVar($data['saveAs'] ?? null, 'sheet_row');
                // Write mode only: OFF (default) = append a new row positionally
                // (values in column order — the legacy behaviour every existing
                // flow relies on). ON = place each value under the sheet column
                // whose HEADER matches the column's label, so a value can land in
                // its named column regardless of order. Opt-in so nothing changes
                // for flows that don't set it.
                $out['matchByHeader'] = (bool) ($data['matchByHeader'] ?? false);
                break;

            case 'google_docs':
                // Copy a template Doc, replace {{placeholders}}, share
                // anyone-with-link, send the link to the customer. The
                // node author picks a Doc from /api/google/picker/docs.
                $out['templateId']    = (string) ($data['templateId'] ?? '');
                $out['newTitle']      = (string) ($data['newTitle']  ?? 'Document for {{name}}');
                $out['placeholders']  = is_array($data['placeholders'] ?? null) ? $data['placeholders'] : [];
                $out['shareable']     = (bool)   ($data['shareable']  ?? true);
                $out['messageTemplate'] = (string) ($data['messageTemplate']
                    ?? "Here's your document: {{doc_url}}");
                $out['saveAs']        = self::cleanVar($data['saveAs'] ?? null, 'doc_url');
                break;

            case 'google_form':
                // Send a published Google Form's URL, pause the flow,
                // resume when the Apps Script on the form fires our
                // webhook. fieldMap maps form item titles → flow vars.
                $out['formId']     = (string) ($data['formId'] ?? '');
                $out['bodyText']   = (string) ($data['bodyText']
                    ?? 'Please fill out this short form:');
                $out['saveAs']     = self::cleanVar($data['saveAs'] ?? null, 'google_form');
                $out['expiresInSec'] = (int)  ($data['expiresInSec'] ?? 86400);
                break;

            case 'template':
                // The builder only stores `tpl` (the template name) — to send
                // the actual template the Node executor needs the body +
                // structured fields. Hydrate them HERE so Node doesn't have
                // to call back to Laravel per send. variable_map lets Node
                // resolve positional placeholders ({{1}}, {{2}}) when the
                // template uses them; named tags ({{name}}, {{doctor}})
                // are substituted from session.userVariables at send time.
                $out['templateName'] = (string) ($data['tpl'] ?? '');
                $out['preview']      = (string) ($data['preview'] ?? '');
                $name = $out['templateName'];
                $tplIdHint = isset($data['tplId']) ? (int) $data['tplId'] : 0;
                $tpl = null;
                if ($tplIdHint > 0) {
                    // Fast path — builder stamped the row id; skip the scan.
                    $tpl = \App\Models\WaTemplate::query()->find($tplIdHint);
                }
                if (!$tpl && $name !== '') {
                    // `template_name` is an encrypted column, so a SQL WHERE
                    // can't match it (each encrypt uses a fresh IV). Iterate
                    // and decrypt-compare in PHP. Approved rows win when
                    // duplicates exist; otherwise newest by id.
                    $candidates = \App\Models\WaTemplate::query()
                        ->orderByRaw("CASE WHEN status = 'approved' THEN 0 ELSE 1 END")
                        ->orderByDesc('id')
                        ->get();
                    foreach ($candidates as $row) {
                        if ((string) $row->template_name === $name) { $tpl = $row; break; }
                    }
                }
                if ($name !== '') {
                    if ($tpl) {
                        $out['templateId']       = (int) $tpl->id;
                        $out['templateBody']     = (string) ($tpl->template_body ?? '');
                        $out['templateHeader']   = (string) ($tpl->header ?? '');
                        $out['templateFooter']   = (string) ($tpl->footer ?? '');
                        $out['templateLanguage'] = (string) ($tpl->language ?? 'en');
                        $out['templateCategory'] = (string) ($tpl->category ?? '');
                        $out['templateButtons']  = is_array($tpl->buttons) ? $tpl->buttons : [];
                        $vm = $tpl->variable_map;
                        if (is_string($vm)) { $vm = json_decode($vm, true) ?: []; }
                        $out['templateVariableMap'] = is_array($vm) ? $vm : [];
                    } else {
                        $out['templateId']   = null;
                        $out['templateBody'] = '';
                    }
                }
                break;

            case 'list':
                $opts = (array) ($data['options'] ?? []);
                // The builder has a SINGLE "Prompt" field → it's the message
                // body. Leave the header empty: copying the prompt into BOTH
                // header + body made WhatsApp render the same line twice (the
                // reported duplicate-header bug). Node omits an empty header.
                $out['headerText'] = '';
                $out['bodyText']   = (string) ($data['prompt'] ?? 'Please choose:');
                // The "tap to open" button label that hangs under the
                // body bubble. WhatsApp's `interactive.action.button`
                // is REQUIRED for list messages; default to a sensible
                // value so legacy flows still send a valid payload.
                $out['buttonText'] = (string) ($data['button'] ?? 'View options');
                // Items stay a FLAT list — the index IS the output port (p0,
                // p1, …), so grouping must never reorder them. Each item
                // carries its own `section` heading; the runtime groups by it
                // at send time. Older flows saved options as bare strings →
                // no description, no section (one unnamed group), unchanged
                // behaviour. Meta caps: title 24, description 72, section 24
                // — enforced again in Node, trimmed here so the builder's
                // preview and the real send agree.
                $out['listItems']  = array_values(array_map(
                    fn ($opt, $i) => [
                        'id'          => 'p' . $i,
                        'title'       => mb_substr(is_string($opt) ? $opt : (string) ($opt['title'] ?? ('Option ' . ($i + 1))), 0, 24),
                        'description' => is_array($opt) ? mb_substr((string) ($opt['description'] ?? ''), 0, 72) : '',
                        'section'     => is_array($opt) ? mb_substr((string) ($opt['section'] ?? ''), 0, 24) : '',
                    ],
                    $opts,
                    array_keys($opts),
                ));
                $out['variable'] = self::cleanVar($data['var'] ?? null, 'choice');
                break;

            case 'buttons':
                $opts = (array) ($data['options'] ?? []);
                // Same as list — single "Prompt" is the body; empty header so
                // WhatsApp doesn't show the prompt twice (header + body).
                $out['headerText'] = '';
                $out['bodyText']   = (string) ($data['prompt'] ?? 'Please choose:');
                $out['buttons']    = array_values(array_map(
                    fn ($opt, $i) => [
                        'id'    => 'p' . $i,
                        'title' => is_string($opt) ? $opt : ($opt['title'] ?? ('Option ' . ($i + 1))),
                    ],
                    $opts,
                    array_keys($opts),
                ));
                $out['variable'] = self::cleanVar($data['var'] ?? null, 'choice');
                break;

            case 'ask':
                $out['question'] = (string) ($data['prompt'] ?? '');
                // cleanVar: a builder value of "{{name}}" must save under "name",
                // not the literal "{{name}}" key — else {{name}} downstream reads
                // the seeded profile name and a Sheets column repeats it forever.
                $out['variable'] = self::cleanVar($data['var'] ?? null, 'answer');
                // Expected answers (stored in `options[]` so the field
                // shape matches buttons/list) — when set, the Node
                // runtime should try to match the customer's reply
                // against this list and route through the matching
                // `p<i>` port (or `else` when nothing matches).
                $expected = (array) ($data['options'] ?? $data['expected'] ?? []);
                $out['expectedAnswers'] = array_values(array_filter(
                    array_map(fn ($v) => is_string($v) ? trim($v) : '', $expected),
                    fn ($s) => $s !== ''
                ));
                break;

            case 'condition':
                // Multi-rule compound condition with AND/OR logic.
                // `conditions[]` is the list of rules, `operators[]`
                // has n-1 'AND'/'OR' strings between them. Legacy flows
                // that saved single `var/op/value` get auto-promoted to
                // a one-rule chain so the runtime sees a uniform shape.
                $rawConds = $data['conditions'] ?? null;
                if (is_array($rawConds)) {
                    $out['conditions'] = array_values(array_map(fn ($c) => [
                        'variable' => self::cleanVar($c['variable'] ?? null, ''),
                        'operator' => (string) ($c['operator'] ?? 'equals'),
                        'value'    => (string) ($c['value']    ?? ''),
                    ], $rawConds));
                } else {
                    $out['conditions'] = [[
                        'variable' => self::cleanVar($data['var'] ?? null, ''),
                        'operator' => (string) ($data['op']    ?? 'equals'),
                        'value'    => (string) ($data['value'] ?? ''),
                    ]];
                }
                $out['logicOperators'] = array_values(array_map(
                    fn ($o) => strtoupper((string) $o) === 'OR' ? 'OR' : 'AND',
                    (array) ($data['operators'] ?? [])
                ));
                // Backwards-compat: first rule as flat fields so legacy
                // Node executors that only read `variable/operator/compareValue`
                // still behave correctly when there's a single rule.
                $first = $out['conditions'][0] ?? ['variable' => '', 'operator' => 'equals', 'value' => ''];
                $out['variable']     = $first['variable'];
                $out['operator']     = $first['operator'];
                $out['compareValue'] = $first['value'];
                break;

            case 'delay':
                $unit = (string) ($data['unit'] ?? 'min');
                $amt  = (int) ($data['amount'] ?? 5);
                $mult = match ($unit) {
                    'sec', 'second', 'seconds' => 1,
                    'min', 'minute', 'minutes' => 60,
                    'hour', 'hours'            => 3600,
                    'day', 'days'              => 86400,
                    default                    => 60,
                };
                $out['delaySeconds'] = $amt * $mult;
                break;

            case 'webhook':
                // Outbound HTTP call. Node's executor reads these to
                // build the request. `body` is sent raw (with merge
                // tags substituted) for POST/PUT; ignored for GET.
                $out['method']      = strtoupper((string) ($data['method'] ?? 'POST'));
                $out['url']         = (string) ($data['url'] ?? '');
                $out['body']        = (string) ($data['body'] ?? '');
                $out['variable']    = self::cleanVar($data['save'] ?? null, 'response');
                $out['contentType'] = (string) ($data['contentType'] ?? 'application/json');
                // Custom request headers — array of {key,value}. Values may
                // carry {{vars}} (substituted Node-side). Empty keys dropped.
                $out['headers']     = array_values(array_filter(
                    array_map(fn ($h) => is_array($h) ? [
                        'key'   => (string) ($h['key'] ?? ''),
                        'value' => (string) ($h['value'] ?? ''),
                    ] : null, (array) ($data['headers'] ?? [])),
                    fn ($h) => $h && $h['key'] !== ''
                ));
                break;

            case 'mysql':
                // Read-only SQL query against an external MySQL/MariaDB. The
                // Node executor forwards these to Laravel's /api/flow-node/
                // mysql-query (PHP PDO), which runs a SELECT only, caps rows,
                // and returns JSON saved under {{save}}. `sql` may contain
                // {{vars}} (substituted Node-side before the call).
                $out['host']     = (string) ($data['host']     ?? '');
                $out['port']     = (int)    ($data['port']     ?? 3306);
                $out['database'] = (string) ($data['database'] ?? '');
                $out['username'] = (string) ($data['username'] ?? '');
                $out['password'] = (string) ($data['password'] ?? '');
                $out['sql']      = (string) ($data['sql']      ?? '');
                $out['variable'] = self::cleanVar($data['save'] ?? null, 'rows');
                break;

            case 'subflow':
                // Run another flow by id. The Node runtime should
                // pause the current session, run the referenced flow,
                // then resume. `flowId` is the picked flow's primary
                // key from the workspace flow list.
                $out['flowId'] = (string) ($data['flow'] ?? '');
                break;

            case 'assign':
                // Hand the conversation off to a team's queue (or to a
                // specific agent inside it). Node-side handler uses
                // `teamId` to find the team and apply its assignment
                // strategy when `userId` is empty; when `userId` is
                // set it pins the conversation to that operator. The
                // internal note (`message`) lands on the conversation
                // as a system event so the receiving agent sees the
                // context.
                $out['teamId']  = (string) ($data['team']    ?? '');
                $out['userId']  = (string) ($data['userId']  ?? '');
                $out['noteForAgent'] = (string) ($data['message'] ?? '');
                break;

            case 'tag':
                // Action = add | remove. Either:
                //   - `tagId` (preferred) points at an existing
                //     workspace tag row, OR
                //   - `tag` is a name we should create on the fly when
                //     it doesn't exist yet.
                $out['action'] = strtolower((string) ($data['action'] ?? 'add')) === 'remove' ? 'remove' : 'add';
                $out['tagId']  = (string) ($data['tagId'] ?? '');
                $out['tag']    = (string) ($data['tag']   ?? '');
                break;

            case 'whatsapp_shop':
            case 'woocommerce':
            case 'shopify':
                // Commerce nodes — pick a store + N products at design
                // time. The Node executor uses `provider` to pick the
                // right /api/commerce/checkout-link path for Baileys,
                // and to decide WABA-native MPM vs SPM. Products are
                // ALWAYS sent hydrated (with image/price/etc snapshot)
                // so Node doesn't need to call back to Laravel just
                // to render the cards.
                $out['provider']     = $type; // 'whatsapp_shop' | 'woocommerce' | 'shopify'
                $out['storeId']      = (string) ($data['storeId'] ?? '');
                $out['headerText']   = (string) ($data['headerText'] ?? '');
                $out['bodyText']     = (string) ($data['bodyText'] ?? 'Tap a product to see details:');
                $out['footerText']   = (string) ($data['footerText'] ?? '');
                $out['abandonedWaitMinutes'] = (int) ($data['abandonedWaitMinutes'] ?? 5);

                $picked = is_array($data['productItems'] ?? null) ? $data['productItems'] : [];
                // Old flows might have stored:
                //   - `productIds[]` (array of retailer ids — WA Shop legacy)
                //   - `product` (single retailer id string — WC/Shopify legacy)
                // Migrate both into the new productItems[] shape so the
                // Node executor sees a usable list. Without this,
                // legacy flows would normalize to 0 items and take the
                // abandoned port immediately at runtime.
                if (empty($picked) && is_array($data['productIds'] ?? null)) {
                    $picked = array_map(fn ($rid) => ['retailer_id' => (string) $rid], $data['productIds']);
                }
                if (empty($picked) && !empty($data['product'])) {
                    $picked = [['retailer_id' => (string) $data['product']]];
                }
                $out['productItems'] = array_slice(array_values(array_filter(
                    array_map(fn ($p) => is_array($p) ? [
                        'retailer_id' => (string) ($p['retailer_id'] ?? ''),
                        'name'        => (string) ($p['name']        ?? ''),
                        'image'       => (string) ($p['image']       ?? ''),
                        'price_minor' => (int)    ($p['price_minor'] ?? 0),
                        'currency'    => (string) ($p['currency']    ?? ''),
                        'url'         => (string) ($p['url']         ?? ''),
                    ] : null, $picked),
                    fn ($p) => $p && $p['retailer_id'] !== ''
                )), 0, 30);
                break;

            case 'code':
                // Sandboxed JS transform node. We carry the user's JS + the
                // output variable verbatim; execution happens ONLY in
                // node/services/codeSandbox.js (isolated-vm, env-gated) — no
                // PHP ever evals it.
                $out['code']     = (string) ($data['code'] ?? '');
                $out['variable'] = self::cleanVar($data['save'] ?? null, 'result');
                break;

            // ── Call flow nodes — flat shape for callFlowRuntime.js ──
            case 'cf_say':
                $out['text'] = (string) ($data['text'] ?? '');
                break;
            case 'cf_listen':
                $out['variable']       = self::cleanVar($data['save'] ?? null, 'caller_said');
                $out['silenceTimeout'] = (int) ($data['silenceTimeout'] ?? 6);
                break;
            case 'cf_ai':
                $out['model']        = (string) ($data['model'] ?? 'gpt-4o-mini');
                $out['prompt']       = (string) ($data['prompt'] ?? '');
                $out['variable']     = self::cleanVar($data['save'] ?? null, 'ai_reply');
                $aid = (int) ($data['assistant'] ?? 0);
                $out['assistant_id'] = $aid > 0 ? $aid : null;
                $out['endOnGoodbye'] = (bool) ($data['endOnGoodbye'] ?? true);
                break;
            case 'cf_menu':
                $out['routeBy'] = (string) ($data['mode'] ?? 'intent');
                $out['options'] = array_values(array_map(fn ($o) => [
                    'match' => (string) ($o['match'] ?? ''),
                    'label' => (string) ($o['label'] ?? ''),
                ], (array) ($data['options'] ?? [])));
                break;
            case 'cf_search':
                $out['query']    = (string) ($data['query'] ?? '');
                $out['variable'] = self::cleanVar($data['save'] ?? null, 'search');
                $out['filler']   = (string) ($data['filler'] ?? '');
                break;
            case 'cf_transfer':
                $out['number']  = (string) ($data['number'] ?? '');
                $out['message'] = (string) ($data['message'] ?? '');
                break;
            case 'cf_hangup':
                $out['goodbye'] = (string) ($data['goodbye'] ?? '');
                break;

            case 'ai':
                $out['model']    = (string) ($data['model'] ?? 'gpt-4o-mini');
                $out['prompt']   = (string) ($data['prompt'] ?? '');
                $out['variable'] = self::cleanVar($data['save'] ?? null, 'reply');
                // Optional AI-Training assistant — when set, the runtime
                // pulls that assistant's knowledge base into the reply
                // (resolved server-side in FlowNodeActionsController::aiCall).
                $aid = (int) ($data['assistant'] ?? $data['assistant_id'] ?? 0);
                $out['assistant_id'] = $aid > 0 ? $aid : null;
                // Structured-extraction (JSON mode) + silent (no customer reply)
                // — promoted to top level so the Node executor reads them
                // directly (it checks node.extract / node.fields / node.silent).
                $out['extract']  = (bool) ($data['extract'] ?? false);
                $out['silent']   = (bool) ($data['silent'] ?? false);
                // Conversation mode — the AI node drives the whole chat: it
                // keeps replying to every message (parks on itself) until the
                // exit keyword. Promoted flat so the Node executor reads
                // node.conversational / node.exit_keyword directly.
                $out['conversational'] = (bool) ($data['conversational'] ?? false);
                $out['exit_keyword']   = (string) ($data['exit_keyword'] ?? '');
                // `fields` may arrive as a comma-separated string (builder text
                // input) OR an array of strings/objects — normalise to string[].
                $rawFields = $data['fields'] ?? [];
                if (is_string($rawFields)) {
                    $rawFields = $rawFields === '' ? [] : preg_split('/\s*,\s*/', trim($rawFields));
                }
                $out['fields']   = array_values(array_filter(array_map(
                    fn ($f) => trim((string) (is_array($f) ? ($f['key'] ?? $f['name'] ?? '') : $f)),
                    (array) $rawFields
                )));
                if (isset($data['temperature']) && is_numeric($data['temperature'])) $out['temperature'] = (float) $data['temperature'];
                if (isset($data['maxTokens'])   && is_numeric($data['maxTokens']))   $out['maxTokens']   = (int) $data['maxTokens'];
                break;

            case 'cta':
                // Multi-action CTA — up to 3 buttons combining
                // Visit / Call / Copy in any order. Normalized output
                // exposes BOTH the new `ctaActions[]` array AND a
                // back-compat `ctaType/ctaLabel/ctaUrl/ctaPhone/ctaCode`
                // snapshot of the FIRST action so older Node executors
                // that only read the flat fields still work.
                $rawActions = $data['actions'] ?? null;
                if (!is_array($rawActions)) {
                    // Migrate the legacy single-kind shape into actions[].
                    $legacyKind = strtolower((string) ($data['kind'] ?? 'url'));
                    if (!in_array($legacyKind, ['url', 'phone', 'call_now', 'copy'], true)) $legacyKind = 'url';
                    $legacyValue = match ($legacyKind) {
                        'phone', 'call_now' => (string) ($data['phone'] ?? ''),
                        'copy'              => (string) ($data['code']  ?? ''),
                        default             => (string) ($data['url']   ?? ''),
                    };
                    $rawActions = [[
                        'type'  => $legacyKind,
                        'label' => (string) ($data['label'] ?? ''),
                        'value' => $legacyValue,
                    ]];
                }
                $actions = [];
                foreach (array_slice($rawActions, 0, 3) as $a) {
                    $t = strtolower((string) ($a['type'] ?? 'url'));
                    if (!in_array($t, ['url', 'phone', 'call_now', 'copy'], true)) $t = 'url';
                    $actions[] = [
                        'type'  => $t,
                        'label' => (string) ($a['label'] ?? ''),
                        'value' => (string) ($a['value'] ?? ''),
                    ];
                }
                $out['ctaActions']  = $actions;
                // Header / body / footer travel as separate Baileys
                // fields, mirroring buttons/list — the executor renders
                // them as labelled lines on top of the action list.
                $out['headerText']  = (string) ($data['headerText'] ?? '');
                $out['bodyText']    = (string) ($data['bodyText']   ?? '');
                $out['footerText']  = (string) ($data['footerText'] ?? '');

                // Back-compat flat-fields from the first action so the
                // legacy Node executor that only reads `ctaUrl/ctaType`
                // still sends *something* meaningful.
                $first = $actions[0] ?? ['type' => 'url', 'label' => '', 'value' => ''];
                $out['ctaType']  = $first['type'];
                $out['ctaLabel'] = $first['label'] ?: 'Visit';
                $out['ctaUrl']   = $first['type'] === 'phone' || $first['type'] === 'call_now'
                    ? 'tel:' . preg_replace('/\s+/', '', $first['value'])
                    : $first['value'];
                $out['ctaPhone'] = ($first['type'] === 'phone' || $first['type'] === 'call_now') ? $first['value'] : '';
                $out['ctaCode']  = $first['type'] === 'copy' ? $first['value'] : '';
                break;

            case 'location':
                $out['latitude']  = (float) ($data['lat'] ?? 0);
                $out['longitude'] = (float) ($data['lng'] ?? 0);
                $out['address']   = (string) ($data['address'] ?? '');
                $out['title']     = (string) ($data['title'] ?? '');
                break;

            case 'poll':
                $opts = (array) ($data['options'] ?? []);
                $out['question'] = (string) ($data['question'] ?? 'Pick one');
                $out['options']  = array_values($opts);
                $out['allowMultiple'] = (bool) ($data['multi'] ?? false);
                break;

            case 'chatbot':
                // `bot` now stores an AiAgent ID (was hardcoded slug
                // strings before). Emit both `agentId` for new Node
                // executors and `botId` for legacy ones.
                $out['agentId'] = (string) ($data['bot'] ?? '');
                $out['botId']   = (string) ($data['bot'] ?? '');
                break;

            case 'book_appointment':
                // Node handler reads these directly. Keep keys parallel
                // to the React builder so debugging stays easy.
                $out['slotCount']        = (int) ($data['slotCount'] ?? 5);
                $out['prompt']           = (string) ($data['prompt'] ?? 'Pick a time that works for you:');
                $out['confirmation']     = (string) ($data['confirmation'] ?? '✅ Booked! See you on {{slot}}.');
                $out['calendarOverride'] = (string) ($data['calendarOverride'] ?? '');
                $out['collectEmail']     = (bool) ($data['collectEmail'] ?? false);
                break;

            case 'deal':
                // Create a CRM deal (or move an existing one) when this node
                // fires. Node reads these flat, substitutes {{vars}} in
                // dealName/value, and POSTs to /api/flow-node/deal-action.
                // `stageId` carries the pipeline (the stage knows its pipeline).
                $out['action']   = strtolower((string) ($data['action'] ?? 'create')) === 'move' ? 'move' : 'create';
                $out['dealName'] = (string) ($data['dealName'] ?? '{{contact_name}} — deal');
                $out['stageId']  = (string) ($data['stageId'] ?? '');
                $out['dealValue']= (string) ($data['value']   ?? '');   // may carry {{vars}}
                $out['ownerId']  = (string) ($data['ownerId'] ?? '');
                $out['saveAs']   = self::cleanVar($data['saveAs'] ?? null, 'deal_id');
                break;
        }

        return $out;
    }

    /**
     * Make a stored media path absolute so Node/Baileys/WABA can actually
     * fetch it. A relative "/storage/.." or "storage/.." silently fails to
     * send (Baileys needs a fully-qualified URL). Already-absolute http(s)
     * URLs pass through untouched.
     */
    private function absoluteUrl(string $u): string
    {
        $u = trim($u);
        if ($u === '') return '';
        if (preg_match('#^https?://#i', $u)) return $u;
        return url($u);
    }

    private function normalizeEdge(array $e, array $reactNodes): array
    {
        $sourceId   = (string) ($e['source'] ?? '');
        $targetId   = (string) ($e['target'] ?? '');
        $handle     = (string) ($e['sourceHandle'] ?? 'out');

        // Find the source node's type so we can map the handle name to
        // a port index using PORT_MAP.
        $srcType = '';
        foreach ($reactNodes as $n) {
            if (($n['id'] ?? null) === $sourceId) { $srcType = (string) ($n['type'] ?? ''); break; }
        }

        $port = $this->handleToPort($srcType, $handle);

        return [
            'id'           => $e['id'] ?? null,
            'sourceNodeId' => $sourceId . '_' . $port,
            'targetNodeId' => $targetId . '_1',
            // Carry React-side hint so debugging is possible.
            'sourceHandle' => $handle,
            'source'       => $sourceId,
            'target'       => $targetId,
        ];
    }

    private function handleToPort(string $srcType, string $handle): int
    {
        // Multi-option nodes use p0, p1, p2... → 1, 2, 3...
        if (preg_match('/^p(\d+)$/', $handle, $m)) {
            return ((int) $m[1]) + 1;
        }
        // "else / no match" catch-all on choice nodes (buttons / list / ask).
        // It MUST get a port of its own — real option ports are p0→1, p1→2, …
        // so anything ≥ 1 is taken. Mapping else to the default 1 (as the
        // fall-through below did) made it collide with the FIRST button: the
        // operator's else branch became unreachable, and — worse — the runtime
        // sent unmatched replies down port 1 too, advancing the flow on any
        // random message. Port 0 is otherwise unused; the Node runtime looks
        // for `${nodeId}_0` when no option matched.
        if ($handle === 'else') {
            return 0;
        }
        if (isset(self::PORT_MAP[$srcType][$handle])) {
            return self::PORT_MAP[$srcType][$handle];
        }
        // 'out' or anything unmapped → port 1 (Node's default convention).
        return 1;
    }

    /**
     * Sanitise a variable NAME. Users (and the builder's "save answer to" field)
     * naturally type the variable WITH its template braces — "{{name}}" — but a
     * variable name is a KEY, not a token to resolve. Left as-is, an Ask node
     * with var="{{name}}" stores the customer's answer under the literal key
     * "{{name}}", while every {{name}} reference reads the SEPARATE "name" key
     * (which only holds the seeded profile name) — so the captured value never
     * appears downstream and a Sheets column writes the same seeded value every
     * run ("the variable saved the response"). Strip one layer of surrounding
     * {{ }}, a leading @, and whitespace so "{{name}}", "@name", " name " all
     * normalise to "name". Falls back to $default when nothing usable remains.
     */
    private static function cleanVar(mixed $v, string $default = ''): string
    {
        $s = trim((string) $v);
        if ($s === '') return $default;
        // Remove one surrounding {{ … }} wrapper, then any stray braces / @ / space.
        $s = preg_replace('/^\s*\{\{\s*|\s*\}\}\s*$/', '', $s);
        $s = trim((string) $s, " \t\r\n@{}");
        return $s !== '' ? $s : $default;
    }
}
