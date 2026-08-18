<?php

namespace App\Models;

use App\Models\Concerns\LogsNotifications;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Package extends Model
{
    use HasFactory, LogsNotifications;

    protected $table = 'packages';

    public const TYPE_PLAN  = 'plan';
    public const TYPE_ADDON = 'addon';

    /** Full subscription plans (the default; NULL type = legacy plan rows). */
    public function scopePlans($q)
    {
        return $q->where(fn ($w) => $w->where('type', self::TYPE_PLAN)->orWhereNull('type'));
    }

    /** À-la-carte add-on packs bought on top of a plan. */
    public function scopeAddons($q)
    {
        return $q->where('type', self::TYPE_ADDON);
    }

    public function isAddon(): bool
    {
        return $this->type === self::TYPE_ADDON;
    }

    /**
     * Is this a STACKABLE (quantity) add-on — one that adds a numeric limit
     * (e.g. "+1 device")? Those may be bought repeatedly and stack. A
     * feature-only add-on (unlocks a capability, no limits) is a one-time buy:
     * once owned there is nothing more to gain from buying it again.
     */
    public function isStackable(): bool
    {
        $g = is_array($this->grants_json) ? $this->grants_json : null;
        if ($g !== null) {
            return !empty($g['limits']);
        }
        foreach (\App\Http\Controllers\AdminPagesController::planLimitColumns() as $l) {
            if ((int) ($this->{$l} ?? 0) > 0) return true;   // legacy fallback
        }
        return false;
    }

    /**
     * Does this add-on GRANT the given boolean feature? Reads grants_json (the
     * v2 model where an add-on lists ONLY what it grants). Legacy add-ons with
     * no grants_json fall back to the raw column being truthy.
     */
    public function grantsFeature(string $key): bool
    {
        $g = is_array($this->grants_json) ? $this->grants_json : null;
        if ($g !== null && array_key_exists('features', $g)) {
            return in_array($key, (array) $g['features'], true);
        }
        return isset($this->{$key}) && (bool) $this->{$key};   // legacy fallback
    }

    /**
     * The numeric limit DELTA this add-on adds for the given key, or null when
     * the add-on does not touch it. grants_json is authoritative; legacy rows
     * fall back to the raw column but ONLY when > 0 — a 0 was "blank", so it
     * must NOT be read as a grant (that was the unlimited-leak bug).
     */
    public function grantLimit(string $key): ?int
    {
        $g = is_array($this->grants_json) ? $this->grants_json : null;
        if ($g !== null && isset($g['limits']) && is_array($g['limits'])) {
            return array_key_exists($key, $g['limits']) ? (int) $g['limits'][$key] : null;
        }
        $v = $this->{$key} ?? null;                            // legacy fallback
        return (is_numeric($v) && (int) $v > 0) ? (int) $v : null;
    }

    protected $fillable = [
        'plan_id', 'type', 'grants_json', 'pname', 'pfeatures_id', 'plan_unit', 'plan_duration',
        'plan_amount', 'offer_price', 'currency',
        'free', 'lifetime', 'data_retention_days', 'status', 'is_default', 'is_highlighted', 'is_custom_quote',
        'sort_order', 'detail', 'cta_label', 'cta_url', 'subtitle',
        // Numeric limits (countable).
        'device_limit', 'monthly_messages_limit', 'contacts_limit',
        'broadcast_limit', 'template_limit', 'groups_limit',
        'campaign_messages_limit', 'automation_messages_limit',
        'broadcast_size_limit', 'total_campaigns_limit', 'active_campaign_limit',
        'user_seat_limit', 'tags_limit', 'flow_limit', 'flow_steps_limit',
        'autoreply_limit', 'chatbot_limit', 'scheduled_campaign_limit',
        'daily_media_size_allowance',
        'workspaces_per_owner_limit', 'routing_rules_limit',
        'drip_campaigns_limit', 'appointments_limit',
        'ai_agents_limit', 'saved_replies_limit', 'webhooks_limit',
        'ai_token_limit_monthly',
        'api_rate_limit_per_minute',
        // Feature toggles (booleans).
        'autoreply', 'bulkmessage', 'schedulemessage', 'ads', 'campaign',
        'autoflow', 'broadcast', 'chatgpt_suggestion', 'template',
        'access_carousel_templates', 'role_based_permissions',
        'access_drip_campaigns', 'access_ctwa', 'access_analytics', 'remove_branding',
        'integration_shopify', 'integration_woocommerce', 'integration_hubspot',
        'integration_google_calendar', 'integration_google_sheets',
        'integration_slack', 'integration_trello',
        'access_kanban_view', 'access_appointment_booking', 'access_edit_messages',
        'access_internal_notes', 'access_message_reactions', 'access_routing_rules',
        'access_business_hours', 'access_team_performance', 'access_outbound_webhooks',
        'access_keyword_replies', 'access_ai_agents',
        'allow_byok_ai_keys',
        'multipledevice', 'file_type_restrictions',
        // Sprint 9.5 plan gates — WABA calling + AI + storefront + SLA.
        'access_waba_calling', 'access_call_recording',
        'access_ai_voice_agent', 'access_ai_chat_assistant',
        'access_ai_training', 'access_ai_generate',
        'access_wa_storefront', 'access_flows_commerce',
        'access_chatbot_widgets', 'access_sla_policies',
        'access_translation', 'access_data_residency',
        // Per-number proxy / IP isolation (Unofficial-API).
        'access_proxy_isolation',
        // Sprint 11 — Sales Pipeline / Deal Management CRM.
        'access_sales_pipeline', 'pipelines_limit',
        'waba_calling_minutes_monthly', 'ai_voice_minutes_monthly',
        'ai_chat_messages_monthly', 'ai_training_sources_limit',
        'chatbot_widgets_limit', 'storefronts_limit',
        'sla_policies_limit', 'translation_chars_monthly',
    ];

    /**
     * Core columns PLUS the plan flags and limits installed extensions add.
     *
     * Without this an extension's columns pass validation and then get dropped
     * silently by mass-assignment protection — the admin ticks a box, the form
     * saves "successfully", and the value never lands. Merging at runtime keeps
     * core free of any specific extension's column names.
     */
    public function getFillable(): array
    {
        return array_values(array_unique(array_merge(
            parent::getFillable(),
            array_keys(\App\Services\ExtensionRegistry::planFeatures()),
            array_keys(\App\Services\ExtensionRegistry::planLimits())
        )));
    }


    protected $casts = [
        'grants_json' => 'array',
        'plan_amount' => 'decimal:2',
        'offer_price' => 'decimal:2',
        'plan_duration' => 'integer',
        'sort_order' => 'integer',
        'free' => 'boolean',
        'lifetime' => 'boolean',
        'data_retention_days' => 'integer',
        'status' => 'boolean',
        'is_default' => 'boolean',
        'is_highlighted' => 'boolean',
        'is_custom_quote' => 'boolean',
        'device_limit' => 'integer',
        'monthly_messages_limit' => 'integer',
        'contacts_limit' => 'integer',
        'broadcast_limit' => 'integer',
        'template_limit' => 'integer',
        'groups_limit' => 'integer',
        'campaign_messages_limit' => 'integer',
        'automation_messages_limit' => 'integer',
        'broadcast_size_limit' => 'integer',
        'total_campaigns_limit' => 'integer',
        'active_campaign_limit' => 'integer',
        'user_seat_limit' => 'integer',
        'tags_limit' => 'integer',
        'flow_limit' => 'integer',
        'flow_steps_limit' => 'integer',
        'autoreply_limit' => 'integer',
        'chatbot_limit' => 'integer',
        'scheduled_campaign_limit' => 'integer',
        'daily_media_size_allowance' => 'integer',
        'workspaces_per_owner_limit' => 'integer',
        'routing_rules_limit' => 'integer',
        'drip_campaigns_limit' => 'integer',
        'appointments_limit' => 'integer',
        'ai_agents_limit' => 'integer',
        'saved_replies_limit' => 'integer',
        'webhooks_limit' => 'integer',
        'autoreply' => 'boolean',
        'bulkmessage' => 'boolean',
        'schedulemessage' => 'boolean',
        'ads' => 'boolean',
        'campaign' => 'boolean',
        'autoflow' => 'boolean',
        'broadcast' => 'boolean',
        'chatgpt_suggestion' => 'boolean',
        'template' => 'boolean',
        'access_carousel_templates' => 'boolean',
        'role_based_permissions' => 'boolean',
        'access_drip_campaigns' => 'boolean',
        'access_ctwa' => 'boolean',
        'access_analytics' => 'boolean',
        'remove_branding' => 'boolean',
        'integration_shopify' => 'boolean',
        'integration_woocommerce' => 'boolean',
        'integration_hubspot' => 'boolean',
        'integration_google_calendar' => 'boolean',
        'integration_google_sheets' => 'boolean',
        'integration_slack' => 'boolean',
        'integration_trello' => 'boolean',
        'access_kanban_view' => 'boolean',
        'access_appointment_booking' => 'boolean',
        'access_edit_messages' => 'boolean',
        'access_internal_notes' => 'boolean',
        'access_message_reactions' => 'boolean',
        'access_routing_rules' => 'boolean',
        'access_business_hours' => 'boolean',
        'access_team_performance' => 'boolean',
        'access_outbound_webhooks' => 'boolean',
        'access_keyword_replies' => 'boolean',
        'access_ai_agents' => 'boolean',
        'allow_byok_ai_keys' => 'boolean',
        'multipledevice' => 'boolean',
        'access_proxy_isolation' => 'boolean',
    ];

    public function scopeActive($q) { return $q->where('status', 1); }

    public function getFeaturesArray(): array
    {
        return !empty($this->pfeatures_id) ? array_filter(array_map('trim', explode(',', $this->pfeatures_id))) : [];
    }

    public function isFreePlan(): bool
    {
        return (bool) $this->free || ((float) $this->plan_amount === 0.0 && !$this->is_custom_quote);
    }

    public function getMonthlyPriceAttribute(): float
    {
        return (float) $this->plan_amount;
    }

    public function getYearlyPriceAttribute(): float
    {
        // offer_price stores the discounted (yearly) per-month price; if blank,
        // fall back to a 20% discount on the monthly price.
        if ($this->offer_price !== null && (float) $this->offer_price > 0) {
            return (float) $this->offer_price;
        }
        return round((float) $this->plan_amount * 0.8, 2);
    }

    /**
     * The ACTUAL amount to charge / display for this plan, in the package's
     * own currency. When an offer (discounted) price is set it wins over the
     * regular plan_amount — this is the single source of truth the checkout,
     * order creation, recurring renewals, and admin lists must all use so the
     * discounted price is never bypassed. Free / zero-price plans charge 0.
     */
    public function chargeableAmount(): float
    {
        if ($this->free || (float) $this->plan_amount <= 0) {
            return 0.0;
        }
        if ($this->offer_price !== null && (float) $this->offer_price > 0) {
            return (float) $this->offer_price;
        }
        return (float) $this->plan_amount;
    }

    public function getCurrencySymbolAttribute(): string
    {
        return match (strtoupper((string) $this->currency)) {
            'INR' => '&#8377;',
            'USD' => '$',
            'EUR' => '&euro;',
            'GBP' => '&pound;',
            default => htmlspecialchars((string) $this->currency, ENT_QUOTES) . ' ',
        };
    }

    public function formatPrice(float $value): string
    {
        return $this->currency_symbol . number_format($value, 0);
    }

    /**
     * Is this plan PRICED yearly, i.e. plan_amount already covers a full year?
     *
     * The distinction matters because two different things both call themselves
     * "yearly": a package the admin authored with plan_unit = years, and a
     * monthly package a buyer chose to pay annually via the pricing toggle.
     * The first must be charged as-is; the second is monthly x 12 minus the
     * global discount. Conflating them billed ~9.6x.
     *
     * Kept on the model so checkout, the pricing page and the gateway drivers
     * all answer the question the same way.
     */
    public function isNativelyYearly(): bool
    {
        return str_starts_with(strtolower((string) ($this->plan_unit ?? '')), 'year');
    }

    /** Human period label from plan_unit/plan_duration, e.g. "/month", "/year". */
    public function periodLabel(): string
    {
        $unit = strtolower((string) $this->plan_unit);
        $dur  = (int) ($this->plan_duration ?: 1);
        $u = match (true) {
            str_contains($unit, 'year')  => 'year',
            str_contains($unit, 'month') => 'month',
            str_contains($unit, 'week')  => 'week',
            str_contains($unit, 'day')   => 'day',
            default                      => $unit ?: 'month',
        };
        return $dur > 1 ? "/{$dur} {$u}s" : "/{$u}";
    }

    /**
     * Map this package onto the card shape <x-frontend.pricing-strip> renders,
     * so the public pricing page reflects the real, admin-managed plans
     * (no hardcoded Starter/Pro/Scale).
     */
    public function toPricingCard(int $index = 0): array
    {
        $catalog = self::featureCatalog();

        // Price + period — mirror the /account/plans logic EXACTLY so the public
        // page and the dashboard never disagree: chargeableAmount() (honours the
        // offer price), converted into the platform display currency, then run
        // through FormatSettings::currency() for an identical symbol + grouping
        // (e.g. "Rp176,000" — not the raw package symbol + number).
        $displayCcy = strtoupper((string) \App\Models\SystemSetting::get('default_currency', 'USD'));
        $yearlyPct  = (int) \App\Models\SystemSetting::get('pricing.yearly_discount_pct', 20);
        if ($this->is_custom_quote) {
            $price = __('Custom');
            $priceYearly = $price;
            $period = __('contact sales');
        } elseif ($this->free || (float) $this->plan_amount <= 0) {
            $price = __('Free');
            $priceYearly = $price;
            $period = $this->lifetime ? __('forever') : __('/month');
        } else {
            $amount = (float) $this->chargeableAmount();
            if ($this->currency && strtoupper((string) $this->currency) !== $displayCcy) {
                $amount = \App\Support\FormatSettings::convert($amount, $this->currency, $displayCcy);
            }
            $price = \App\Support\FormatSettings::currency($amount);
            // Yearly = the discounted monthly-equivalent, EXACTLY like /account/plans
            // ($amount × (1 − pct/100)). Lifetime plans never discount.
            $priceYearly = $this->lifetime ? $price : \App\Support\FormatSettings::currency($amount * (1 - $yearlyPct / 100));
            $period = $this->lifetime ? __('one-time') : $this->periodLabel();
        }

        // CTA link — mirror /account/plans: paid plans → the real checkout (an
        // anonymous visitor bounces through login first), free → register,
        // custom-quote → the admin's sales link. Same destination as the dashboard
        // so the public page and the client dashboard never point different ways.
        if ($this->is_custom_quote) {
            $ctaHref = $this->cta_url ?: url('/contact');
        } elseif ($this->free || (float) $this->plan_amount <= 0) {
            $ctaHref = \Illuminate\Support\Facades\Route::has('register') ? route('register') : url('/');
        } else {
            $ctaHref = \Illuminate\Support\Facades\Route::has('user.checkout.show')
                ? route('user.checkout.show', $this->id)
                : (\Illuminate\Support\Facades\Route::has('register') ? route('register') : url('/'));
        }

        // Volume — the headline numeric allowances (0 = unlimited).
        $volumeKeys = ['device_limit', 'monthly_messages_limit', 'user_seat_limit', 'contacts_limit', 'broadcast_limit'];
        $volume = [];
        foreach ($volumeKeys as $k) {
            $v = $this->$k;
            if ($v === null || ! isset($catalog['limits'][$k])) continue;
            $label = ((int) $v === 0 ? __('Unlimited') : number_format((int) $v)) . ' ' . lcfirst($catalog['limits'][$k]);
            $volume[] = ['label' => $label, 'included' => true];
            if (count($volume) >= 4) break;
        }

        // Features — capabilities (enabled first, then a few greyed-out for contrast).
        $on = [];
        $off = [];
        foreach ($catalog['capabilities'] as $field => $label) {
            if ((bool) $this->$field) {
                if (count($on) < 7) $on[] = ['label' => $label, 'included' => true];
            } elseif (count($off) < 3) {
                $off[] = ['label' => $label, 'included' => false];
            }
        }
        $features = array_merge($on, $off);

        // Support — light derived list.
        $support = [['label' => __('Docs & community'), 'included' => true]];
        if ($this->is_highlighted || $this->access_sla_policies) {
            $support[] = ['label' => __('Priority support'), 'included' => true];
        }

        return [
            'plan_num'  => __('Plan') . ' · ' . sprintf('%02d', $index + 1),
            'name'      => $this->pname,
            'badge'     => $this->free ? __('free') : ($this->is_highlighted ? __('popular') : ''),
            'tagline'   => $this->subtitle ?: trim(strip_tags((string) $this->detail)),
            'price'     => $price,
            'price_yearly' => $priceYearly,
            'period'    => $period,
            'overage'   => '',
            'cta_label' => ($this->cta_label ?: ($this->is_custom_quote ? __('Talk to sales') : ($this->free ? __('Start free') : __('Get started')))) . ' →',
            'cta_href'  => $ctaHref,
            'highlighted' => (bool) $this->is_highlighted,
            'volume'    => $volume,
            'features'  => $features,
            'support'   => $support,
        ];
    }

    /** Active plans mapped to public pricing cards, in display order. */
    public static function publicPricingCards(): array
    {
        return static::query()
            ->where('status', true)
            ->orderBy('sort_order')->orderBy('id')
            ->get()
            ->values()
            ->map(fn ($p, $i) => $p->toPricingCard($i))
            ->all();
    }

    public function features()
    {
        $ids = $this->getFeaturesArray();
        if (empty($ids)) return collect();
        return PackageFeature::whereIn('id', $ids)->get();
    }

    /**
     * The full, human-labelled catalog of everything a plan can grant — every
     * numeric limit and every capability flag the Package supports. Single
     * source of truth for the /account/plans feature bullets AND the compare
     * table, so both show EVERYTHING (no hand-picked subset). Columns that
     * don't exist or aren't set on a given plan are simply skipped at render.
     *
     * @return array{limits: array<string,string>, capabilities: array<string,string>}
     */
    public static function featureCatalog(): array
    {
        return [
            // Numeric allowances — rendered with their value (0 = unlimited).
            'limits' => [
                'device_limit'                 => 'WhatsApp numbers',
                'user_seat_limit'              => 'Team inbox seats',
                'contacts_limit'               => 'Contacts',
                'monthly_messages_limit'       => 'Messages / month',
                'broadcast_limit'              => 'Broadcasts',
                'broadcast_size_limit'         => 'Recipients per broadcast',
                'total_campaigns_limit'        => 'Campaigns',
                'active_campaign_limit'        => 'Active campaigns',
                'campaign_messages_limit'      => 'Campaign messages',
                'scheduled_campaign_limit'     => 'Scheduled campaigns',
                'automation_messages_limit'    => 'Automation messages',
                'template_limit'               => 'Templates',
                'flow_limit'                   => 'Flows',
                'flow_steps_limit'             => 'Steps per flow',
                'autoreply_limit'              => 'Auto-reply rules',
                'saved_replies_limit'          => 'Saved replies',
                'chatbot_limit'                => 'Chatbots',
                'chatbot_widgets_limit'        => 'Chat widgets',
                'drip_campaigns_limit'         => 'Drip campaigns',
                'routing_rules_limit'          => 'Routing rules',
                'appointments_limit'           => 'Appointments',
                'tags_limit'                   => 'Tags',
                'groups_limit'                 => 'Groups',
                'webhooks_limit'               => 'Webhooks',
                'workspaces_per_owner_limit'   => 'Workspaces',
                'storefronts_limit'            => 'Storefronts',
                'sla_policies_limit'           => 'SLA policies',
                'ai_agents_limit'              => 'AI agents',
                'ai_token_limit_monthly'       => 'AI tokens / month',
                'ai_chat_messages_monthly'     => 'AI chat messages / month',
                'ai_voice_minutes_monthly'     => 'AI voice minutes / month',
                'waba_calling_minutes_monthly' => 'WhatsApp call minutes / month',
                'ai_training_sources_limit'    => 'AI training sources',
                'translation_chars_monthly'    => 'Translation characters / month',
                'daily_media_size_allowance'   => 'Daily media allowance (MB)',
            ],
            // On/off capabilities — rendered only when enabled.
            'capabilities' => [
                'bulkmessage'                  => 'Bulk messaging',
                'schedulemessage'              => 'Scheduled messages',
                'access_carousel_templates'    => 'Carousel templates',
                'autoflow'                     => 'Flow automation',
                'access_keyword_replies'       => 'Keyword replies',
                'access_drip_campaigns'        => 'Drip campaigns',
                'access_routing_rules'         => 'Auto-assign routing rules',
                'access_business_hours'        => 'Business hours',
                'access_analytics'             => 'Analytics dashboard',
                'access_team_performance'      => 'Team performance analytics',
                'access_kanban_view'           => 'Kanban board',
                'access_appointment_booking'   => 'Appointment booking',
                'access_edit_messages'         => 'Edit sent messages',
                'access_internal_notes'        => 'Internal notes',
                'access_message_reactions'     => 'Message reactions',
                'chatgpt_suggestion'           => 'AI reply suggestions',
                'access_ai_agents'             => 'AI assistants (ChatGPT / Gemini / Claude)',
                'access_ai_chat_assistant'     => 'AI chat assistant',
                'access_ai_voice_agent'        => 'AI voice agent',
                'access_ai_training'           => 'AI training (knowledge base)',
                'access_ai_generate'           => 'AI content generation',
                'allow_byok_ai_keys'           => 'Bring your own AI keys',
                'access_waba_calling'          => 'WhatsApp calling',
                'access_call_recording'        => 'Call recording',
                'access_ctwa'                  => 'Click-to-WhatsApp ads',
                'access_wa_storefront'         => 'WhatsApp storefront',
                'access_flows_commerce'        => 'Commerce flows',
                'access_chatbot_widgets'       => 'Website chat widgets',
                'access_sales_pipeline'        => 'Sales pipeline (Deal CRM)',
                'access_sla_policies'          => 'SLA policies',
                'access_proxy_isolation'       => 'Per-number proxy / dedicated IP',
                'access_translation'           => 'Auto-translation',
                'access_outbound_webhooks'     => 'Outbound webhooks',
                'integration_shopify'          => 'Shopify',
                'integration_woocommerce'      => 'WooCommerce',
                'integration_hubspot'          => 'HubSpot',
                'integration_google_calendar'  => 'Google Calendar',
                'integration_google_sheets'    => 'Google Sheets',
                'integration_slack'            => 'Slack',
                'integration_trello'           => 'Trello',
                'role_based_permissions'       => 'Role-based permissions',
                'multipledevice'               => 'Multiple devices',
                'access_data_residency'        => 'Data residency',
                'remove_branding'              => 'Remove branding',
            ],
        ];
    }
}
