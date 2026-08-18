<?php

namespace Database\Seeders;

use App\Models\Package;
use App\Models\PackageFeature;
use Illuminate\Database\Seeder;

class PackageSeeder extends Seeder
{
    public function run(): void
    {
        // ── Display feature catalog (shown on the pricing page rows) ──────
        $featureNames = [
            'WhatsApp number',
            'Contacts',
            'Messages / month',
            'Flows + auto-replies',
            'Team inbox',
            'Shopify / WooCommerce',
            'AI assist',
            'WhatsApp Catalog',
            'SSO + SCIM',
            'Priority support',
        ];
        foreach ($featureNames as $name) {
            PackageFeature::updateOrCreate(['name' => $name], ['status' => true]);
        }

        // ── Capability flags ─────────────────────────────────────────────
        // EVERY plan declares the SAME full set (each flag explicit true/false)
        // so plan comparison / upgrade-downgrade is consistent and a missing
        // key can never accidentally grant or deny a feature. Defaults: all
        // OFF; each tier turns on a superset of the tier below it.
        $ALL_FLAGS = [
            // core messaging
            'autoreply', 'broadcast', 'schedulemessage', 'template', 'bulkmessage',
            'campaign', 'autoflow', 'ads',
            // inbox / team
            'access_kanban_view', 'access_internal_notes', 'access_message_reactions',
            'access_edit_messages', 'access_business_hours', 'access_routing_rules',
            'access_team_performance', 'access_sla_policies',
            // automation / engagement
            'access_keyword_replies', 'access_drip_campaigns', 'access_appointment_booking',
            'access_chatbot_widgets', 'access_outbound_webhooks',
            // AI suite
            'chatgpt_suggestion', 'access_ai_agents', 'access_ai_chat_assistant',
            'access_ai_training', 'access_ai_generate', 'access_ai_voice_agent',
            // commerce / catalog / ads
            'access_wa_storefront', 'access_flows_commerce', 'access_carousel_templates',
            'access_ctwa',
            // integrations
            'integration_shopify', 'integration_woocommerce', 'integration_hubspot',
            'integration_google_calendar', 'integration_google_sheets',
            // calling
            'access_waba_calling', 'access_call_recording',
            // analytics / enterprise
            'access_analytics', 'access_translation', 'role_based_permissions',
            'remove_branding', 'access_data_residency',
        ];
        $base = array_fill_keys($ALL_FLAGS, false);

        // Turn the given keys ON over a FRESH all-OFF base each call (copy
        // locally so calls never accumulate into each other).
        $on = static function (array $keys) use ($base): array {
            $f = $base;
            foreach ($keys as $k) { $f[$k] = true; }
            return $f;
        };

        $starterOn = ['autoreply', 'broadcast', 'schedulemessage', 'template', 'access_keyword_replies'];
        $growthOn  = array_merge($starterOn, [
            'bulkmessage', 'campaign', 'autoflow', 'ads', 'access_drip_campaigns',
            'integration_shopify', 'integration_woocommerce', 'integration_hubspot',
            'integration_google_calendar', 'integration_google_sheets',
            'access_kanban_view', 'access_internal_notes', 'access_message_reactions',
            'access_edit_messages', 'access_business_hours', 'access_appointment_booking',
            'access_outbound_webhooks', 'access_chatbot_widgets',
        ]);
        $proOn = array_merge($growthOn, [
            'chatgpt_suggestion', 'access_ai_agents', 'access_ai_chat_assistant',
            'access_ai_training', 'access_ai_generate', 'access_ai_voice_agent',
            'access_carousel_templates', 'access_wa_storefront', 'access_flows_commerce',
            'access_analytics', 'access_routing_rules', 'access_team_performance',
            'access_ctwa', 'access_translation', 'access_waba_calling',
            'access_call_recording', 'access_sla_policies',
        ]);
        // Enterprise = the highest plan → EVERY feature ON.
        $enterpriseFlags = array_fill_keys($ALL_FLAGS, true);

        $packages = [
            [
                'plan_id'        => 'starter',
                'pname'          => 'Starter',
                'subtitle'       => 'For solo founders trying it out.',
                'plan_amount'    => 0,
                'offer_price'    => 0,
                'free'           => true,
                'is_highlighted' => false,
                'is_custom_quote'=> false,
                'sort_order'     => 1,
                'cta_label'      => 'Continue free',
                'cta_url'        => '/checkout?plan=starter',
                'device_limit'   => 1,
                'contacts_limit' => 500,
                'monthly_messages_limit' => 1000,
                'flow_limit'     => 1,
                'autoreply_limit'=> 1,
                'detail'         => "1 WhatsApp number\n500 contacts\n1,000 messages / month\n1 flow + 1 auto-reply\nNo team inbox\nEmail-only support",
            ] + $on($starterOn),
            [
                'plan_id'        => 'growth',
                'pname'          => 'Growth',
                'subtitle'       => 'For founders & small teams.',
                'plan_amount'    => 19,
                'offer_price'    => 15,
                'plan_unit'      => 'month',
                'plan_duration'  => 1,
                'is_highlighted' => false,
                'sort_order'     => 2,
                'cta_label'      => 'Choose Growth',
                'cta_url'        => '/checkout?plan=growth',
                'device_limit'   => 2,
                'contacts_limit' => 5000,
                'monthly_messages_limit' => 20000,
                'user_seat_limit'=> 3,
                'detail'         => "2 WhatsApp numbers\n5,000 contacts\n20,000 messages / month\nUnlimited flows + auto-replies\nTeam inbox · 3 agents\nShopify / WooCommerce",
            ] + $on($growthOn),
            [
                'plan_id'        => 'pro',
                'pname'          => 'Pro',
                'subtitle'       => 'For growing brands & agencies.',
                'plan_amount'    => 49,
                'offer_price'    => 39,
                'plan_unit'      => 'month',
                'plan_duration'  => 1,
                'is_highlighted' => true,
                'sort_order'     => 3,
                'cta_label'      => 'Choose Pro',
                'cta_url'        => '/checkout?plan=pro',
                'device_limit'   => 5,
                'contacts_limit' => 25000,
                'monthly_messages_limit' => 100000,
                'user_seat_limit'=> 10,
                'detail'         => "5 WhatsApp numbers\n25,000 contacts\n100,000 messages / month\nTeam inbox · 10 agents\nAI assist · ChatGPT / Gemini / Claude\nWhatsApp Catalog & Store · Calling\nPriority chat support",
            ] + $on($proOn),
            [
                'plan_id'        => 'enterprise',
                'pname'          => 'Enterprise',
                'subtitle'       => 'For high-volume teams & regulated industries.',
                'plan_amount'    => 0,
                'offer_price'    => 0,
                'is_custom_quote'=> true,
                'sort_order'     => 4,
                'cta_label'      => 'Talk to sales',
                'cta_url'        => '/support?topic=enterprise',
                'device_limit'   => 0,            // 0 = unlimited
                'contacts_limit' => 0,
                'monthly_messages_limit' => 0,
                'user_seat_limit'=> 0,
                'detail'         => "Unlimited numbers, contacts, messages\nUnlimited team-inbox seats\nEvery feature unlocked\nSSO, audit logs, SCIM\nData residency · India / EU / US\nDedicated CSM + 24/7 phone · SLA 99.95%",
            ] + $enterpriseFlags,
        ];

        foreach ($packages as $p) {
            $p['status']   = true;
            $p['currency'] = 'USD';
            Package::updateOrCreate(
                ['plan_id' => $p['plan_id']],
                $p
            );
        }
    }
}
