<?php

use Illuminate\Support\Facades\Route;

/**
 * Node-bridge endpoints. Authed via X-Node-Token (or X-Workspace-Token,
 * or Meta HMAC depending on the route) — NEVER via Laravel session.
 *
 * This file is registered in bootstrap/app.php via `withRouting(api: …)`
 * which mounts it under the `api` middleware group. That group does NOT
 * include `StartSession` / `ShareErrorsFromSession` / `VerifyCsrfToken`
 * — that's the whole point of moving these routes here.
 *
 * Before this move: Node's `GET /api/whatsapp-settings` could hang 8+
 * seconds while the browser held the web-session lock on /chat or
 * /devices. Now Node's hot path runs lockless.
 *
 * IMPORTANT: Laravel auto-prefixes this file with `/api`, so paths
 * here are written WITHOUT a leading `/api/`. A route registered as
 * `'/whatsapp-settings'` here serves the URL `/api/whatsapp-settings`.
 */

// ───────── Instaflow (Instagram) → WaDesk push ─────────
// The separate Instaflow deployment pushes new IG events here; the controller
// self-guards on the X-Instaflow-Secret shared secret. Serves /api/instaflow/inbound.
Route::post('/instaflow/inbound',
    [\App\Http\Controllers\Api\InstaflowInboundController::class, 'ingest'])
    ->name('instaflow.inbound');

// ───────── Scheduled / Broadcast / Campaign status callbacks ─────────
Route::post('/update-schedule-status',
    [\App\Http\Controllers\ScheduledController::class, 'updateStatus'])
    ->name('scheduled.update-status');

Route::post('/update-scheduled-contact-status',
    [\App\Http\Controllers\ScheduledController::class, 'updateContactStatus'])
    ->name('scheduled.update-contact-status');

Route::post('/update-message-status',
    [\App\Http\Controllers\BroadcastsController::class, 'nodeMessageStatus'])
    ->name('broadcasts.node.message-status');

// Delivery/read receipt for NON-broadcast sends (Quick Send / /chat /
// team-inbox) — updates the messages row by its stored WhatsApp message id so
// ticks progress past "sent" (Quick Send Delivered count was stuck at 0).
Route::post('/chat/message-status',
    [\App\Http\Controllers\ChatController::class, 'nodeChatMessageStatus'])
    ->name('chat.node.message-status');

Route::post('/update-broadcast-status',
    [\App\Http\Controllers\BroadcastsController::class, 'nodeBroadcastStatus'])
    ->name('broadcasts.node.broadcast-status');

Route::post('/campaigns/update-status',
    [\App\Http\Controllers\WaCampaignsController::class, 'nodeCampaignStatus'])
    ->name('wa-campaigns.node.status');
Route::post('/campaigns/update-contact-status',
    [\App\Http\Controllers\WaCampaignsController::class, 'nodeContactStatus'])
    ->name('wa-campaigns.node.contact-status');
Route::post('/campaigns/update-status-by-id',
    [\App\Http\Controllers\WaCampaignsController::class, 'nodeStatusByMessageId'])
    ->name('wa-campaigns.node.status-by-id');
Route::post('/campaigns/track-response',
    [\App\Http\Controllers\WaCampaignsController::class, 'nodeTrackResponse'])
    ->name('wa-campaigns.node.track-response');
Route::post('/campaigns/unsubscribe',
    [\App\Http\Controllers\WaCampaignsController::class, 'nodeUnsubscribe'])
    ->name('wa-campaigns.node.unsubscribe');

// ───────── Baileys client status callbacks ─────────
Route::post('/update-status',
    [\App\Http\Controllers\WaConnectController::class, 'nodeStatusCallback'])
    ->name('baileys.node.callback');
Route::post('/node-heartbeat',
    [\App\Http\Controllers\WaConnectController::class, 'nodeHeartbeat'])
    ->name('baileys.node.heartbeat');

// Per-number proxy / IP isolation: Node fetches a device's proxy config at
// connect, and reports back the verified egress IP / health. X-Node-Token gated.
Route::get('/devices/proxy-config/{phone}',
    [\App\Http\Controllers\WaConnectController::class, 'proxyConfig'])
    ->name('baileys.node.proxy-config');
Route::post('/devices/proxy-result',
    [\App\Http\Controllers\WaConnectController::class, 'proxyResult'])
    ->name('baileys.node.proxy-result');



// ───────── Per-send settings (HOT PATH) ─────────
Route::get('/whatsapp-settings',
    [\App\Http\Controllers\WaConnectController::class, 'nodeSettings']);
Route::get('/whatsapp-message-settings',
    [\App\Http\Controllers\WaConnectController::class, 'nodeMessageSettings']);

// ───────── Appointment booking (Google Calendar) ─────────
Route::get('/appointments/slots',
    [\App\Http\Controllers\AppointmentController::class, 'slotsApi'])
    ->name('api.appointments.slots');
Route::post('/appointments/book',
    [\App\Http\Controllers\AppointmentController::class, 'bookApi'])
    ->name('api.appointments.book');

// ───────── Commerce ─────────
Route::post('/commerce/checkout-link',
    [\App\Http\Controllers\FlowsCommerceController::class, 'checkoutLink'])
    ->name('api.commerce.checkout-link');
Route::post('/commerce/waba-send-products',
    [\App\Http\Controllers\FlowsCommerceController::class, 'wabaSendProducts'])
    ->name('api.commerce.waba-send');
Route::post('/commerce/check-inventory',
    [\App\Http\Controllers\FlowsCommerceController::class, 'checkInventory'])
    ->name('api.commerce.inventory');

// ───────── Flow-node side-effects ─────────
Route::post('/flow-node/tag',
    [\App\Http\Controllers\FlowNodeActionsController::class, 'tag'])
    ->name('api.flow-node.tag');
Route::post('/flow-node/assign',
    [\App\Http\Controllers\FlowNodeActionsController::class, 'assign'])
    ->name('api.flow-node.assign');
Route::post('/flow-node/ai-call',
    [\App\Http\Controllers\FlowNodeActionsController::class, 'aiCall'])
    ->name('api.flow-node.ai-call');
// Flow "Chatbot" node — assigns a Team-Inbox AI Agent to the conversation and
// fires the first reply (respondIfAssigned). The agent then owns the chat.
Route::post('/flow-node/ai-agent-reply',
    [\App\Http\Controllers\FlowNodeActionsController::class, 'aiAgentReply'])
    ->name('api.flow-node.ai-agent-reply');
// Call Flow "Search web" node — provider-agnostic (Tavily/SerpAPI/Brave).
Route::post('/flow-node/web-search',
    [\App\Http\Controllers\FlowNodeActionsController::class, 'webSearch'])
    ->name('api.flow-node.web-search');
// Call bridge resolves the workspace's active call flow on inbound calls.
Route::post('/call-flow/active',
    [\App\Http\Controllers\FlowNodeActionsController::class, 'activeCallFlow'])
    ->name('api.call-flow.active');
Route::post('/flow-node/google-meet',
    [\App\Http\Controllers\FlowNodeActionsController::class, 'googleMeet'])
    ->name('api.flow-node.google-meet');
Route::post('/flow-node/wa-form-send',
    [\App\Http\Controllers\FlowNodeActionsController::class, 'waFormSend'])
    ->name('api.flow-node.wa-form-send');
Route::post('/flow-node/mysql-query',
    [\App\Http\Controllers\FlowNodeActionsController::class, 'mysqlQuery'])
    ->name('api.flow-node.mysql-query');
Route::post('/flow-node/deal-action',
    [\App\Http\Controllers\FlowNodeActionsController::class, 'dealAction'])
    ->name('api.flow-node.deal-action');

// ───────── WhatsApp group directory sync (Node → Laravel, X-Node-Token) ─────────
// The Node bridge mirrors sock.groupFetchAllParticipating() here so the ordering
// flow can find a customer's group + post the confirmed order with an @mention.
Route::post('/groups/sync',
    [\App\Http\Controllers\WaGroupController::class, 'sync'])
    ->name('api.groups.sync');

// ───────── Natural-language ordering (P3) — parse / confirm / cancel ─────────
Route::post('/flow-node/order-parse',
    [\App\Http\Controllers\FlowNodeActionsController::class, 'orderParse'])
    ->name('api.flow-node.order-parse');
Route::post('/flow-node/order-shipping',
    [\App\Http\Controllers\FlowNodeActionsController::class, 'orderShipping'])
    ->name('api.flow-node.order-shipping');
Route::post('/flow-node/order-confirm',
    [\App\Http\Controllers\FlowNodeActionsController::class, 'orderConfirm'])
    ->name('api.flow-node.order-confirm');
Route::post('/flow-node/order-cancel',
    [\App\Http\Controllers\FlowNodeActionsController::class, 'orderCancel'])
    ->name('api.flow-node.order-cancel');
// One-call reply handler — translates the reply (any language) + confirms /
// cancels / re-asks, all localized. Replaces the Node "contains confirm" branch.
Route::post('/flow-node/order-reply',
    [\App\Http\Controllers\FlowNodeActionsController::class, 'orderReply'])
    ->name('api.flow-node.order-reply');

// ───────── Google flow-node bridge (Sheets / Docs / Forms) ─────────
$gfn = \App\Http\Controllers\GoogleFlowNodeController::class;
Route::post('/flow-node/google/sheet-write',  [$gfn, 'sheetWrite'])->name('api.flow-node.gsheet-write');
Route::post('/flow-node/google/sheet-read',   [$gfn, 'sheetRead'])->name('api.flow-node.gsheet-read');
Route::post('/flow-node/google/doc-generate', [$gfn, 'docGenerate'])->name('api.flow-node.gdoc-generate');
Route::post('/flow-node/google/form-send',    [$gfn, 'formSend'])->name('api.flow-node.gform-send');
Route::post('/google/form-response',          [$gfn, 'formResponse'])->name('api.google.form-response');

// ───────── WABA creds + AI call bridge ─────────
Route::get('/waba-creds',
    [\App\Http\Controllers\FlowNodeActionsController::class, 'wabaCreds'])
    ->name('api.waba-creds');
Route::get('/waba-call/assistant/{id}',
    [\App\Http\Controllers\FlowNodeActionsController::class, 'wabaCallAssistant'])
    ->whereNumber('id')->name('api.waba-call.assistant');
Route::get('/waba-call/voice-keys',
    [\App\Http\Controllers\FlowNodeActionsController::class, 'wabaCallVoiceKeys'])
    ->name('api.waba-call.voice-keys');
Route::post('/waba-call/transcript-turn',
    [\App\Http\Controllers\FlowNodeActionsController::class, 'wabaCallTranscriptTurn'])
    ->name('api.waba-call.transcript-turn');
Route::post('/waba-call/bridge-error',
    [\App\Http\Controllers\FlowNodeActionsController::class, 'wabaCallBridgeError'])
    ->name('api.waba-call.bridge-error');
Route::post('/waba-call/bridge-accepted',
    [\App\Http\Controllers\FlowNodeActionsController::class, 'wabaCallBridgeAccepted'])
    ->name('api.waba-call.bridge-accepted');

// ───────── Flow runtime data ─────────
Route::get('/flows/{id}',
    [\App\Http\Controllers\FlowsController::class, 'nodeShow'])
    ->whereNumber('id')->name('api.flows.show');

// ───────── Inbound message webhook (Baileys → Laravel) ─────────
Route::post('/inbound-message',
    [\App\Http\Controllers\WaInboundController::class, 'baileys'])
    ->name('baileys.inbound');

// ───────── Bot startup syncs + lookups ─────────
Route::get('/scheduled/active',
    [\App\Http\Controllers\ScheduledController::class, 'activeForBot'])
    ->name('scheduled.active-for-bot');

// Node calls this on boot to fetch the active-campaigns list. Body is
// currently a static empty stub (real per-workspace fetch lives at
// /api/campaigns/active), but the endpoint is still public-facing
// surface area — gate it with the same X-Node-Token as every other
// Node-side call so a casual probe can't enumerate it.
Route::get('/campaigns/sync', function (\Illuminate\Http\Request $request) {
    $expected = node_token();
    $got      = (string) $request->header('X-Node-Token', '');
    if ($expected === '' || !hash_equals($expected, $got)) {
        return response()->json(['ok' => false, 'error' => 'unauthorized'], 401);
    }
    return response()->json([
        'success'   => true,
        'campaigns' => [],
    ]);
});

Route::get('/keyword-replies',
    [\App\Http\Controllers\AutoReplyController::class, 'lookup'])
    ->name('keyword-replies.lookup');

// Twilio MessageStatus webhook. Twilio POSTs delivered/read/failed
// events here whenever an outbound send (broadcast, chat, inbox-reply,
// flow, template) changes state. Authenticated via X-Twilio-Signature
// HMAC inside the controller. Without this every Twilio send stayed
// frozen at status='sent' forever.
Route::post('/twilio/status',
    [\App\Http\Controllers\TwilioStatusController::class, 'handle'])
    ->name('twilio.status');

// Node campaignService.js calls this on every campaign send to fetch the
// approved template row (template_body / buttons / attachment / variable_map
// / twilio_content_sid). Without this endpoint `fetchTemplateData` returned
// null and the campaign Twilio branch couldn't see the ContentSid. Gated
// by the same X-Node-Token as every other Node-bridge route.
Route::get('/templates-camp/{id}', function (\Illuminate\Http\Request $request, int $id) {
    $expected = node_token();
    $got      = (string) $request->header('X-Node-Token', '');
    if ($expected === '' || !hash_equals($expected, $got)) {
        return response()->json(['ok' => false, 'error' => 'unauthorized'], 401);
    }
    $tpl = \App\Models\WaTemplate::find($id);
    if (!$tpl) {
        return response()->json(['ok' => false, 'error' => 'not_found'], 404);
    }
    // Decode JSON columns so Node receives arrays, not strings.
    $buttons  = is_string($tpl->buttons) ? json_decode($tpl->buttons, true) : ($tpl->buttons ?? []);
    $carousel = is_string($tpl->carousel_data) ? json_decode($tpl->carousel_data, true) : ($tpl->carousel_data ?? null);
    $varmap   = is_string($tpl->variable_map) ? json_decode($tpl->variable_map, true) : ($tpl->variable_map ?? []);

    // Public-URL builder mirrors BroadcastsController::buildTemplateData and
    // NodeSchedulerClient::resolveTemplateData — files live under
    // public/storage/wa-templates, NOT the legacy /uploads/templates/.
    $attachmentUrl = null;
    if (!empty($tpl->attachment_file)) {
        $attachmentUrl = url('storage/' . ltrim((string) $tpl->attachment_file, '/'));
    }

    // Base64-inline the attachment so Node never has to download it from a URL
    // (the old per-recipient download silently dropped media when Node couldn't
    // reach APP_DOMAIN_NAME/storage). Read ONCE here; reused for all recipients.
    $inlineMedia = \App\Models\WaTemplate::inlineAttachment($tpl->attachment_file);

    return response()->json([
        'success'  => true,
        'template' => [
            'id'                 => $tpl->id,
            'template_name'      => $tpl->template_name,
            'template_type'      => $tpl->template_type,
            'category'           => $tpl->category,
            'meta_category'      => $tpl->meta_category,
            'language'           => $tpl->language,
            'header'             => $tpl->header,
            'title_text'         => $tpl->header,
            'template_body'      => $tpl->template_body,
            'footer'             => $tpl->footer,
            'buttons'            => is_array($buttons) ? $buttons : [],
            'attachment_type'    => $tpl->attachment_type,
            'attachment_file'    => $tpl->attachment_file,
            'attachment_url'     => $attachmentUrl,
            'attachment_base64'  => $inlineMedia['attachment_base64'],
            'attachment_mime'    => $inlineMedia['attachment_mime'],
            'carousel_data'      => $carousel,
            'variable_map'       => is_array($varmap) ? $varmap : [],
            'meta_template_id'   => $tpl->meta_template_id,
            'meta_status'        => $tpl->meta_status,
            'twilio_content_sid' => $tpl->twilio_content_sid,
        ],
    ]);
})->name('templates-camp.show');

// Node flowController fetches a workspace's saved attributes on flow
// start so WORKSPACE-scoped placeholders ({{promo_key}}, {{order_id}}
// defaults, …) render with real values inside flow nodes — the same
// `attributes` table AttributeResolver reads. Contact attrs (seeded by
// seedFlowUserVariables) take precedence over these defaults on key
// collision (merged Node-side). Gated by the same X-Node-Token as every
// other Node-bridge route; Node caches the result per workspace TTL.
Route::get('/workspace-attributes/{workspaceId}', function (\Illuminate\Http\Request $request, int $workspaceId) {
    $expected = node_token();
    $got      = (string) $request->header('X-Node-Token', '');
    if ($expected === '' || !hash_equals($expected, $got)) {
        return response()->json(['ok' => false, 'error' => 'unauthorized'], 401);
    }

    $attributes = \App\Models\Attribute::query()
        ->forWorkspace($workspaceId)
        ->get(['attribute_key', 'attribute_value'])
        ->mapWithKeys(fn ($a) => [(string) $a->attribute_key => (string) $a->attribute_value])
        ->all();

    return response()->json([
        'ok'         => true,
        'attributes' => (object) $attributes,
    ]);
})->whereNumber('workspaceId')->name('api.workspace-attributes');

// ═════════ WaDesk browser-extension API ═════════
// Consumed by extension/content.js. Public: app-config + login. The rest
// authenticate via Bearer token (ExtensionApiAuth → extension_api_tokens).
// Paths match what content.js calls verbatim so no client change is needed.
$ext = \App\Http\Controllers\Api\ExtensionApiController::class;

Route::get('/app-config', [$ext, 'appConfig'])->name('ext.app-config');
// Per-IP throttle so this unauthenticated password endpoint can't be used for
// credential stuffing / password spraying at full speed. 10/min is generous for
// a human retyping a password but caps automated brute force. Relies on the
// tightened trustProxies (bootstrap/app.php) so the throttle key is the real IP.
Route::post('/login',     [$ext, 'login'])->middleware('throttle:10,1')->name('ext.login');

Route::middleware(\App\Http\Middleware\ExtensionApiAuth::class)->group(function () use ($ext) {
    Route::post('/logout',            [$ext, 'logout'])->name('ext.logout');
    Route::get('/get-devices',        [$ext, 'devices'])->name('ext.devices');
    Route::get('/attributes',         [$ext, 'attributes'])->name('ext.attributes');
    Route::get('/get-templates',      [$ext, 'templates'])->name('ext.templates');
    Route::get('/message-history',    [$ext, 'messageHistory'])->name('ext.message-history');
    Route::get('/credits',            [$ext, 'credits'])->name('ext.credits');
    Route::post('/send-quick-message', [$ext, 'sendQuickMessage'])->name('ext.send');
    Route::get('/get-contact-csv',    [$ext, 'contactCsv'])->name('ext.contact-csv');
});
