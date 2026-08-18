<?php

/**
 * URL-path → required plan-feature map for the in-app paywall.
 *
 * The KEYS are request()->is() globs (no leading slash); the VALUES are
 * package feature columns — the SAME flags shown at /admin/packages/create
 * and toggled by the tier ladder. When a logged-in operator opens a path
 * and their plan's effective package has that flag OFF, the page still
 * renders but <x-plan-paywall> slides an "upgrade" sheet over it.
 *
 * Only features that have a DEDICATED PAGE live here. Capability features
 * with no page of their own (kanban view, message reactions, internal
 * notes, edit messages, carousel templates, BYOK AI keys, role-based
 * permissions, business hours, team performance, drip campaigns, AI
 * suggestions, data residency, commerce-aware flows, "generate with AI",
 * call recording, AI voice/chat) are gated IN CONTEXT by
 * PlanLimitGuard::feature()/check() where the action happens — not here.
 *
 * Platform admins and unlocked plans never see the sheet.
 */
return [
    // ── Messaging / outreach ──────────────────────────────────────────
    'meta-ads'          => 'access_ctwa',
    'meta-ads/*'        => 'access_ctwa',
    'wa-campaigns'      => 'campaign',
    'wa-campaigns/*'    => 'campaign',
    'broadcasts'        => 'broadcast',
    'broadcasts/*'      => 'broadcast',
    'scheduled'         => 'schedulemessage',
    'scheduled/*'       => 'schedulemessage',
    'templates'         => 'template',
    'templates/*'       => 'template',
    'auto-reply'        => 'access_keyword_replies',
    'auto-reply/*'      => 'access_keyword_replies',

    // ── Automation ────────────────────────────────────────────────────
    'flows'             => 'autoflow',
    'flows/*'           => 'autoflow',

    // ── AI ────────────────────────────────────────────────────────────
    'ai-assistants'     => 'access_ai_agents',
    'ai-assistants/*'   => 'access_ai_agents',
    'ai-training'       => 'access_ai_training',
    'ai-training/*'     => 'access_ai_training',

    // ── Voice calling ─────────────────────────────────────────────────
    'wa-calling'        => 'access_waba_calling',
    'wa-calling/*'      => 'access_waba_calling',
    'call-logs'         => 'access_waba_calling',
    'call-logs/*'       => 'access_waba_calling',

    // ── Commerce ──────────────────────────────────────────────────────
    'store'             => 'access_wa_storefront',
    'store/*'           => 'access_wa_storefront',
    'catalog'           => 'access_wa_storefront',
    'catalog/*'         => 'access_wa_storefront',

    // ── Sales CRM ─────────────────────────────────────────────────────
    'deals'             => 'access_sales_pipeline',
    'deals/*'           => 'access_sales_pipeline',

    // ── Engagement / ops ──────────────────────────────────────────────
    'chatbot-widgets'   => 'access_chatbot_widgets',
    'chatbot-widgets/*' => 'access_chatbot_widgets',
    'appointments'      => 'access_appointment_booking',
    'appointments/*'    => 'access_appointment_booking',
    'webhooks'          => 'access_outbound_webhooks',
    'webhooks/*'        => 'access_outbound_webhooks',
    'analytics'         => 'access_analytics',
    'analytics/*'       => 'access_analytics',

    // ── Integrations ──────────────────────────────────────────────────
    'shopify'           => 'integration_shopify',
    'shopify/*'         => 'integration_shopify',
    'woocommerce'       => 'integration_woocommerce',
    'woocommerce/*'     => 'integration_woocommerce',
    'hubspot'           => 'integration_hubspot',
    'hubspot/*'         => 'integration_hubspot',
    'google-account'    => 'integration_google_calendar',
    'google-account/*'  => 'integration_google_calendar',
];
