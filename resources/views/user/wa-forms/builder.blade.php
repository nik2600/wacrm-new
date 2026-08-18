@php
    $f = $form;
    $payload = [
        'id' => $f?->id,
        'title' => $f?->title ?? '',
        'purpose' => $f?->purpose ?? '',
        'audience_type' => $f?->audience_type ?? 'lead_capture',
        'submission_cap' => (int) ($f?->submission_cap ?? 0),
        'cap_reached_note' => $f?->cap_reached_note ?? 'You have already submitted this form.',
        'send_button_label' => $f?->send_button_label ?? 'Send',
        'thank_you_note' => $f?->thank_you_note ?? 'Thanks — we got your details!',
        'status' => $f?->status ?? 'draft',
        'definition' => $f?->definition_json ?: [
            'screens' => [
                [
                    'id' => 'screen_1',
                    'label' => 'Step 1',
                    'fields' => [
                        ['id' => 'fld_intro', 'kind' => 'heading', 'label' => 'Tell us about you'],
                        [
                            'id' => 'fld_name',
                            'kind' => 'text',
                            'label' => 'Your name',
                            'required' => true,
                            'hint' => '',
                        ],
                        [
                            'id' => 'fld_email',
                            'kind' => 'email',
                            'label' => 'Email address',
                            'required' => true,
                            'hint' => '',
                        ],
                    ],
                ],
            ],
        ],
    ];
@endphp

<x-layouts.user :title="$mode === 'edit' ? __('Edit form') : __('Build a form')" nav-key="more" page="user-wa-forms-builder">

    {{-- Sticky top bar — matches /wa-campaigns/create pattern --}}
    <div class="border-b border-paper-200 bg-paper-0 sticky top-0 z-20">
        <div class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-3 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3 min-w-0">
                <a href="{{ url('/wa-forms') }}"
                    class="w-8 h-8 rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50 flex items-center justify-center"
                    title="{{ __('Back to forms') }}">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6">
                        <path d="M10 4l-4 4 4 4" />
                    </svg>
                </a>
                <div class="min-w-0">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">Forms /
                        {{ $mode === 'edit' ? 'Edit' : 'New' }}</div>
                    <div class="font-serif text-[20px] leading-tight truncate">
                        {{ $mode === 'edit' ? 'Edit a' : 'Build a' }} <span
                            class="italic text-wa-deep">{{ __('WhatsApp form') }}</span></div>
                </div>
            </div>
            <div class="flex items-center gap-2 flex-wrap justify-end">
                <span data-status-badge
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium bg-paper-50 text-ink-700 font-mono">{{ __('Draft / unsaved') }}</span>
                <button type="button" data-save-btn
                    class="px-3.5 py-1.5 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('Save draft') }}</button>
                <button type="button" data-publish-btn
                    class="px-3.5 py-1.5 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold inline-flex items-center gap-1.5">
                    <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M2 8l5 5 7-9" />
                    </svg>
                    Save &amp; publish
                </button>
            </div>
        </div>
    </div>

    <section class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-5" data-builder-state>
        <div class="grid grid-cols-1 lg:grid-cols-[260px_1fr_360px] gap-5 items-start">

            {{-- ── Left rail — component drawer (vertical, NOT competitor's horizontal pill row) --}}
            <aside class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card p-3 sticky top-[68px]">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 px-2 pt-1 pb-2">
                    {{ __('Drop a field') }}</div>

                @foreach ([['group' => 'Display', 'kinds' => [['k' => 'heading', 'l' => 'Heading', 'icon' => 'M3 3h10v3H3zM3 9h10M3 13h7']]], ['group' => 'Text inputs', 'kinds' => [['k' => 'text', 'l' => 'Short text', 'icon' => 'M2 5h12v6H2z'], ['k' => 'long_text', 'l' => 'Paragraph', 'icon' => 'M2 3h12v3H2zM2 7h12v3H2zM2 11h12v3H2z'], ['k' => 'email', 'l' => 'Email', 'icon' => 'M2 4h12v8H2zM2 4l6 4 6-4'], ['k' => 'phone', 'l' => 'Phone', 'icon' => 'M3 4a2 2 0 0 1 2-2h2l1.5 3-1.5 1a8 8 0 0 0 4 4l1-1.5 3 1.5v2a2 2 0 0 1-2 2A11 11 0 0 1 3 4z'], ['k' => 'number', 'l' => 'Number', 'icon' => 'M6 3l-2 10M12 3l-2 10M3 6h10M3 11h10']]], ['group' => 'Choice inputs', 'kinds' => [['k' => 'dropdown', 'l' => 'Dropdown', 'icon' => 'M3 5h10l-5 6z'], ['k' => 'choice', 'l' => 'Single pick', 'icon' => 'M4 8a4 4 0 1 0 8 0 4 4 0 0 0-8 0M8 6v4M6 8h4'], ['k' => 'multi', 'l' => 'Multi pick', 'icon' => 'M3 3h4v4H3zM9 9h4v4H9zM3 11l1 1 2-2'], ['k' => 'date', 'l' => 'Date', 'icon' => 'M3 4h10v9H3zM3 6h10M5 2v3M11 2v3']]]] as $cat)
                    <div class="mt-2">
                        <div class="font-mono text-[9.5px] uppercase tracking-[0.14em] text-ink-500 px-2 mb-1">
                            {{ $cat['group'] }}</div>
                        @foreach ($cat['kinds'] as $kind)
                            <button type="button" data-add-kind="{{ $kind['k'] }}"
                                class="w-full flex items-center gap-2 px-2 py-2 rounded-lg hover:bg-paper-50 text-left text-[12.5px]">
                                <span
                                    class="w-7 h-7 rounded-md bg-paper-50 text-wa-deep grid place-items-center shrink-0">
                                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                        stroke-width="1.6">
                                        <path d="{{ $kind['icon'] }}" />
                                    </svg>
                                </span>
                                <span class="font-medium text-ink-800">{{ $kind['l'] }}</span>
                            </button>
                        @endforeach
                    </div>
                @endforeach

                <div class="mt-3 pt-3 border-t border-paper-200">
                    <button type="button" data-add-screen
                        class="w-full px-3 py-2 rounded-lg border border-dashed border-paper-300 hover:border-wa-deep hover:bg-paper-50 text-[12px] font-semibold text-wa-deep">+
                        Add a screen (multi-step)</button>
                </div>
            </aside>

            {{-- ── Center — meta panel + canvas (NOT competitor's separate cards) --}}
            <div class="space-y-4">
                <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card p-5">
                    <div class="flex items-center gap-2 mb-3">
                        <svg viewBox="0 0 16 16" class="w-4 h-4 text-wa-deep" fill="none" stroke="currentColor"
                            stroke-width="1.7">
                            <rect x="3" y="2" width="10" height="12" rx="1.5" />
                            <path d="M5 5h6M5 8h6" />
                        </svg>
                        <h2 class="font-serif text-[16px]">{{ __('Form basics') }}</h2>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-[1.6fr_1fr] gap-3 mb-3">
                        <label class="block">
                            <span class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Form title') }}
                                <span class="text-accent-coral">*</span></span>
                            <input data-wf="title" type="text" maxlength="140"
                                placeholder="{{ __('e.g. Demo signup, Class booking, NPS survey') }}"
                                class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                            <span
                                class="text-[10.5px] text-ink-500 mt-1 block">{{ __('Operator-facing label — not shown to the customer.') }}</span>
                        </label>
                        <label class="block">
                            <span
                                class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Audience type') }}</span>
                            <select data-wf="audience_type"
                                class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[13px]">
                                <option value="lead_capture">{{ __('Lead capture') }}</option>
                                <option value="survey">{{ __('Survey') }}</option>
                                <option value="appointment">{{ __('Appointment') }}</option>
                                <option value="feedback">{{ __('Feedback') }}</option>
                                <option value="onboarding">{{ __('Onboarding') }}</option>
                                <option value="support">{{ __('Support') }}</option>
                                <option value="other">{{ __('Other') }}</option>
                            </select>
                        </label>
                    </div>
                    <label class="block mb-3">
                        <span class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Purpose') }}</span>
                        <textarea data-wf="purpose" rows="2" maxlength="2000"
                            placeholder="{{ __('Why does this form exist? Helps your team find it later.') }}"
                            class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"></textarea>
                    </label>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <label class="block">
                            <span
                                class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Submission cap per contact') }}</span>
                            <input data-wf="submission_cap" type="number" min="0" placeholder="0 = unlimited"
                                class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[13px]" />
                        </label>
                        <label class="block col-span-2">
                            <span
                                class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Cap reached note') }}</span>
                            <input data-wf="cap_reached_note" type="text" maxlength="500"
                                class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[13px]" />
                        </label>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-3">
                        <label class="block">
                            <span
                                class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Send button label') }}</span>
                            <input data-wf="send_button_label" type="text" maxlength="40"
                                class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[13px]" />
                        </label>
                        <label class="block">
                            <span
                                class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Thank-you note') }}</span>
                            <input data-wf="thank_you_note" type="text" maxlength="500"
                                class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[13px]" />
                        </label>
                    </div>
                </div>

                <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card">
                    <div class="px-5 py-3 border-b border-paper-200 flex items-center gap-2">
                        <svg viewBox="0 0 16 16" class="w-4 h-4 text-wa-deep" fill="none" stroke="currentColor"
                            stroke-width="1.7">
                            <path d="M3 4h10M3 8h10M3 12h6" />
                        </svg>
                        <h2 class="font-serif text-[16px] flex-1">{{ __('Form designer') }}</h2>
                        <span class="font-mono text-[10.5px] text-ink-500" data-fields-count>0 fields</span>
                    </div>
                    <div data-screens class="p-4 space-y-4"></div>
                </div>

                <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card p-4 text-[12px] text-ink-700">
                    <div class="font-semibold mb-1">{{ __('After submit') }}</div>
                    <p class="text-ink-500">
                        {{ __('Once the form returns, the conversation continues — the answers land in') }} <code
                            class="font-mono">@{{ form.field_id }}</code> variables your flow can use, and the
                        submission shows in the inbox timeline. Tie this form to a flow's <span
                            class="font-mono">{{ __('WhatsApp form') }}</span> node to chain follow-up automation.</p>
                </div>
            </div>

            {{-- ── Right rail — WhatsApp chat preview (NOT a full phone mock) --}}
            <aside class="space-y-4 sticky top-[68px]">
                <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                    <div class="px-4 py-3 border-b border-paper-200 bg-paper-50/40 flex items-center justify-between">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Live preview') }}</div>
                        <span class="text-[10px] font-mono text-ink-500">{{ __('how customer sees it') }}</span>
                    </div>
                    {{-- Render as a WhatsApp bubble preview — much smaller than competitor's full phone frame --}}
                    <div class="p-4 bg-[#E5DDD5] min-h-[360px]"
                        style="background-image: linear-gradient(135deg, #E5DDD5 25%, #DDD5CB 25%, #DDD5CB 50%, #E5DDD5 50%, #E5DDD5 75%, #DDD5CB 75%, #DDD5CB 100%); background-size: 16px 16px;">
                        <div class="bg-paper-0 rounded-xl rounded-bl-[4px] shadow-soft p-3 max-w-[280px]">
                            <div class="text-[12.5px] text-ink-900 mb-2" data-preview-greet>
                                {{ __('Hi! Please click below to fill out our form.') }}</div>
                            <div class="border-t border-paper-200 pt-2 text-center">
                                <span class="font-mono text-[10.5px] text-wa-deep font-semibold">▣ <span
                                        data-preview-open>{{ __('Open form') }}</span></span>
                            </div>
                            <div class="text-right mt-1 text-[9.5px] text-ink-500 font-mono">12:43</div>
                        </div>
                        <div class="bg-paper-0 rounded-xl mt-3 shadow-soft p-3 max-w-[280px]">
                            <div class="font-semibold text-[12.5px] mb-2" data-preview-screen-label>
                                {{ __('Step 1') }}</div>
                            <div class="space-y-2" data-preview-fields></div>
                            <button
                                class="mt-3 w-full px-3 py-2 rounded-lg bg-wa-deep text-paper-0 text-[12px] font-semibold"
                                data-preview-button>{{ __('Send') }}</button>
                        </div>
                    </div>
                </div>

                <div
                    class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card p-4 text-[12px] text-ink-700 leading-relaxed">
                    <div class="font-semibold text-ink-900 mb-1.5">{{ __('Publish checklist') }}</div>
                    <ul class="space-y-1 list-disc pl-4">
                        <li>{{ __('WABA workspace connected with calling/form scopes') }}</li>
                        <li>{{ __('Form has at least one input field') }}</li>
                        <li>{{ __('Each field has a unique') }} <code class="font-mono">id</code></li>
                        <li>{{ __('Multi-step? Last screen is marked terminal automatically') }}</li>
                    </ul>
                </div>
            </aside>
        </div>
    </section>

    <script>
        window.WA_FORM_PAYLOAD = @json($payload);
    </script>

</x-layouts.user>
