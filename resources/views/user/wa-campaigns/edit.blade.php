<x-layouts.user :title="__('Edit WhatsApp Campaign')" nav-key="wa-campaigns" page="user-wa-campaigns-edit">

    @if (session('status') || $errors->any())
        @push('scripts')
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    @if (session('status'))
                        window.toast(@json(session('status')), 'success');
                    @endif
                    @foreach ($errors->all() as $err)
                        window.toast(@json($err), 'error');
                    @endforeach
                });
            </script>
        @endpush
    @endif

    @php
        // Pre-fill helpers. The campaign's array/JSON casts hydrate the
// buttons + variable map; old() wins on a validation re-render so the
// operator never loses an in-progress edit.
$statusKey = strtolower((string) $campaign->status);
$curType = old('campaign_type', $campaign->campaign_type ?: 'text');
$curSchedule = old('schedule_type', $campaign->schedule_type ?: 'scheduled');

$buttons = old('custom_buttons', is_array($campaign->custom_buttons) ? $campaign->custom_buttons : []);
if (empty($buttons)) {
    $buttons = [['type' => 'visit_website', 'text' => '', 'url' => '', 'value' => '']];
}

// Existing attachment label — shown so the operator knows a file is
// already attached and only re-uploads to replace it.
$existingMedia = null;
if ($campaign->custom_image) {
    $existingMedia = ['type' => 'Image', 'path' => $campaign->custom_image];
} elseif ($campaign->custom_video) {
    $existingMedia = ['type' => 'Video', 'path' => $campaign->custom_video];
} elseif ($campaign->custom_document) {
    $existingMedia = ['type' => 'Document', 'path' => $campaign->custom_document];
}

$varMapRaw = old('custom_message_variable_map', $campaign->custom_variable_map ?: '{}');
$varMapJson = is_string($varMapRaw) ? $varMapRaw : json_encode($varMapRaw);
if (!$varMapJson) {
    $varMapJson = '{}';
        }

        $recipientIds = $recipientIds ?? [];
    @endphp

    <div class="border-b border-paper-200 bg-paper-0 sticky top-0 z-20">
        <div class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-3 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3 min-w-0">
                <a href="{{ route('user.wa-campaigns.detail', $campaign->id) }}"
                    class="w-8 h-8 rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50 flex items-center justify-center"
                    title="{{ __('Back to campaign') }}">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6">
                        <path d="M10 4l-4 4 4 4" />
                    </svg>
                </a>
                <div class="min-w-0">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                        {{ __('WA Campaigns / Edit') }} / #{{ $campaign->id }}</div>
                    <div class="font-serif text-[20px] leading-tight truncate">{{ __('Edit') }} <span
                            class="italic text-wa-deep">{{ $campaign->campaign_name }}</span></div>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <span
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium bg-paper-50 text-ink-700 font-mono">{{ ucfirst($statusKey ?: 'draft') }}</span>
                <button type="submit" form="campaignEditForm"
                    class="px-3.5 py-1.5 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold flex items-center gap-2">
                    <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M2 8l5 5 7-9" />
                    </svg>
                    {{ __('Save changes') }}
                </button>
            </div>
        </div>
    </div>

    <section class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-6">
        <form id="campaignEditForm" method="POST" action="{{ route('user.wa-campaigns.update', $campaign->id) }}"
            enctype="multipart/form-data" class="grid grid-cols-1 xl:grid-cols-[1fr_342px] gap-5 items-start">
            @csrf
            @method('PUT')

            <div class="bg-white border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                <div class="p-5 space-y-6">

                    {{-- ===== Setup ===== --}}
                    <div>
                        <div class="flex items-center gap-2.5 mb-4">
                            <span
                                class="w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">01</span>
                            <span
                                class="font-serif text-[18px] leading-none text-ink-900 flex-1">{{ __('Campaign setup') }}</span>
                            <span class="font-mono text-[10px] text-ink-500">{{ __('required') }}</span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">
                            <div>
                                <label
                                    class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-1.5"
                                    for="campaign-name">{{ __('Campaign name') }} <span
                                        class="text-accent-coral">*</span></label>
                                <input id="campaign-name" name="campaign_name" type="text"
                                    value="{{ old('campaign_name', $campaign->campaign_name) }}"
                                    placeholder="{{ __('e.g. May offer reactivation') }}"
                                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 leading-snug focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                    required>
                            </div>
                            <div>
                                <label
                                    class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-1.5"
                                    for="device">{{ __('Sender') }}</label>
                                @php
                                    // Re-derive the composite engine:id key for the campaign's
                                    // current sender so the unified picker pre-selects it.
                                    $currentEngine = $campaign->provider
                                        ?: \App\Services\WorkspaceEngine::for($campaign->workspace_id);
                                    $currentSenderKey = $campaign->device_id
                                        ? ($currentEngine . ':' . $campaign->device_id)
                                        : null;
                                @endphp
                                <x-sender-picker :senders="$senders" name="sender" id="device"
                                    :selected="old('sender', $currentSenderKey)"
                                    :placeholder="__('— Select a sender —')" />
                            </div>
                        </div>

                        <div>
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-2">{{ __('Campaign type') }}
                                <span class="text-accent-coral">*</span></label>
                            <select id="campaign-type" name="campaign_type"
                                class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                <option value="text" @selected($curType === 'text')>{{ __('Custom message') }}</option>
                                <option value="custom" @selected($curType === 'custom')>{{ __('Custom (rich)') }}</option>
                                <option value="button" @selected($curType === 'button')>{{ __('Buttons') }}</option>
                                <option value="media" @selected($curType === 'media')>{{ __('Media') }}</option>
                                <option value="template" @selected($curType === 'template')>{{ __('Template') }}</option>
                                <option value="flow" @selected($curType === 'flow')>{{ __('Flow') }}</option>
                            </select>
                        </div>
                    </div>

                    {{-- ===== Compose: custom body ===== --}}
                    <div class="border-t border-paper-200 pt-6">
                        <div class="flex items-center gap-2.5 mb-4">
                            <span
                                class="w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">02</span>
                            <span
                                class="font-serif text-[18px] leading-none text-ink-900 flex-1">{{ __('Compose') }}</span>
                            <span class="font-mono text-[10px] text-ink-500">{{ __('message content') }}</span>
                        </div>

                        <div class="mb-4">
                            <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block"
                                for="cc-header">{{ __('Header text') }} <span
                                    class="font-mono text-[10px] text-ink-500">{{ __('optional / max 60') }}</span></label>
                            <input id="cc-header" name="custom_header" type="text"
                                value="{{ old('custom_header', $campaign->custom_header) }}" maxlength="60"
                                placeholder="{{ __('e.g. May offer is live') }}"
                                class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                        </div>

                        <div class="mb-4">
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Body') }}</label>
                            <x-compose-textarea id="message-body" name="custom_message" :rows="8"
                                :maxlength="4096" :show-counter="true" :value="old('custom_message', $campaign->custom_message ?? '')" />
                            {{-- Seed the positional {{1}}→attribute map the compose component
 emits as custom_message_variable_map. The component hardcodes
 an empty "{}" default, so without this a saved map would be
 wiped on edit (stored bodies are already positional, so
 normalizeCustomMessage leaves them untouched + keeps this map). --}}
                            @push('scripts')
                                <script>
                                    document.addEventListener('DOMContentLoaded', function() {
                                        var f = document.querySelector('form#campaignEditForm input[name="custom_message_variable_map"]');
                                        if (f) {
                                            try {
                                                f.value = @json($varMapJson);
                                            } catch (e) {}
                                        }
                                    });
                                </script>
                            @endpush
                            <div class="text-[10.5px] text-ink-500 mt-1.5 flex flex-wrap items-center gap-1.5">
                                <span>{{ __('Markdown:') }}</span>
                                <code class="font-mono px-1.5 py-0.5 bg-paper-50 rounded text-[10px]">*bold*</code>
                                <code class="font-mono px-1.5 py-0.5 bg-paper-50 rounded text-[10px]">_italic_</code>
                                <code class="font-mono px-1.5 py-0.5 bg-paper-50 rounded text-[10px]">~strike~</code>
                            </div>
                        </div>

                        {{-- Attachment — only a NEW upload replaces the stored file. --}}
                        <div class="mb-4">
                            <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block"
                                for="cc-attach-type">{{ __('Attachment') }} <span
                                    class="font-mono text-[10px] text-ink-500">{{ __('optional') }}</span></label>
                            @if ($existingMedia)
                                <div
                                    class="mb-2 rounded-lg bg-paper-50 border border-paper-200 px-3 py-2 text-[12px] text-ink-700 flex items-center gap-2">
                                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 text-wa-deep" fill="none"
                                        stroke="currentColor" stroke-width="1.6">
                                        <path d="M4 2h6l3 3v9H4z" />
                                        <path d="M10 2v3h3" />
                                    </svg>
                                    <span>{{ __('Current attachment') }}: <b>{{ $existingMedia['type'] }}</b> · <span
                                            class="font-mono text-[11px] text-ink-500">{{ \Illuminate\Support\Str::afterLast($existingMedia['path'], '/') }}</span></span>
                                </div>
                            @endif
                            <div class="grid grid-cols-1 md:grid-cols-[160px_1fr] gap-3">
                                <select id="cc-attach-type" data-edit-attach-type
                                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                    <option value="none" @selected(!$existingMedia)>{{ __('Keep / none') }}
                                    </option>
                                    <option value="Image" @selected($existingMedia && $existingMedia['type'] === 'Image')>{{ __('Image') }}</option>
                                    <option value="Video" @selected($existingMedia && $existingMedia['type'] === 'Video')>{{ __('Video') }}</option>
                                    <option value="Document" @selected($existingMedia && $existingMedia['type'] === 'Document')>{{ __('Document') }}
                                    </option>
                                </select>
                                <div class="min-w-0">
                                    <div data-edit-media-pane="Image" class="hidden">
                                        <input name="custom_image" type="file" accept="image/*"
                                            class="w-full text-[12px] file:mr-3 file:px-3 file:py-1.5 file:rounded-full file:border-0 file:bg-wa-deep file:text-paper-0 file:text-[11px] file:font-semibold">
                                        <div class="text-[10.5px] text-ink-500 mt-1">
                                            {{ __('JPG or PNG, max 2 MB. Replaces the current attachment.') }}</div>
                                    </div>
                                    <div data-edit-media-pane="Video" class="hidden">
                                        <input name="custom_video" type="file" accept="video/*"
                                            class="w-full text-[12px] file:mr-3 file:px-3 file:py-1.5 file:rounded-full file:border-0 file:bg-wa-deep file:text-paper-0 file:text-[11px] file:font-semibold">
                                        <div class="text-[10.5px] text-ink-500 mt-1">
                                            {{ __('MP4, max 16 MB. Replaces the current attachment.') }}</div>
                                    </div>
                                    <div data-edit-media-pane="Document" class="hidden">
                                        <input name="custom_document" type="file" accept=".pdf,.doc,.docx"
                                            class="w-full text-[12px] file:mr-3 file:px-3 file:py-1.5 file:rounded-full file:border-0 file:bg-wa-deep file:text-paper-0 file:text-[11px] file:font-semibold">
                                        <div class="text-[10.5px] text-ink-500 mt-1">
                                            {{ __('PDF or DOC, max 16 MB. Replaces the current attachment.') }}</div>
                                    </div>
                                    <div data-edit-media-pane="none"
                                        class="text-[11px] text-ink-500 leading-[1.4] px-1">
                                        {{ __('No change — keep the existing attachment (if any).') }}</div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block"
                                for="footer">{{ __('Footer') }} <span
                                    class="font-mono text-[10px] text-ink-500">{{ __('optional / max 60') }}</span></label>
                            <input id="footer" name="custom_footer" type="text"
                                value="{{ old('custom_footer', $campaign->custom_footer) }}" maxlength="60"
                                placeholder="{{ __('e.g. Reply STOP to opt out') }}"
                                class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                        </div>

                        {{-- Buttons — each stored CTA row is pre-filled. JS adds/removes rows. --}}
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <label class="text-[11.5px] font-semibold text-ink-700">{{ __('Buttons') }} <span
                                        class="font-mono text-[10px] text-ink-500">{{ __('optional / up to 3') }}</span></label>
                            </div>
                            <div id="cc-btn-list" class="space-y-2">
                                @foreach ($buttons as $i => $btn)
                                    @php
                                        $bType = $btn['type'] ?? 'visit_website';
                                        $bText = $btn['text'] ?? '';
                                        $bUrl = $btn['url'] ?? '';
                                        $bVal = $btn['value'] ?? '';
                                    @endphp
                                    <div class="cc-btn-row grid grid-cols-[1fr_28px] sm:grid-cols-[130px_1fr_1fr_28px] gap-1.5 items-center"
                                        data-kind="cta">
                                        <select name="custom_buttons[{{ $i }}][type]"
                                            class="cc-cta-type w-full px-2 py-2 border border-paper-200 rounded-lg bg-white text-[12px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                            <option value="visit_website" @selected($bType === 'visit_website')>
                                                {{ __('Visit website') }}</option>
                                            <option value="copy_code" @selected($bType === 'copy_code')>
                                                {{ __('Copy code') }}</option>
                                            <option value="call_phone" @selected($bType === 'call_phone')>
                                                {{ __('Call phone') }}</option>
                                        </select>
                                        <input type="text" name="custom_buttons[{{ $i }}][text]"
                                            value="{{ $bText }}" maxlength="25"
                                            placeholder="{{ __('Button text') }}"
                                            class="cc-cta-text w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                        <input type="text" name="custom_buttons[{{ $i }}][url]"
                                            value="{{ $bUrl }}" placeholder="https://..."
                                            class="cc-cta-url w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10 {{ $bType === 'visit_website' ? '' : 'hidden' }}">
                                        <input type="text" name="custom_buttons[{{ $i }}][value]"
                                            value="{{ $bVal }}"
                                            placeholder="{{ $bType === 'call_phone' ? __('Number with country code') : 'PROMO50' }}"
                                            class="cc-cta-value w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10 {{ $bType === 'visit_website' ? 'hidden' : '' }}">
                                        <span
                                            class="w-7 h-7 rounded-[7px] inline-flex items-center justify-center text-ink-500 cursor-pointer transition hover:bg-[#FFEDE8] hover:text-accent-coral"
                                            data-cc-remove title="{{ __('Remove') }}"><svg viewBox="0 0 16 16"
                                                class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                                stroke-width="1.8">
                                                <path d="M4 4l8 8M12 4l-8 8" />
                                            </svg></span>
                                    </div>
                                @endforeach
                            </div>
                            <button type="button" id="cc-btn-add"
                                class="mt-2.5 inline-flex items-center gap-1.5 text-[12px] font-medium text-wa-deep hover:underline">
                                <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                                    stroke-width="2">
                                    <path d="M8 3v10M3 8h10" />
                                </svg>
                                <span>{{ __('Add CTA button') }}</span>
                            </button>
                        </div>

                        {{-- Template picker — only consumed when type=template. --}}
                        <div class="mt-4">
                            <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block"
                                for="template-only">{{ __('Template') }} <span
                                    class="font-mono text-[10px] text-ink-500">{{ __('used when type is Template') }}</span></label>
                            <select id="template-only" name="template_id"
                                class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                <option value="">{{ __('Select a template') }}</option>
                                @foreach ($templates as $t)
                                    @php
                                        $name = $t->template_name ?? ($t->name ?? 'Template #' . $t->id);
                                        $status = $t->status ?? 'pending';
                                        $isUsable =
                                            !($requiresApprovedTemplates ?? true) ||
                                            in_array($status, ['approved', 'public'], true);
                                    @endphp
                                    <option value="{{ $t->id }}" @disabled(!$isUsable)
                                        {{ (int) old('template_id', $campaign->template_id) === (int) $t->id ? 'selected' : '' }}>
                                        {{ $name }}@if (($t->provider ?? null) && method_exists($t, 'engineKey') && $t->engineKey() === 'waba') · {{ $t->provider->display_label ?: $t->provider->phone_number }}@endif / {{ $status }}</option>
                                @endforeach
                            </select>

                            {{-- Send-time mapping — pre-filled with whatever this
 campaign already had stored, so an edit shows the current
 override rather than blank boxes. --}}
                            <x-template-live-mapping :templates="$templates" target="#template-only"
                                :value="$campaign->template_overrides" />

                            {{-- Variant B's own mapping, pre-filled from what this
                                 campaign stored. Mirrors the create screen; see the
                                 migration note for why B needs its own blob. --}}
                            <div data-ab-b class="{{ $campaign->ab_testing ? '' : 'hidden' }} mt-4 pt-4 border-t border-dashed border-paper-200">
                                <div class="text-[11.5px] font-semibold text-ink-700 mb-1">
                                    {{ __('Fill in this send — Variant B') }}
                                </div>
                                <div class="text-[10.5px] text-ink-500 mb-2">
                                    {{ __('Variant B uses its own template, so it gets its own values. Leave blank to send Variant B with no overrides.') }}
                                </div>
                                <x-template-live-mapping :templates="$templates" target="#template_id_b"
                                    field="template_overrides_b" :value="$campaign->template_overrides_b" />
                            </div>
                        </div>
                    </div>

                    {{-- ===== Recipients ===== --}}
                    <div class="border-t border-paper-200 pt-6">
                        <div class="flex items-center gap-2.5 mb-4">
                            <span
                                class="w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">03</span>
                            <span
                                class="font-serif text-[18px] leading-none text-ink-900 flex-1">{{ __('Recipients') }}</span>
                            <span class="font-mono text-[10px] text-ink-500">{{ count($recipientIds) }}
                                {{ __('selected') }}</span>
                        </div>
                        <div
                            class="rounded-lg bg-wa-bubble/40 border border-paper-200 px-3 py-2 text-[12px] text-ink-700 mb-3">
                            {{ __('Leave the current selection untouched to keep the existing audience, or re-pick contacts below to replace it.') }}
                        </div>
                        @if ($contacts->isNotEmpty())
                            <div
                                class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2 max-h-[220px] overflow-y-auto border border-paper-200 rounded-lg p-2 bg-paper-50/40">
                                @foreach ($contacts as $contact)
                                    <label
                                        class="flex items-center gap-2 px-2 py-1 rounded hover:bg-white cursor-pointer">
                                        <input type="checkbox" name="recipients[]" value="{{ $contact->id }}"
                                            class="w-3.5 h-3.5 accent-wa-deep"
                                            {{ in_array((int) $contact->id, array_map('intval', old('recipients', $recipientIds)), true) ? 'checked' : '' }}>
                                        <span
                                            class="text-[12px] text-ink-800 truncate">{{ $contact->name ?: mask_phone($contact->mobile) }}</span>
                                    </label>
                                @endforeach
                            </div>
                        @else
                            <div
                                class="border border-dashed border-paper-200 rounded-lg p-4 text-center text-[12px] text-ink-500">
                                {{ __('No contacts in this workspace yet.') }}
                            </div>
                        @endif
                    </div>

                    {{-- ===== Schedule ===== --}}
                    <div class="border-t border-paper-200 pt-6">
                        <div class="flex items-center gap-2.5 mb-4">
                            <span
                                class="w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">04</span>
                            <span
                                class="font-serif text-[18px] leading-none text-ink-900 flex-1">{{ __('Schedule') }}</span>
                            <span class="font-mono text-[10px] text-ink-500">{{ __('queue') }}</span>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">
                            <div>
                                <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block"
                                    for="schedule-type">{{ __('Schedule type') }} <span
                                        class="text-accent-coral">*</span></label>
                                <select id="schedule-type" name="schedule_type"
                                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                    <option value="now" @selected($curSchedule === 'now')>{{ __('Send now') }}</option>
                                    <option value="scheduled" @selected($curSchedule === 'scheduled')>{{ __('Schedule later') }}
                                    </option>
                                    <option value="recurring" @selected($curSchedule === 'recurring')>{{ __('Recurring') }}
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block"
                                    for="timezone">{{ __('Timezone') }}</label>
                                <select id="timezone" name="timezone"
                                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                    @php
                                        $curTz = old('timezone', $campaign->timezone ?: 'Asia/Kolkata');
                                        try {
                                            $tzList = \DateTimeZone::listIdentifiers();
                                        } catch (\Throwable $e) {
                                            $tzList = [
                                                'UTC',
                                                'Asia/Kolkata',
                                                'Asia/Dubai',
                                                'Europe/London',
                                                'America/New_York',
                                            ];
                                        }
                                    @endphp
                                    @foreach ($tzList as $tz)
                                        <option value="{{ $tz }}" {{ $tz === $curTz ? 'selected' : '' }}>
                                            {{ $tz }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-4">
                            <div>
                                <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block"
                                    for="send-date">{{ __('Send date') }}</label>
                                <input id="send-date" name="send_date" type="date"
                                    value="{{ old('send_date', optional($campaign->send_date)->toDateString()) }}"
                                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                            </div>
                            <div>
                                <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block"
                                    for="send-time">{{ __('Send time') }}</label>
                                <input id="send-time" name="send_time" type="time"
                                    value="{{ old('send_time', $campaign->send_time ? \Illuminate\Support\Str::limit((string) $campaign->send_time, 5, '') : '') }}"
                                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                            </div>
                            <div>
                                <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block"
                                    for="repeat-interval">{{ __('Repeat') }} <span
                                        class="text-ink-400 font-normal">({{ __('recurring only') }})</span></label>
                                <select id="repeat-interval" name="repeat_interval"
                                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                    @php $curRepeat = old('repeat_interval', $campaign->repeat_interval ?: 'weekly'); @endphp
                                    <option value="weekly" @selected($curRepeat === 'weekly')>{{ __('Every week') }}
                                    </option>
                                    <option value="daily" @selected($curRepeat === 'daily')>{{ __('Every day') }}
                                    </option>
                                    <option value="monthly" @selected($curRepeat === 'monthly')>{{ __('Every month') }}
                                    </option>
                                </select>
                            </div>
                        </div>

                        {{-- Optional per-campaign END DATE (auto-stop). Blank = the
                             platform default auto-end. Prefilled in the campaign tz. --}}
                        @php
                            $expLocal = $campaign->expires_at
                                ? $campaign->expires_at->timezone($campaign->timezone ?: config('app.timezone', 'UTC'))->format('Y-m-d\TH:i')
                                : '';
                        @endphp
                        <div class="mb-4">
                            <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block" for="expires-at">
                                {{ __('Auto-end date') }} <span class="text-ink-400 font-normal">({{ __('optional') }})</span>
                            </label>
                            <input id="expires-at" name="expires_at" type="datetime-local"
                                value="{{ old('expires_at', $expLocal) }}"
                                class="w-full sm:max-w-[280px] px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                            <div class="text-[10.5px] text-ink-500 mt-1">
                                {{ __('Stop sending after this date & time (campaign timezone). Clear to use the platform default.') }}
                            </div>
                        </div>
                    </div>

                </div>

                <div class="px-5 py-4 border-t border-paper-200 bg-paper-50/40 flex items-center justify-between">
                    <a href="{{ route('user.wa-campaigns.detail', $campaign->id) }}"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-full border border-paper-200 bg-paper-0 hover:bg-white text-[12px] font-semibold text-ink-700">
                        {{ __('Cancel') }}
                    </a>
                    <button type="submit"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold">
                        <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <path d="M2 8l5 5 7-9" />
                        </svg>
                        {{ __('Save changes') }}
                    </button>
                </div>
            </div>

            <aside class="space-y-4">
                <div class="bg-white border border-paper-200 rounded-2xl shadow-card p-4 sticky top-[92px]">
                    <div class="font-mono text-[9.5px] uppercase tracking-[0.16em] text-ink-500 mb-3">
                        {{ __('Editing summary') }}</div>
                    <div class="space-y-2 text-[12px]">
                        <div class="flex items-center justify-between"><span
                                class="text-ink-500">{{ __('Status') }}</span><b>{{ ucfirst($statusKey ?: 'draft') }}</b>
                        </div>
                        <div class="flex items-center justify-between"><span
                                class="text-ink-500">{{ __('Type') }}</span><b>{{ ucfirst((string) $campaign->campaign_type) }}</b>
                        </div>
                        <div class="flex items-center justify-between"><span
                                class="text-ink-500">{{ __('Recipients') }}</span><b>{{ number_format(count($recipientIds)) }}</b>
                        </div>
                        <div class="flex items-center justify-between"><span
                                class="text-ink-500">{{ __('Schedule') }}</span><b>{{ ucfirst((string) ($campaign->schedule_type ?: 'scheduled')) }}</b>
                        </div>
                    </div>
                    <div
                        class="mt-4 rounded-lg bg-wa-bubble/40 border border-paper-200 px-3 py-2.5 text-[11.5px] text-ink-700 leading-[1.5]">
                        {{ __('Only draft, scheduled or paused campaigns can be edited. Changes apply on the next run.') }}
                    </div>
                </div>
            </aside>
        </form>
    </section>

</x-layouts.user>
