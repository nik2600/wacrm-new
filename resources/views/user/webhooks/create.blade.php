<x-layouts.user :title="isset($hook) ? __('Edit Webhook') : __('New Webhook')" nav-key="more" page="user-webhooks-create">

    @php $editing = isset($hook); $hEvents = $editing ? (array) ($hook->events ?? []) : []; @endphp

    <!-- Sticky toolbar -->
    <div class="border-b border-paper-200 bg-paper-0 sticky top-0 z-20">
        <div class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-3 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3 min-w-0">
                <a href="{{ url('/webhooks') }}"
                    class="w-8 h-8 rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50 flex items-center justify-center"
                    title="{{ __('Back') }}"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none"
                        stroke="currentColor" stroke-width="1.6">
                        <path d="M10 4l-4 4 4 4" />
                    </svg></a>
                <div class="min-w-0">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                        {{ $editing ? __('Webhooks / Edit') : __('Webhooks / New') }}</div>
                    <div class="font-serif text-[20px] leading-tight truncate">{{ $editing ? __('Edit') : __('Add a') }} <span
                            class="italic text-wa-deep">{{ __('webhook') }}</span> endpoint</div>
                </div>
            </div>
            <div class="flex items-center gap-2 flex-wrap justify-end">
                <span
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium bg-paper-50 text-ink-700 font-mono">{{ $editing ? __('Editing') : __('Draft / unsaved') }}</span>
                <button type="button" id="wh-test-fire"
                    class="px-3.5 py-1.5 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium inline-flex items-center gap-1.5 disabled:opacity-50 disabled:cursor-not-allowed">
                    <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.7">
                        <path d="M3 11h10M8 4v9M5 7l3-3 3 3" />
                    </svg>
                    <span id="wh-test-fire-label">Test fire</span>
                </button>
                <button type="submit" form="webhookForm"
                    class="px-3.5 py-1.5 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold flex items-center gap-2">
                    <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M2 8l5 5 7-9" />
                    </svg>
                    Save webhook
                </button>
            </div>
        </div>
    </div>

    <section class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-6">
        <form id="webhookForm" class="grid grid-cols-1 xl:grid-cols-[1fr_360px] gap-5 items-start">

            <div class="bg-white border border-paper-200 rounded-2xl shadow-card overflow-hidden">

                <!-- 01 Endpoint -->
                <div class="px-5 py-4 border-b border-paper-200">
                    <div class="flex items-center gap-2.5 mb-4">
                        <span
                            class="w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">01</span>
                        <span
                            class="font-serif text-[18px] leading-none text-ink-900 flex-1">{{ __('Endpoint') }}</span>
                        <span class="font-mono text-[10px] text-ink-500">{{ __('required') }}</span>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-[1fr_140px] gap-3 mb-3">
                        <div>
                            <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block"
                                for="wh-url">{{ __('Webhook URL') }} <span class="text-accent-coral">*</span></label>
                            <input id="wh-url" type="url" value="{{ $editing ? $hook->webhook_url : '' }}" placeholder="https://api.brand.example/{{ \Illuminate\Support\Str::slug(brand_name()) }}/events"
                                class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                required oninput="document.getElementById('rv-url').textContent=this.value||'—'">
                            <div class="text-[10.5px] text-ink-500 mt-1">
                                {{ __("Must be HTTPS. We'll POST a signed JSON payload to this URL.") }}</div>
                        </div>
                        <div>
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Method') }}</label>
                            <select id="wh-method"
                                class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                <option value="POST" @selected($editing && $hook->http_method === 'POST')>{{ __('POST') }}</option>
                                <option value="PUT" @selected($editing && $hook->http_method === 'PUT')>{{ __('PUT') }}</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block"
                            for="wh-name">{{ __('Internal name') }} <span
                                class="text-[10.5px] font-normal text-ink-500">(optional)</span></label>
                        <input id="wh-name" type="text" value="{{ $editing ? $hook->name : '' }}" placeholder="{{ __('Production CRM relay') }}"
                            class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                            oninput="document.getElementById('rv-name').textContent=this.value||'—'">
                    </div>
                </div>

                <!-- 02 Events -->
                <div class="px-5 py-4 border-b border-paper-200">
                    <div class="flex items-center gap-2.5 mb-4">
                        <span
                            class="w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">02</span>
                        <span
                            class="font-serif text-[18px] leading-none text-ink-900 flex-1">{{ __('Events to subscribe') }}</span>
                        <span class="font-mono text-[10px] text-ink-500">{{ __('at least one') }}</span>
                    </div>

                    <div class="flex items-center gap-2 mb-3">
                        <button type="button" id="ev-all"
                            class="px-3 py-1.5 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[11.5px] font-medium">{{ __('Select all') }}</button>
                        <button type="button" id="ev-none"
                            class="px-3 py-1.5 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[11.5px] font-medium">{{ __('Clear') }}</button>
                        <span class="ml-auto text-[10.5px] font-mono text-ink-500"><span id="ev-count">0</span>
                            selected</span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2" id="event-list">
                        <label
                            class="flex items-start gap-3 px-3 py-2.5 border border-paper-200 rounded-lg hover:border-wa-deep cursor-pointer">
                            <input type="checkbox" class="ev peer sr-only" data-ev="message_delivered">
                            <span
                                class="w-5 h-5 rounded border-[1.5px] border-paper-200 grid place-items-center peer-checked:bg-wa-deep peer-checked:border-wa-deep peer-checked:[&>svg]:opacity-100 transition shrink-0 mt-0.5"><svg
                                    viewBox="0 0 16 16" class="w-3 h-3 text-paper-0 opacity-0 transition" fill="none"
                                    stroke="currentColor" stroke-width="2.4">
                                    <path d="M3 8l3 3 7-8" />
                                </svg></span>
                            <div class="min-w-0">
                                <div class="font-mono text-[12px] text-ink-900">{{ __('message.delivered') }}</div>
                                <div class="text-[10.5px] text-ink-500">
                                    {{ __("Fired once a message lands on the recipient's device.") }}</div>
                            </div>
                        </label>
                        <label
                            class="flex items-start gap-3 px-3 py-2.5 border border-paper-200 rounded-lg hover:border-wa-deep cursor-pointer">
                            <input type="checkbox" class="ev peer sr-only" data-ev="message_read">
                            <span
                                class="w-5 h-5 rounded border-[1.5px] border-paper-200 grid place-items-center peer-checked:bg-wa-deep peer-checked:border-wa-deep peer-checked:[&>svg]:opacity-100 transition shrink-0 mt-0.5"><svg
                                    viewBox="0 0 16 16" class="w-3 h-3 text-paper-0 opacity-0 transition" fill="none"
                                    stroke="currentColor" stroke-width="2.4">
                                    <path d="M3 8l3 3 7-8" />
                                </svg></span>
                            <div class="min-w-0">
                                <div class="font-mono text-[12px] text-ink-900">{{ __('message.read') }}</div>
                                <div class="text-[10.5px] text-ink-500">
                                    {{ __('Recipient opened the message — both ticks turned blue.') }}</div>
                            </div>
                        </label>
                        <label
                            class="flex items-start gap-3 px-3 py-2.5 border border-paper-200 rounded-lg hover:border-wa-deep cursor-pointer">
                            <input type="checkbox" class="ev peer sr-only" data-ev="message_received">
                            <span
                                class="w-5 h-5 rounded border-[1.5px] border-paper-200 grid place-items-center peer-checked:bg-wa-deep peer-checked:border-wa-deep peer-checked:[&>svg]:opacity-100 transition shrink-0 mt-0.5"><svg
                                    viewBox="0 0 16 16" class="w-3 h-3 text-paper-0 opacity-0 transition"
                                    fill="none" stroke="currentColor" stroke-width="2.4">
                                    <path d="M3 8l3 3 7-8" />
                                </svg></span>
                            <div class="min-w-0">
                                <div class="font-mono text-[12px] text-ink-900">{{ __('message.received') }}</div>
                                <div class="text-[10.5px] text-ink-500">
                                    {{ __('Inbound message from a contact — text, media, location.') }}</div>
                            </div>
                        </label>
                        <label
                            class="flex items-start gap-3 px-3 py-2.5 border border-paper-200 rounded-lg hover:border-wa-deep cursor-pointer">
                            <input type="checkbox" class="ev peer sr-only" data-ev="message_failed">
                            <span
                                class="w-5 h-5 rounded border-[1.5px] border-paper-200 grid place-items-center peer-checked:bg-wa-deep peer-checked:border-wa-deep peer-checked:[&>svg]:opacity-100 transition shrink-0 mt-0.5"><svg
                                    viewBox="0 0 16 16" class="w-3 h-3 text-paper-0 opacity-0 transition"
                                    fill="none" stroke="currentColor" stroke-width="2.4">
                                    <path d="M3 8l3 3 7-8" />
                                </svg></span>
                            <div class="min-w-0">
                                <div class="font-mono text-[12px] text-ink-900">{{ __('message.failed') }}</div>
                                <div class="text-[10.5px] text-ink-500">
                                    {{ __("Send didn't reach WhatsApp — payload includes failure reason.") }}</div>
                            </div>
                        </label>
                        <label
                            class="flex items-start gap-3 px-3 py-2.5 border border-paper-200 rounded-lg hover:border-wa-deep cursor-pointer">
                            <input type="checkbox" class="ev peer sr-only" data-ev="contact_opt_in">
                            <span
                                class="w-5 h-5 rounded border-[1.5px] border-paper-200 grid place-items-center peer-checked:bg-wa-deep peer-checked:border-wa-deep peer-checked:[&>svg]:opacity-100 transition shrink-0 mt-0.5"><svg
                                    viewBox="0 0 16 16" class="w-3 h-3 text-paper-0 opacity-0 transition"
                                    fill="none" stroke="currentColor" stroke-width="2.4">
                                    <path d="M3 8l3 3 7-8" />
                                </svg></span>
                            <div class="min-w-0">
                                <div class="font-mono text-[12px] text-ink-900">{{ __('contact.opt_in') }}</div>
                                <div class="text-[10.5px] text-ink-500">
                                    {{ __('A contact agreed to receive messages from your business.') }}</div>
                            </div>
                        </label>
                        <label
                            class="flex items-start gap-3 px-3 py-2.5 border border-paper-200 rounded-lg hover:border-wa-deep cursor-pointer">
                            <input type="checkbox" class="ev peer sr-only" data-ev="contact_opt_out">
                            <span
                                class="w-5 h-5 rounded border-[1.5px] border-paper-200 grid place-items-center peer-checked:bg-wa-deep peer-checked:border-wa-deep peer-checked:[&>svg]:opacity-100 transition shrink-0 mt-0.5"><svg
                                    viewBox="0 0 16 16" class="w-3 h-3 text-paper-0 opacity-0 transition"
                                    fill="none" stroke="currentColor" stroke-width="2.4">
                                    <path d="M3 8l3 3 7-8" />
                                </svg></span>
                            <div class="min-w-0">
                                <div class="font-mono text-[12px] text-ink-900">{{ __('contact.opt_out') }}</div>
                                <div class="text-[10.5px] text-ink-500">
                                    {{ __('Contact replied STOP or blocked — stop messaging immediately.') }}</div>
                            </div>
                        </label>
                        <label
                            class="flex items-start gap-3 px-3 py-2.5 border border-paper-200 rounded-lg hover:border-wa-deep cursor-pointer">
                            <input type="checkbox" class="ev peer sr-only" data-ev="contact_updated">
                            <span
                                class="w-5 h-5 rounded border-[1.5px] border-paper-200 grid place-items-center peer-checked:bg-wa-deep peer-checked:border-wa-deep peer-checked:[&>svg]:opacity-100 transition shrink-0 mt-0.5"><svg
                                    viewBox="0 0 16 16" class="w-3 h-3 text-paper-0 opacity-0 transition"
                                    fill="none" stroke="currentColor" stroke-width="2.4">
                                    <path d="M3 8l3 3 7-8" />
                                </svg></span>
                            <div class="min-w-0">
                                <div class="font-mono text-[12px] text-ink-900">{{ __('contact.updated') }}</div>
                                <div class="text-[10.5px] text-ink-500">
                                    {{ __('Profile changes — name, phone, custom fields.') }}</div>
                            </div>
                        </label>
                        <label
                            class="flex items-start gap-3 px-3 py-2.5 border border-paper-200 rounded-lg hover:border-wa-deep cursor-pointer">
                            <input type="checkbox" class="ev peer sr-only" data-ev="campaign_status_updated">
                            <span
                                class="w-5 h-5 rounded border-[1.5px] border-paper-200 grid place-items-center peer-checked:bg-wa-deep peer-checked:border-wa-deep peer-checked:[&>svg]:opacity-100 transition shrink-0 mt-0.5"><svg
                                    viewBox="0 0 16 16" class="w-3 h-3 text-paper-0 opacity-0 transition"
                                    fill="none" stroke="currentColor" stroke-width="2.4">
                                    <path d="M3 8l3 3 7-8" />
                                </svg></span>
                            <div class="min-w-0">
                                <div class="font-mono text-[12px] text-ink-900">{{ __('campaign.completed') }}</div>
                                <div class="text-[10.5px] text-ink-500">
                                    {{ __('All recipients in a scheduled blast have been processed.') }}</div>
                            </div>
                        </label>
                        <label
                            class="flex items-start gap-3 px-3 py-2.5 border border-paper-200 rounded-lg hover:border-wa-deep cursor-pointer">
                            <input type="checkbox" class="ev peer sr-only" data-ev="template_status_changed">
                            <span
                                class="w-5 h-5 rounded border-[1.5px] border-paper-200 grid place-items-center peer-checked:bg-wa-deep peer-checked:border-wa-deep peer-checked:[&>svg]:opacity-100 transition shrink-0 mt-0.5"><svg
                                    viewBox="0 0 16 16" class="w-3 h-3 text-paper-0 opacity-0 transition"
                                    fill="none" stroke="currentColor" stroke-width="2.4">
                                    <path d="M3 8l3 3 7-8" />
                                </svg></span>
                            <div class="min-w-0">
                                <div class="font-mono text-[12px] text-ink-900">{{ __('template.status_changed') }}
                                </div>
                                <div class="text-[10.5px] text-ink-500">
                                    {{ __('A template was approved, rejected, or paused by Meta.') }}</div>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- 03 Security -->
                <div class="px-5 py-4 border-b border-paper-200">
                    <div class="flex items-center gap-2.5 mb-4">
                        <span
                            class="w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">03</span>
                        <span
                            class="font-serif text-[18px] leading-none text-ink-900 flex-1">{{ __('Security') }}</span>
                        <span class="font-mono text-[10px] text-ink-500">{{ __('recommended') }}</span>
                    </div>
                    <label
                        class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Signing secret') }}</label>
                    <div class="flex gap-2">
                        <input id="wh-secret" type="text" readonly value="{{ $editing ? $hook->secret : ('whsec_' . \Illuminate\Support\Str::random(40)) }}"
                            class="flex-1 px-3 py-2 border border-paper-200 rounded-lg bg-paper-50/40 text-[12.5px] font-mono text-ink-700 focus:outline-none">
                        <button type="button"
                            onclick="navigator.clipboard&&navigator.clipboard.writeText(document.getElementById('wh-secret').value)"
                            class="px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('Copy') }}</button>
                        <button type="button"
                            class="px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('Rotate') }}</button>
                    </div>
                    <div class="text-[10.5px] text-ink-500 mt-1.5">{{ __('Verify each request via') }} <span
                            class="font-mono">{{ __('X-:brand-Signature: t=...,v1=...', ['brand' => brand_name()]) }}</span> using HMAC-SHA256.
                    </div>

                    <label
                        class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block mt-4">{{ __('Custom headers') }}
                        <span class="text-[10.5px] font-normal text-ink-500">(optional)</span></label>
                    <div id="hdr-list" class="space-y-2">
                        <div class="flex items-center gap-2">
                            <input type="text" placeholder="{{ __('X-API-Key') }}"
                                class="w-1/3 px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] font-mono focus:outline-none focus:border-wa-deep">
                            <input type="text" placeholder="{{ __('value') }}"
                                class="flex-1 px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] font-mono focus:outline-none focus:border-wa-deep">
                            <button type="button" onclick="this.parentElement.remove()"
                                class="w-9 h-9 rounded-lg hover:bg-accent-coral/15 text-accent-coral grid place-items-center"><svg
                                    viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                    stroke-width="1.7">
                                    <path d="M4 4l8 8M12 4l-8 8" />
                                </svg></button>
                        </div>
                    </div>
                    <button type="button" onclick="addHeader()"
                        class="mt-2 text-[12px] text-wa-deep font-semibold hover:underline inline-flex items-center gap-1"><svg
                            viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <path d="M8 3v10M3 8h10" />
                        </svg>{{ __('Add header') }}</button>
                </div>

                <!-- 04 Retry policy -->
                <div class="px-5 py-4 border-b border-paper-200">
                    <div class="flex items-center gap-2.5 mb-4">
                        <span
                            class="w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">04</span>
                        <span
                            class="font-serif text-[18px] leading-none text-ink-900 flex-1">{{ __('Retry policy') }}</span>
                        <span class="font-mono text-[10px] text-ink-500">{{ __('defaults are sane') }}</span>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Max attempts') }}</label>
                            <select
                                class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep">
                                <option>3 (default)</option>
                                <option>5</option>
                                <option>8</option>
                                <option>{{ __('None — fire once') }}</option>
                            </select>
                        </div>
                        <div>
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Backoff') }}</label>
                            <select
                                class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep">
                                <option>{{ __('Exponential (default)') }}</option>
                                <option>{{ __('Linear') }}</option>
                                <option>{{ __('Fixed 30 s') }}</option>
                            </select>
                        </div>
                        <div>
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Timeout') }}</label>
                            <div class="flex items-center gap-2">
                                <input type="number" min="1" value="10"
                                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep">
                                <span class="text-[11px] font-mono text-ink-500">{{ __('sec') }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="text-[10.5px] text-ink-500 mt-2">
                        {{ __('We mark a delivery successful on any 2xx response. 4xx and 5xx will be retried per the policy above.') }}
                    </div>
                </div>

                <!-- 05 Status -->
                <div class="px-5 py-4">
                    <div class="flex items-center gap-2.5 mb-3">
                        <span
                            class="w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">05</span>
                        <span
                            class="font-serif text-[18px] leading-none text-ink-900 flex-1">{{ __('Status') }}</span>
                    </div>
                    <div
                        class="flex items-center justify-between bg-paper-50/60 border border-paper-200 rounded-lg p-3">
                        <div>
                            <div class="text-[13px] font-semibold">{{ __('Activate immediately') }}</div>
                            <div class="text-[11px] text-ink-500">
                                {{ __('Endpoint will start receiving the events you selected as soon as you save.') }}
                            </div>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input id="wh-status" type="checkbox" class="sr-only peer" @checked(!$editing || $hook->status)>
                            <span
                                class="w-10 h-[22px] rounded-full bg-paper-200 peer-checked:bg-wa-deep transition-colors"></span>
                            <span
                                class="absolute top-0.5 left-0.5 w-[18px] h-[18px] rounded-full bg-paper-0 shadow transition-transform peer-checked:translate-x-[18px]"></span>
                        </label>
                    </div>
                </div>

            </div>

            <!-- Right rail: payload preview + summary -->
            <aside class="space-y-4 xl:sticky xl:top-[78px] xl:self-start">
                <div class="bg-white border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                    <div class="px-4 py-3 border-b border-paper-200 flex items-center justify-between">
                        <span
                            class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Sample payload') }}</span>
                        <span class="font-mono text-[10px] text-wa-deep"
                            id="rv-event">{{ __('message.delivered') }}</span>
                    </div>
                    <pre
                        class="p-4 text-[11px] font-mono leading-snug text-ink-700 bg-paper-50/40 max-h-[360px] overflow-auto whitespace-pre-wrap">{
 "id": "evt_01HRJX2Y9C3T8",
 "type": "message.delivered",
 "created": 1714122488,
 "data": {
 "message_id": "wamid.HBgL...8c4321",
 "to": "+919876543210",
 "device": "+919876500000",
 "delivered_at": "2026-04-26T18:32:08Z",
 "template": "spring_promo_v3",
 "campaign": "Spring promo / VIP"
 }
}</pre>
                    <div
                        class="px-4 py-3 border-t border-paper-200 bg-paper-50/40 flex items-center justify-between text-[11px]">
                        <span class="font-mono text-ink-500">{{ __('Content-Type: application/json') }}</span>
                        <button type="button"
                            class="text-wa-deep font-semibold hover:underline">{{ __('Copy') }}</button>
                    </div>
                </div>

                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-3">
                        {{ __('Summary') }}</div>
                    <dl class="space-y-2 text-[12px]">
                        <div class="flex items-center justify-between">
                            <dt class="text-ink-500">{{ __('Name') }}</dt>
                            <dd class="font-mono text-ink-900 truncate ml-2" id="rv-name">—</dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="text-ink-500">{{ __('URL') }}</dt>
                            <dd class="font-mono text-ink-900 truncate ml-2" id="rv-url">—</dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="text-ink-500">{{ __('Events') }}</dt>
                            <dd class="font-mono text-ink-900" id="rv-events">0</dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="text-ink-500">{{ __('Method') }}</dt>
                            <dd class="font-mono text-ink-900">{{ __('POST') }}</dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="text-ink-500">{{ __('Retries') }}</dt>
                            <dd class="font-mono text-ink-900">3 · exponential</dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="text-ink-500">{{ __('Timeout') }}</dt>
                            <dd class="font-mono text-ink-900">10 s</dd>
                        </div>
                    </dl>
                </div>

                <div class="bg-wa-deep rounded-2xl p-4 shadow-soft text-paper-0">
                    <div class="font-serif text-[18px] leading-tight">{{ __('Verify the signature') }}</div>
                    <p class="mt-2 text-[12px] text-paper-0/80 leading-relaxed">{{ __('Every request includes') }}
                        <span class="font-mono">{{ __('X-:brand-Signature', ['brand' => brand_name()]) }}</span>. Compute HMAC-SHA256 with your
                        secret over the raw body — discard requests that don't match.</p>
                </div>
            </aside>

        </form>
    </section>

    {{--
        Test fire — fires a one-shot request to the URL/secret/method the
        operator typed, WITHOUT saving the webhook. Lets them verify the
        endpoint works before they commit it to the list. Server route
        is /webhooks/test-fire-draft; the saved-row /test-fire is wired
        in the index page's JS (resources/js/charts/user-webhooks-index.js).
    --}}
    <script>
    (function () {
        const btn   = document.getElementById('wh-test-fire');
        const label = document.getElementById('wh-test-fire-label');
        if (!btn) return;

        const csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

        const flash = (kind, msg) => {
            // Prefer the platform toaster when available; fall back to alert
            // so the operator at least sees the result.
            if (window.WaToaster && typeof window.WaToaster[kind] === 'function') {
                window.WaToaster[kind](msg);
            } else {
                alert(msg);
            }
        };

        btn.addEventListener('click', async () => {
            const url    = (document.getElementById('wh-url')?.value || '').trim();
            const secret = (document.getElementById('wh-secret')?.value || '').trim();
            const method = (document.getElementById('wh-method')?.value || 'POST').toUpperCase();
            const events = Array.from(document.querySelectorAll('.ev:checked'))
                .map((el) => el.dataset.ev).filter(Boolean);

            if (!url) {
                flash('error', 'Enter a Webhook URL first.');
                document.getElementById('wh-url')?.focus();
                return;
            }
            if (!/^https?:\/\//i.test(url)) {
                flash('error', 'Webhook URL must start with http:// or https://.');
                return;
            }

            // UI lockout while in flight — same pattern the row-action wires use.
            btn.disabled = true;
            const prevLabel = label.textContent;
            label.textContent = 'Firing…';

            try {
                const resp = await fetch('{{ route('user.webhooks.test-fire-draft') }}', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        webhook_url: url,
                        secret:      secret || null,
                        http_method: method,
                        events,
                    }),
                });
                const data = await resp.json().catch(() => ({}));
                if (!resp.ok) {
                    const msg = data?.message
                        || (data?.errors ? Object.values(data.errors).flat().join(' • ') : null)
                        || `Request failed (HTTP ${resp.status}).`;
                    flash('error', msg);
                    return;
                }
                const code = data.statusCode ?? '—';
                const lat  = data.latencyMs ?? '—';
                if (data.isOk) {
                    flash('success', `Test fire OK · ${code} · ${lat}ms`);
                } else if (data.error) {
                    flash('error', `Test fire failed · ${data.error}`);
                } else {
                    flash('error', `Endpoint replied ${code} (${lat}ms) — expected 2xx.`);
                }
            } catch (e) {
                flash('error', 'Test fire failed: ' + (e?.message || 'network error'));
            } finally {
                btn.disabled = false;
                label.textContent = prevLabel;
            }
        });
    })();
    </script>

    {{--
        Save handler — the form has no action/method, so without this the
        "Save webhook" submit button just reloaded the page and NOTHING was
        saved (blank table). Collect fields + the .ev event checkboxes, POST
        JSON to the store route, then go to the list so the new row shows.
    --}}
    <script>
    (function () {
        const form    = document.getElementById('webhookForm');
        const saveBtn = document.querySelector('button[form="webhookForm"][type="submit"]');
        if (!form) return;

        const csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
        const flash = (kind, msg) => {
            if (window.WaToaster && typeof window.WaToaster[kind] === 'function') window.WaToaster[kind](msg);
            else alert(msg);
        };

        // Edit vs create: submit to the update (PUT) route when we opened this
        // form from "Edit", else the create (POST) route. Without this, editing a
        // webhook always POSTed to store() and created a DUPLICATE.
        const editing    = @json($editing);
        const saveUrl    = @json($editing ? route('user.webhooks.update', $hook->id) : route('user.webhooks.store'));
        const hookEvents = @json($hEvents);

        // Pre-tick the events this webhook is already subscribed to so the edit
        // form reflects the saved state instead of showing everything unchecked.
        if (editing && Array.isArray(hookEvents)) {
            document.querySelectorAll('.ev').forEach((el) => {
                if (hookEvents.includes(el.dataset.ev)) el.checked = true;
            });
            const cnt = document.getElementById('ev-count'); if (cnt) cnt.textContent = String(hookEvents.length);
            const rvE = document.getElementById('rv-events'); if (rvE) rvE.textContent = String(hookEvents.length);
        }

        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            const name   = (document.getElementById('wh-name')?.value || '').trim();
            const url    = (document.getElementById('wh-url')?.value || '').trim();
            const method = (document.getElementById('wh-method')?.value || 'POST').toUpperCase();
            const secret = (document.getElementById('wh-secret')?.value || '').trim();
            const events = Array.from(document.querySelectorAll('.ev:checked'))
                .map((el) => el.dataset.ev).filter(Boolean);
            const status = document.getElementById('wh-status')?.checked ?? true;

            if (!url) { flash('error', 'Enter a Webhook URL first.'); document.getElementById('wh-url')?.focus(); return; }
            if (!/^https:\/\//i.test(url)) { flash('error', 'Webhook URL must start with https://'); return; }
            if (events.length === 0) { flash('error', 'Select at least one event to subscribe to.'); return; }

            if (saveBtn) saveBtn.disabled = true;
            try {
                // Field names MUST match WebhooksController::store() validation:
                //   webhook_url   (was 'url')
                //   http_method   (was missing entirely)
                //   status        (was 'is_active')
                // The old payload sent {url, is_active} which the server discarded
                // → 422 "The webhook url field is required." Rename to match.
                const resp = await fetch(saveUrl, {
                    method: editing ? 'PUT' : 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        name,
                        webhook_url: url,
                        http_method: method,
                        secret: secret || null,
                        events,
                        status,
                    }),
                });
                // Store() currently returns a RedirectResponse (302 → /webhooks).
                // fetch() follows redirects by default, so a successful save
                // lands here as the /webhooks GET response (200 or 302 to 200).
                // Match on network success + non-4xx/5xx, not a JSON `ok` flag
                // that the RedirectResponse doesn't set.
                if (!resp.ok) {
                    // 422 comes back as JSON with { message, errors: {…} }
                    let msg = `Save failed (HTTP ${resp.status}).`;
                    try {
                        const data = await resp.json();
                        msg = data?.message
                            || (data?.errors ? Object.values(data.errors).flat().join(' • ') : msg);
                    } catch (_) { /* HTML body — keep the HTTP-status fallback */ }
                    flash('error', msg);
                    if (saveBtn) saveBtn.disabled = false;
                    return;
                }
                flash('success', editing ? 'Webhook updated.' : 'Webhook saved.');
                window.location.href = '{{ url('/webhooks') }}';
            } catch (err) {
                flash('error', 'Save failed: ' + (err?.message || 'network error'));
                if (saveBtn) saveBtn.disabled = false;
            }
        });
    })();
    </script>

</x-layouts.user>
