@php
    $isEdit = isset($package) && $package;
    $title = $isEdit ? 'Edit package' : 'Create package';
    $action = $isEdit ? route('admin.packages.update', $package->id) : route('admin.packages.store');

    $limitLabels = [
        'device_limit' => 'Devices',
        'monthly_messages_limit' => 'Messages / month',
        'contacts_limit' => 'Contacts',
        'broadcast_limit' => 'Broadcasts',
        'template_limit' => 'Templates',
        'groups_limit' => 'Groups',
        'campaign_messages_limit' => 'Campaign messages',
        'automation_messages_limit' => 'Automation messages',
        'broadcast_size_limit' => 'Broadcast size',
        'total_campaigns_limit' => 'Total campaigns',
        'active_campaign_limit' => 'Active campaigns',
        'user_seat_limit' => 'Team seats',
        'tags_limit' => 'Tags',
        'flow_limit' => 'Flows',
        'flow_steps_limit' => 'Steps per flow',
        'autoreply_limit' => 'Keyword auto-replies',
        'chatbot_limit' => 'Chatbots',
        'scheduled_campaign_limit' => 'Scheduled campaigns',
        'daily_media_size_allowance' => 'Daily media MB',
        'workspaces_per_owner_limit' => 'Workspaces per owner',
        'routing_rules_limit' => 'Routing rules',
        'drip_campaigns_limit' => 'Drip campaigns',
        'appointments_limit' => 'Appointments / month',
        'ai_agents_limit' => 'AI agents',
        'saved_replies_limit' => 'Quick replies',
        'webhooks_limit' => 'Outbound webhooks',
        'ai_token_limit_monthly' => 'AI tokens / month',
        // Sprint 9.5 caps.
        'waba_calling_minutes_monthly' => 'WABA calling minutes / mo',
        'ai_voice_minutes_monthly' => 'AI voice minutes / mo',
        'ai_chat_messages_monthly' => 'AI chat messages / mo',
        'ai_training_sources_limit' => 'AI training sources',
        'chatbot_widgets_limit' => 'Chatbot website widgets',
        'storefronts_limit' => 'WA storefronts',
        'sla_policies_limit' => 'SLA policies',
        'translation_chars_monthly' => 'Translation chars / mo',
        'api_rate_limit_per_minute' => 'API rate limit / min (0 = default)',
    ];
    $featureLabels = [
        'autoreply' => 'Auto-reply system',
        'bulkmessage' => 'Bulk messaging',
        'schedulemessage' => 'Scheduled messages',
        'ads' => 'Meta Ads integration',
        'campaign' => 'Campaigns',
        'autoflow' => 'Flow builder',
        'broadcast' => 'Broadcasts',
        'chatgpt_suggestion' => 'AI reply suggestions',
        'template' => 'Templates',
        'access_wa_forms' => 'WhatsApp Forms',
        'access_wa_links' => 'WhatsApp Link Generator',
        'access_carousel_templates' => 'Carousel templates',
        'role_based_permissions' => 'Role-based permissions',
        'access_drip_campaigns' => 'Drip campaigns',
        'access_ctwa' => 'Click-to-WhatsApp ads',
        'access_analytics' => 'Analytics dashboard',
        'access_instagram' => 'Instagram automation',
        'access_instagram_reposter' => 'Instagram Reels Autopilot',
        'remove_branding' => 'Remove ' . brand_name() . ' branding',
        'integration_shopify' => 'Shopify integration',
        'integration_woocommerce' => 'WooCommerce integration',
        'integration_hubspot' => 'HubSpot integration',
        'integration_google_calendar' => 'Google Calendar',
        'integration_google_sheets' => 'Google Sheets',
        'integration_slack' => 'Slack',
        'integration_trello' => 'Trello',
        'access_kanban_view' => 'Kanban view',
        'access_appointment_booking' => 'Appointment booking',
        'access_edit_messages' => 'Edit sent messages',
        'access_internal_notes' => 'Internal notes',
        'access_message_reactions' => 'Message reactions',
        'access_routing_rules' => 'Routing rules',
        'access_business_hours' => 'Business hours',
        'access_team_performance' => 'Team performance',
        'access_outbound_webhooks' => 'Outbound webhooks',
        'access_keyword_replies' => 'Keyword auto-replies',
        'access_ai_agents' => 'AI agents',
        'allow_byok_ai_keys' => 'Bring your own AI keys',
        'multipledevice' => 'Multi-device sending',
        'file_type_restrictions' => 'File-type upload restrictions',
        // Sprint 9.5 toggles.
        'access_waba_calling' => 'WhatsApp Cloud-API voice calling',
        'access_call_recording' => 'Call recording',
        'access_ai_voice_agent' => 'AI voice agent (answer calls)',
        'access_ai_chat_assistant' => 'AI chat assistant (text)',
        'access_ai_training' => 'AI training sources',
        'access_ai_generate' => 'Inline "Generate with AI" buttons',
        'access_wa_storefront' => 'WhatsApp Storefront / catalog',
        'access_whatsapp_pay' => 'WhatsApp Pay (in-chat payments, India)',
        'access_flows_commerce' => 'Commerce-aware flows',
        'access_chatbot_widgets' => 'Chatbot website widgets',
        'access_sla_policies' => 'SLA policies',
        'access_translation' => 'Multilingual auto-translation',
        'access_data_residency' => 'Data residency (EU/local) drivers',
        'access_proxy_isolation' => 'Per-number proxy / dedicated IP',
        'access_sales_pipeline' => 'Sales pipeline (Deal CRM)',
    ];

    // Logical limit groups (mapped to the columns the form ships with).
    $limitGroups = [
        'Messaging caps' => [
            'monthly_messages_limit',
            'broadcast_limit',
            'broadcast_size_limit',
            'campaign_messages_limit',
            'automation_messages_limit',
            'scheduled_campaign_limit',
            'total_campaigns_limit',
            'active_campaign_limit',
            'daily_media_size_allowance',
        ],
        'Workspace caps' => [
            'device_limit',
            'user_seat_limit',
            'contacts_limit',
            'groups_limit',
            'workspaces_per_owner_limit',
            'tags_limit',
        ],
        'Content caps' => [
            'template_limit',
            'flow_limit',
            'flow_steps_limit',
            'autoreply_limit',
            'chatbot_limit',
            'saved_replies_limit',
        ],
        'AI & voice caps' => [
            'waba_calling_minutes_monthly',
            'ai_voice_minutes_monthly',
            'ai_chat_messages_monthly',
            'ai_agents_limit',
            'ai_training_sources_limit',
            'ai_token_limit_monthly',
        ],
        'Commerce & SLA' => [
            'storefronts_limit',
            'chatbot_widgets_limit',
            'sla_policies_limit',
            'translation_chars_monthly',
            'drip_campaigns_limit',
            'appointments_limit',
        ],
        'Other caps' => ['webhooks_limit', 'routing_rules_limit', 'api_rate_limit_per_minute'],
    ];
    // Logical feature groups.
    $featureGroups = [
        'Messaging' => [
            'autoreply',
            'bulkmessage',
            'schedulemessage',
            'campaign',
            'autoflow',
            'broadcast',
            'template',
            'access_wa_forms',
            'access_carousel_templates',
            'access_drip_campaigns',
            'access_keyword_replies',
            'access_edit_messages',
            'access_message_reactions',
        ],
        'Inbox & team' => [
            'access_internal_notes',
            'access_routing_rules',
            'access_business_hours',
            'access_team_performance',
            'access_kanban_view',
            'access_appointment_booking',
            'access_sla_policies',
            'role_based_permissions',
        ],
        'AI & calling' => [
            'access_waba_calling',
            'access_call_recording',
            'access_ai_voice_agent',
            'access_ai_chat_assistant',
            'access_ai_training',
            'access_ai_generate',
            'access_ai_agents',
            'allow_byok_ai_keys',
            'chatgpt_suggestion',
        ],
        'Commerce' => [
            'access_wa_storefront',
            'access_whatsapp_pay',
            'access_flows_commerce',
            'access_chatbot_widgets',
            'integration_shopify',
            'integration_woocommerce',
        ],
        'Integrations' => [
            'integration_hubspot',
            'integration_google_calendar',
            'integration_google_sheets',
            'integration_slack',
            'integration_trello',
            'access_outbound_webhooks',
        ],
        'Advanced' => [
            'ads',
            'access_ctwa',
            'access_analytics',
            'multipledevice',
            'file_type_restrictions',
            'access_translation',
            'access_data_residency',
            'access_proxy_isolation',
            'access_sales_pipeline',
            'access_wa_links',
            'remove_branding',
        ],
    ];

    // ---- Extension-contributed plan fields -------------------------------
    // The form renders from $featureGroups / $limitGroups and then filters
    // against the controller's column lists — a key must appear in BOTH or it
    // silently never renders. That double gate is exactly why the Instagram
    // flags were invisible while still being saved as false on every submit.
    //
    // Merging here closes it: an installed extension's flags get a group, a
    // label and a rendered input, and they disappear again when it is removed.
    foreach (\App\Services\ExtensionRegistry::planFeatures() as $key => $meta) {
        $featureLabels[$key] = $meta['label'];
        $featureGroups[$meta['group']][] = $key;
    }
    foreach (\App\Services\ExtensionRegistry::planLimits() as $key => $meta) {
        $limitLabels[$key] = $meta['label'];
        $limitGroups[$meta['group']][] = $key;
    }

    // Instagram add-on gets its OWN wizard step — pull its feature toggles and
    // limit inputs out of the generic Features/Limits groups so they render
    // together on a dedicated "Instagram" step (only when the add-on is
    // installed, i.e. it contributed any Instagram-group fields).
    $igFeatures = array_values(array_unique($featureGroups['Instagram'] ?? []));
    $igLimits   = array_values(array_unique($limitGroups['Instagram'] ?? []));
    unset($featureGroups['Instagram'], $limitGroups['Instagram']);
    $hasIgStep  = !empty($igFeatures) || !empty($igLimits);

    $val = function ($field, $default = null) use ($package) {
        return old($field, $package->{$field} ?? $default);
    };
@endphp

<x-layouts.admin :title="$title" admin-key="packages" page="admin-packages-create">
    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-4 sm:px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <a href="{{ route('admin.packages.index') }}" class="hover:text-ink-900">{{ __('Packages') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ $isEdit ? 'Edit' : 'New' }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2 flex-wrap justify-end">
            <span class="font-mono text-[11px] text-ink-500 mr-2">{{ __('Step') }} <span id="cur-step">1</span> /
                4</span>
            <a href="{{ route('admin.packages.index') }}"
                class="px-3.5 py-1.5 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('Cancel') }}</a>
            <button type="button" id="prevBtn" disabled
                class="px-3.5 py-1.5 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium disabled:opacity-40 disabled:cursor-not-allowed">{{ __('Back') }}</button>
            <button type="button" id="nextBtn"
                class="px-3.5 py-1.5 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold flex items-center gap-2">
                Next
                <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M6 4l4 4-4 4" />
                </svg>
            </button>
            <button type="submit" form="pkgForm" id="submitBtn"
                class="hidden px-3.5 py-1.5 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold flex items-center gap-2">
                <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M2 8l5 5 7-9" />
                </svg>
                {{ $isEdit ? 'Save changes' : 'Create package' }}
            </button>
        </div>
    </header>

    <div class="px-4 sm:px-7 pt-7 pb-2">
        <div>
            <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">Admin · Packages ·
                {{ $isEdit ? 'Edit' : 'New' }}</div>
            <h1 class="font-serif font-normal tracking-[-0.01em] text-[28px] sm:text-[36px] leading-[1.0]">
                {{ $isEdit ? 'Edit' : 'Create a' }} <span
                    class="italic text-wa-deep">{{ $isEdit ? 'package' : 'package' }}</span></h1>
            <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">
                {{ __("Set pricing, numeric limits, feature toggles, and display options. Empty limit field = unlimited. Unchecked toggle = workspaces on this plan can't use that feature.") }}
            </p>
        </div>
    </div>

    <main class="px-4 sm:px-7 pb-7">
        @if (session('success'))
            <div class="mb-4 rounded-2xl border border-wa-green/40 bg-wa-bubble text-wa-deep px-4 py-2 text-[12.5px]">
                {{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div
                class="mb-4 rounded-2xl border border-accent-coral/40 bg-accent-coral/10 text-accent-coral px-4 py-2 text-[12.5px]">
                {{ session('error') }}</div>
        @endif
        @if ($errors->any())
            <div
                class="mb-4 rounded-2xl border border-accent-coral/40 bg-accent-coral/10 text-accent-coral px-4 py-3 text-[12.5px]">
                <div class="font-semibold mb-1">{{ __('Please fix the following:') }}</div>
                <ul class="list-disc pl-5">
                    @foreach ($errors->all() as $e)
                        <li>{{ $e }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form id="pkgForm" method="POST" action="{{ $action }}">
            @csrf
            @if ($isEdit)
                @method('PATCH')
            @endif

            <div class="bg-white border border-paper-200 rounded-2xl shadow-card overflow-hidden">

                {{-- Stepper bar --}}
                <div class="px-5 py-4 border-b border-paper-200 bg-paper-50/40 overflow-x-auto">
                    <div class="flex items-center min-w-[520px]" id="stepper">
                        @php $steps = $hasIgStep
                            ? ['Basics', 'Limits', 'Features', 'Instagram', 'Display & review']
                            : ['Basics', 'Limits', 'Features', 'Display & review']; @endphp
                        @foreach ($steps as $i => $label)
                            <div class="step-node flex items-center gap-2.5 {{ $loop->last ? '' : 'flex-1' }} cursor-pointer"
                                data-n="{{ $loop->iteration }}">
                                <span
                                    class="dot w-7 h-7 rounded-full grid place-items-center text-[11px] font-semibold font-mono shrink-0 transition border-[1.5px] bg-paper-0 {{ $loop->first ? 'border-wa-deep text-wa-deep ring-4 ring-wa-deep/10' : 'border-paper-200 text-ink-500' }}">{{ $loop->iteration }}</span>
                                <span
                                    class="lab text-[11.5px] {{ $loop->first ? 'font-semibold text-wa-deep' : 'font-medium text-ink-500' }} whitespace-nowrap">{{ $label }}</span>
                                @if (!$loop->last)
                                    <span class="bar flex-1 h-[2px] mx-2 rounded bg-paper-200"></span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="p-5">

                    {{-- STEP 1 — Basics --}}
                    <div class="step-pane" data-step="1">
                        <div class="flex items-center gap-2.5 mb-4">
                            <span
                                class="w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">01</span>
                            <span
                                class="font-serif text-[18px] leading-none text-ink-900 flex-1">{{ __('Plan basics') }}</span>
                            <span class="font-mono text-[10px] text-ink-500">{{ __('required') }}</span>
                        </div>

                        {{-- Package type + data retention. An ADD-ON grants its toggles/limits
                             ON TOP of a customer's plan (they buy it instead of upgrading);
                             data-retention auto-wipes a workspace's data N days after expiry. --}}
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">
                            <div>
                                <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Package type') }}
                                    <x-info-hint align="left" :title="__('Plan vs Add-on')"
                                        :text="__('Plan = a full subscription a customer sits on. Add-on = a small pack bought on top of their current plan that only grants the extra features/limits you toggle below (it never changes their base plan).')" />
                                </label>
                                <select name="type"
                                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                    <option value="plan"  @selected($val('type', request('type', 'plan')) === 'plan')>{{ __('Plan — full subscription') }}</option>
                                    <option value="addon" @selected($val('type', request('type', 'plan')) === 'addon')>{{ __('Add-on — feature pack bought on top of a plan') }}</option>
                                </select>
                                <div class="text-[10.5px] text-ink-500 mt-1">{{ __('Add-on: toggle only the features/limits it grants below; customers buy it to unlock those without changing plan.') }}</div>
                            </div>
                            <div>
                                <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Data retention (days after expiry)') }}
                                    <x-info-hint align="left" :title="__('Data retention')"
                                        :text="__('How long a workspace keeps its data after its plan expires. 0 = keep forever. Set e.g. 30 to auto-wipe an expired workspace 30 days later.')" />
                                </label>
                                <input type="number" name="data_retention_days" value="{{ $val('data_retention_days', 0) }}" min="0" max="3650"
                                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                    placeholder="0">
                                <div class="text-[10.5px] text-ink-500 mt-1">{{ __('0 = never. After the plan is expired this many days, the workspace\'s data is auto-wiped.') }}</div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">
                            <div>
                                <label
                                    class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Package name') }}
                                    <span class="text-accent-coral">*</span></label>
                                <input type="text" name="pname" value="{{ $val('pname') }}" required
                                    maxlength="120"
                                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                    placeholder="{{ __('Starter / Growth / Pro / Enterprise') }}">
                            </div>
                            <div>
                                <label
                                    class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Subtitle') }}</label>
                                <input type="text" name="subtitle" value="{{ $val('subtitle') }}" maxlength="191"
                                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                    placeholder="{{ __('For growing teams') }}">
                            </div>
                            <div>
                                <label
                                    class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Price') }}
                                    <span class="text-accent-coral">*</span></label>
                                <input type="number" name="plan_amount" value="{{ $val('plan_amount', 0) }}" required
                                    step="0.01" min="0"
                                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                <div class="text-[10.5px] text-ink-500 mt-1">0 = free. Set "Free plan" toggle below.
                                </div>
                            </div>
                            <div>
                                <label
                                    class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Offer price (discounted)') }}
                                    <x-info-hint align="left" :title="__('Offer price')"
                                        :text="__('Optional sale price. When set, the pricing page shows this crossed-out against the full Price. Leave blank for no discount.')" /></label>
                                <input type="number" name="offer_price" value="{{ $val('offer_price') }}"
                                    step="0.01" min="0"
                                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                    placeholder="{{ __('optional') }}">
                            </div>
                            <div>
                                <label
                                    class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Currency') }}
                                    <span class="text-accent-coral">*</span></label>
                                <select name="currency" required
                                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                    @forelse ($currencies as $c)
                                        <option value="{{ $c->code }}" @selected($val('currency', 'USD') === $c->code)>
                                            {{ $c->code }} — {{ $c->name }}</option>
                                    @empty
                                        <option value="USD">{{ __('USD — US Dollar') }}</option>
                                        <option value="INR">{{ __('INR — Indian Rupee') }}</option>
                                        <option value="EUR">{{ __('EUR — Euro') }}</option>
                                    @endforelse
                                </select>
                            </div>
                            <div>
                                <label
                                    class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Billing period') }}
                                    <span class="text-accent-coral">*</span></label>
                                <div class="flex gap-2">
                                    <input type="number" name="plan_duration"
                                        value="{{ $val('plan_duration', 1) }}" required min="1"
                                        max="120"
                                        class="w-24 px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                    <select name="plan_unit"
                                        class="flex-1 px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                        @foreach (['days', 'weeks', 'months', 'years'] as $u)
                                            <option value="{{ $u }}" @selected($val('plan_unit', 'months') === $u)>
                                                {{ $u }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="text-[10.5px] text-ink-500 mt-1">e.g. <b>1 month</b> = monthly recurring,
                                    <b>1 year</b> = annual.</div>
                            </div>
                        </div>

                        <div>
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Plan description (shown on pricing page)') }}</label>
                            <textarea name="detail" rows="3" maxlength="5000"
                                class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                placeholder="{{ __('Short selling text shown to customers under the plan name.') }}">{{ $val('detail') }}</textarea>
                        </div>

                        <div class="mt-5 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                            @php $basicsFlags = [
                                    'free' => ['Free plan', 'No charge, no recurring billing'],
                                    'lifetime' => ['Lifetime', 'One-time payment, no renewal'],
                                    'status' => ['Active', 'Visible on the public pricing page'],
                                    'is_default' => [
                                        'Default for signups',
                                        'New workspaces get this plan automatically',
                                    ],
                                    'is_highlighted' => ['Highlight as popular', 'Adds "Most popular" badge'],
                                    'is_custom_quote' => ['Custom quote', 'Hides price, shows "Contact sales"'],
                            ]; @endphp
                            @foreach ($basicsFlags as $name => $meta)
                                <label
                                    class="border border-paper-200 rounded-lg px-3 py-2.5 flex items-center justify-between gap-3 cursor-pointer hover:bg-paper-50">
                                    <span>
                                        <span class="block text-[12.5px] font-semibold">{{ $meta[0] }}</span>
                                        <span class="block text-[10.5px] text-ink-500">{{ $meta[1] }}</span>
                                    </span>
                                    <input type="hidden" name="{{ $name }}" value="0">
                                    <span class="relative inline-block w-[34px] h-5 shrink-0">
                                        <input class="peer opacity-0 w-0 h-0" type="checkbox"
                                            name="{{ $name }}" value="1" @checked($val($name, $name === 'status'))>
                                        <span
                                            class="absolute cursor-pointer inset-0 bg-paper-200 rounded-full transition before:content-[''] before:absolute before:h-4 before:w-4 before:left-0.5 before:bottom-0.5 before:bg-paper-0 before:rounded-full before:transition peer-checked:bg-wa-deep peer-checked:before:translate-x-[14px]"></span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    {{-- STEP 2 — Limits --}}
                    <div class="step-pane hidden" data-step="2">
                        <div class="flex items-center gap-2.5 mb-4">
                            <span
                                class="w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">02</span>
                            <span
                                class="font-serif text-[18px] leading-none text-ink-900 flex-1">{{ __('Numeric limits') }}</span>
                            <span
                                class="font-mono text-[10px] text-ink-500">{{ __('leave blank = unlimited') }}</span>
                        </div>
                        @foreach ($limitGroups as $groupName => $cols)
                            <div class="mb-5">
                                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-2">
                                    {{ $groupName }}</div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                                    @foreach ($cols as $col)
                                        @continue (!in_array($col, $limitColumns, true))
                                        <label class="block">
                                            <span
                                                class="text-[11.5px] font-semibold text-ink-700 mb-1 flex items-center justify-between gap-1.5">
                                                <span class="truncate">{{ $limitLabels[$col] ?? ucfirst(str_replace('_', ' ', $col)) }}</span>
                                                <x-info-hint align="right"
                                                    :text="__('Cap on :name for a workspace on this plan. Leave blank (∞) for unlimited; type a number to cap it.', ['name' => strtolower($limitLabels[$col] ?? str_replace('_', ' ', $col))])" />
                                            </span>
                                            {{-- 0 = unlimited in storage; show it as a BLANK field (with the
                                                 "∞ unlimited" placeholder) so admins don't read it as a zero cap.
                                                 Typing a real number (5, 10, …) still saves normally. --}}
                                            <input type="number" name="{{ $col }}" data-limit-input
                                                value="{{ $val($col) ?: '' }}" min="0" placeholder="∞ unlimited"
                                                class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                            <span
                                                class="block text-[9.5px] text-ink-500 mt-0.5 font-mono">{{ $col }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>

                    {{-- STEP 3 — Features --}}
                    <div class="step-pane hidden" data-step="3">
                        <div class="flex items-center gap-2.5 mb-4">
                            <span
                                class="w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">03</span>
                            <span
                                class="font-serif text-[18px] leading-none text-ink-900 flex-1">{{ __('Feature toggles') }}</span>
                            <span class="font-mono text-[10px] text-ink-500">{{ __('unchecked = blocked') }}</span>
                        </div>
                        @foreach ($featureGroups as $groupName => $cols)
                            <div class="mb-5">
                                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-2">
                                    {{ $groupName }}</div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                    @foreach ($cols as $col)
                                        @continue (!in_array($col, $featureToggles, true))
                                        <label
                                            class="flex items-center gap-3 px-3 py-2 border border-paper-200 rounded-lg cursor-pointer hover:bg-paper-50">
                                            <input type="hidden" name="{{ $col }}" value="0">
                                            <span class="relative inline-block w-[30px] h-[18px] shrink-0">
                                                <input class="peer opacity-0 w-0 h-0" type="checkbox"
                                                    name="{{ $col }}" value="1" data-feature-toggle
                                                    @checked($val($col, true))>
                                                <span
                                                    class="absolute cursor-pointer inset-0 bg-paper-200 rounded-full transition before:content-[''] before:absolute before:h-3.5 before:w-3.5 before:left-0.5 before:bottom-0.5 before:bg-paper-0 before:rounded-full before:transition peer-checked:bg-wa-deep peer-checked:before:translate-x-[12px]"></span>
                                            </span>
                                            <span class="flex-1 min-w-0">
                                                <span
                                                    class="block text-[12.5px] font-semibold truncate">{{ $featureLabels[$col] ?? ucfirst(str_replace('_', ' ', $col)) }}</span>
                                                <span
                                                    class="block text-[9.5px] text-ink-500 font-mono truncate">{{ $col }}</span>
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>

                    {{-- STEP 4 — Instagram add-on (only when the add-on contributed fields) --}}
                    @if ($hasIgStep)
                        <div class="step-pane hidden" data-step="4">
                            <div class="flex items-center gap-2.5 mb-4">
                                <span class="w-[23px] h-[23px] rounded-[7px] inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0 text-white"
                                    style="background:linear-gradient(135deg,#833AB4,#E1306C,#F77737)">04</span>
                                <span class="font-serif text-[18px] leading-none text-ink-900 flex-1">{{ __('Instagram') }}</span>
                                <span class="font-mono text-[10px] text-ink-500">{{ __('add-on plan') }}</span>
                            </div>
                            <p class="text-[11.5px] text-ink-500 mb-4">
                                {{ __('Toggle which Instagram capabilities this plan unlocks, and set its usage caps. Leave a cap blank for unlimited.') }}</p>

                            {{-- Instagram feature toggles --}}
                            @if (!empty($igFeatures))
                                <div class="mb-5">
                                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-2">{{ __('Features') }}</div>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                        @foreach ($igFeatures as $col)
                                            @continue (!in_array($col, $featureToggles, true))
                                            <label class="flex items-center gap-3 px-3 py-2 border border-paper-200 rounded-lg cursor-pointer hover:bg-paper-50">
                                                <input type="hidden" name="{{ $col }}" value="0">
                                                <span class="relative inline-block w-[30px] h-[18px] shrink-0">
                                                    <input class="peer opacity-0 w-0 h-0" type="checkbox" name="{{ $col }}" value="1" data-feature-toggle @checked($val($col, true))>
                                                    <span class="absolute cursor-pointer inset-0 bg-paper-200 rounded-full transition before:content-[''] before:absolute before:h-3.5 before:w-3.5 before:left-0.5 before:bottom-0.5 before:bg-paper-0 before:rounded-full before:transition peer-checked:bg-wa-deep peer-checked:before:translate-x-[12px]"></span>
                                                </span>
                                                <span class="flex-1 min-w-0">
                                                    <span class="block text-[12.5px] font-semibold truncate">{{ $featureLabels[$col] ?? ucfirst(str_replace('_', ' ', $col)) }}</span>
                                                    <span class="block text-[9.5px] text-ink-500 font-mono truncate">{{ $col }}</span>
                                                </span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            {{-- Instagram limit inputs --}}
                            @if (!empty($igLimits))
                                <div class="mb-2">
                                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-2">{{ __('Usage caps') }}</div>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                                        @foreach ($igLimits as $col)
                                            @continue (!in_array($col, $limitColumns, true))
                                            <label class="block">
                                                <span class="text-[11.5px] font-semibold text-ink-700 mb-1 block">{{ $limitLabels[$col] ?? ucfirst(str_replace('_', ' ', $col)) }}</span>
                                                <input type="number" name="{{ $col }}" data-limit-input
                                                    value="{{ $val($col) ?: '' }}" min="0" placeholder="∞ unlimited"
                                                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                                <span class="block text-[9.5px] text-ink-500 mt-0.5 font-mono">{{ $col }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endif

                    {{-- FINAL STEP — Display + Review --}}
                    <div class="step-pane hidden" data-step="{{ $hasIgStep ? 5 : 4 }}">
                        <div class="flex items-center gap-2.5 mb-4">
                            <span
                                class="w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">{{ $hasIgStep ? '05' : '04' }}</span>
                            <span
                                class="font-serif text-[18px] leading-none text-ink-900 flex-1">{{ __('Display, CTA & review') }}</span>
                            <span class="font-mono text-[10px] text-ink-500">{{ __('final step') }}</span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 mb-5">
                            <div>
                                <label
                                    class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('CTA label') }}</label>
                                <input type="text" name="cta_label"
                                    value="{{ $val('cta_label', 'Get started') }}" maxlength="64"
                                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                            </div>
                            {{-- CTA URL removed — plan buttons always go to checkout; no custom URL needed. --}}
                            <div>
                                <label
                                    class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Sort order') }}</label>
                                <input type="number" name="sort_order" value="{{ $val('sort_order', 0) }}"
                                    min="0"
                                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                <div class="text-[10.5px] text-ink-500 mt-1">
                                    {{ __('Lower number = shown first on pricing page.') }}</div>
                            </div>
                        </div>

                        {{-- Live review summary — read by JS from form state. --}}
                        <div class="rounded-2xl border border-paper-200 bg-paper-50/50 p-5">
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-3">
                                {{ __('Review') }}</div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-[12.5px]" id="pkg-review">
                                {{-- JS injects rows here on entering step 4. --}}
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </form>
    </main>

</x-layouts.admin>
