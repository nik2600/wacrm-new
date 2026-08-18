@php
    /** @var \Illuminate\Support\Collection $templates */
    $catColors = [
        // WhatsApp/Meta categories — what the library badge + tabs show.
        'marketing' => 'bg-accent-coral/15 text-[#A1431F]',
        'authentication' => 'bg-[#13478A]/10 text-[#13478A]',
        'utility' => 'bg-[#DFF1ED] text-wa-deep',
        // Legacy local verticals (fallback when no meta_category is set).
        'travel' => 'bg-wa-bubble text-wa-deep',
        'healthcare' => 'bg-[#DFF1ED] text-wa-deep',
        'education' => 'bg-[#F4E9C9] text-[#7B5A14]',
        'ecommerce' => 'bg-accent-coral/15 text-[#A1431F]',
        'festival' => 'bg-[#EFE5F5] text-[#5B3D8A]',
        'finance' => 'bg-[#13478A]/10 text-[#13478A]',
    ];
    $statusDot = [
        'approved' => 'bg-wa-green',
        'public' => 'bg-wa-green',
        'pending' => 'bg-accent-amber',
        'rejected' => 'bg-accent-coral',
    ];
@endphp

@forelse ($templates as $t)
    @php
        // Badge/filter use the WhatsApp category (meta_category); fall back to
        // the local vertical only when a template has no Meta category yet.
        $catKey = $t->meta_category ?: $t->category;
        $cls = $catColors[$catKey] ?? 'bg-paper-50 text-ink-700';
        $dot = $statusDot[$t->status] ?? 'bg-paper-300';
        // First 5 paragraphs of the body. Body is encrypted text;
        // we just split on \n\n so the card preview matches what
        // the operator typed.
        $bodyHtml = collect(explode("\n\n", (string) $t->template_body))
            ->take(5)
            ->map(fn($p) => '<p>' . nl2br(e($p)) . '</p>')
            ->implode('');
        // Which engine this template belongs to — shown as a chip on the card.
        $engKey = $t->engineKey();
        $engMeta =
            [
                'baileys' => [__('Unofficial'), 'bg-wa-mint text-wa-deep'],
                'waba' => [__('Meta WABA'), 'bg-wa-bubble text-wa-deep'],
                'twilio' => [__('Twilio'), 'bg-[#F22F46]/10 text-[#A12534]'],
                'instagram' => [__('Instagram'), 'bg-[#E1306C]/10 text-[#C13584]'],
            ][$engKey] ?? [__('Unofficial'), 'bg-paper-50 text-ink-700'];
        $isIg = $engKey === 'instagram';
    @endphp
    <div class="tpl-card bg-white border border-paper-200 rounded-[14px] p-4 transition flex flex-col hover:border-wa-deep hover:shadow-soft hover:-translate-y-px"
        data-template-row data-template-id="{{ $t->id }}" data-category="{{ $catKey }}"
        data-channel="{{ $engKey }}"
        data-status="{{ $t->status }}">
        <div class="flex items-start justify-between gap-2 mb-2">
            <span class="tpl-name text-[13px] font-semibold text-ink-900 break-words">{{ $t->template_name }}</span>
            <span
                class="tpl-cat shrink-0 text-[10px] font-medium px-2 py-0.5 rounded {{ $cls }}">{{ ucfirst($catKey) }}</span>
        </div>
        <div class="flex items-center gap-1.5 mb-2 text-[10.5px] font-mono text-ink-500 flex-wrap">
            <span
                class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full font-semibold {{ $engMeta[1] }}">
                @if ($isIg)
                    <svg viewBox="0 0 24 24" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="18" height="18" rx="5" /><circle cx="12" cy="12" r="4" /><circle cx="17.5" cy="6.5" r="1" fill="currentColor" stroke="none" />
                    </svg>
                @else
                    <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M2.6 11.2 2 14l2.9-.6A6 6 0 1 0 2.6 11.2Z" />
                    </svg>
                @endif{{ $engMeta[0] }}
            </span>
            <span class="inline-flex items-center gap-1.5"><span
                    class="w-1.5 h-1.5 rounded-full {{ $dot }}"></span>{{ $t->status_label }}</span>
            <span>·</span>
            <span>{{ ucfirst($t->template_type) }}</span>
            @if ($t->meta_category)
                <span>·</span>
                <span>{{ ucfirst($t->meta_category) }}</span>
            @endif
            @if ($engKey === 'waba' && $t->provider && $t->provider->id)
                {{-- Which WABA account this template belongs to — so 3-account
                     workspaces can tell their synced templates apart. --}}
                <span>·</span>
                <span class="inline-flex items-center gap-1 text-wa-deep font-semibold truncate max-w-[150px]"
                    title="{{ $t->provider->display_label ?: $t->provider->phone_number }}">
                    <svg viewBox="0 0 16 16" class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M5.5 2.5h5a1 1 0 0 1 1 1v9a1 1 0 0 1-1 1h-5a1 1 0 0 1-1-1v-9a1 1 0 0 1 1-1Zm1.5 10h2" />
                    </svg>{{ $t->provider->display_label ?: $t->provider->phone_number ?: 'WABA' }}
                </span>
            @endif
        </div>
        @php
            $btns = is_array($t->buttons) ? $t->buttons : [];
            $att  = strtolower((string) $t->attachment_type);
            $hasMedia = in_array($att, ['image', 'video', 'document', 'media', 'location'], true);
            // WhatsApp-style formatting for the preview bubble: *bold*, _italic_,
            // ~strike~; and highlight {{variables}} as pills. Escape first.
            $fmt = e((string) $t->template_body);
            $fmt = preg_replace('/\*(.+?)\*/s', '<strong>$1</strong>', $fmt);
            $fmt = preg_replace('/_(.+?)_/s', '<em>$1</em>', $fmt);
            $fmt = preg_replace('/~(.+?)~/s', '<del>$1</del>', $fmt);
            $fmt = preg_replace('/\{\{\s*([^{}]+?)\s*\}\}/', '<span class="inline-block px-1 rounded bg-wa-bubble text-wa-deep font-medium">{{$1}}</span>', $fmt);
            $fmt = nl2br($fmt);
        @endphp
        {{-- ===== WhatsApp message mockup ===== --}}
        <div class="tpl-preview flex-1 rounded-xl border border-paper-200 bg-paper-100 p-3 flex flex-col gap-1 overflow-hidden">
            <div class="self-start w-full max-w-[95%] bg-white rounded-lg rounded-tl-sm shadow-sm px-2.5 py-2">
                @if ($hasMedia)
                    <div class="mb-1.5 h-20 rounded-md bg-paper-100 border border-paper-200 grid place-items-center text-ink-400">
                        <svg viewBox="0 0 16 16" class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.4">
                            @if ($att === 'video')
                                <rect x="2" y="3.5" width="12" height="9" rx="1.5" /><path d="M6.5 6.5 10 8l-3.5 1.5z" fill="currentColor" stroke="none" />
                            @elseif ($att === 'document')
                                <path d="M4 1.5h5l3 3V14a.5.5 0 0 1-.5.5h-7A.5.5 0 0 1 4 14zM9 1.5V4.5h3M6 8h4M6 10.5h4" />
                            @elseif ($att === 'location')
                                <path d="M8 14s4.5-4 4.5-7A4.5 4.5 0 0 0 8 2.5 4.5 4.5 0 0 0 3.5 7c0 3 4.5 7 4.5 7z" /><circle cx="8" cy="7" r="1.5" />
                            @else
                                <rect x="2" y="3" width="12" height="10" rx="1.5" /><circle cx="6" cy="6.5" r="1.2" /><path d="m3 12 3-3 2.5 2 2-1.5L13 12" />
                            @endif
                        </svg>
                    </div>
                @endif
                @if (!empty($t->header) && !$hasMedia)
                    <div class="text-[12.5px] font-semibold text-ink-900 leading-snug mb-1 break-words">
                        {{ \Illuminate\Support\Str::limit($t->header, 90) }}</div>
                @endif
                <div class="tpl-body text-[12px] leading-[1.5] text-ink-800 break-words">{!! $fmt !!}</div>
                @if (!empty($t->footer))
                    <div class="text-[10.5px] text-ink-400 mt-1.5 break-words">
                        {{ \Illuminate\Support\Str::limit($t->footer, 120) }}</div>
                @endif
                <div class="text-[9px] text-ink-400 flex items-center justify-end gap-1 mt-1">
                    <span>10:30</span>
                    <svg viewBox="0 0 18 12" class="w-3.5 h-3 text-wa-deep" fill="none" stroke="currentColor" stroke-width="1.6">
                        <path d="M1 6.5 4 9.5 9.5 3" /><path d="M7.5 9.5 8.5 8.5M9 6.5 13.5 2" />
                    </svg>
                </div>
            </div>
            @if (count($btns))
                <div class="self-start w-full max-w-[95%] space-y-1 mt-0.5">
                    @foreach (array_slice($btns, 0, 3) as $b)
                        <div class="bg-white rounded-lg shadow-sm text-center text-[12px] text-wa-deep font-medium py-1.5 flex items-center justify-center gap-1.5 break-words">
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.5">
                                @if (($b['type'] ?? '') === 'URL' || !empty($b['url']))
                                    <path d="M6.5 9.5 9.5 6.5M7 4.5 8.5 3a2.5 2.5 0 0 1 3.5 3.5L10.5 8M9 11.5 7.5 13A2.5 2.5 0 0 1 4 9.5L5.5 8" />
                                @elseif (($b['type'] ?? '') === 'PHONE_NUMBER' || !empty($b['phone_number']))
                                    <path d="M4 2.5c0 5 4.5 9.5 9.5 9.5v-2l-2.5-1-1.5 1a7 7 0 0 1-3-3l1-1.5-1-2.5z" />
                                @else
                                    <path d="M13 8a5 5 0 1 1-1.5-3.5M13 3v2h-2" />
                                @endif
                                <span class="truncate">{{ $b['text'] ?? __('Button') }}</span>
                            </svg>
                        </div>
                    @endforeach
                    @if (count($btns) > 3)
                        <div class="text-[10px] font-mono text-ink-500 text-center">+{{ count($btns) - 3 }} {{ __('more') }}</div>
                    @endif
                </div>
            @endif
        </div>
        @if ($t->status === 'rejected' && $t->rejection_reason)
            <div
                class="mt-2 rounded-lg border border-accent-coral/30 bg-accent-coral/10 px-2.5 py-1.5 text-[11px] text-[#A1431F]">
                <b>Rejected:</b> {{ $t->rejection_reason }}
            </div>
        @endif
        <div class="mt-3 flex items-center gap-2">
            <a href="{{ route('user.templates.edit', $t->id) }}"
                class="flex-1 use-sample border border-dashed border-wa-deep text-wa-deep bg-transparent text-[11px] font-medium py-[7px] rounded-full transition hover:bg-wa-deep hover:text-paper-0 hover:border-solid text-center">Edit
                template</a>
            {{-- View = open the template detail / Meta review-status page
                 (user.templates.show → /templates/{id}). Was missing, so a
                 pending "In review" template had no way to check its status. --}}
            <a href="{{ route('user.templates.show', $t->id) }}"
                class="w-8 h-8 rounded-full border border-paper-200 bg-paper-0 hover:bg-wa-mint hover:border-wa-deep hover:text-wa-deep grid place-items-center"
                title="{{ __('View details & Meta review status') }}">
                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6">
                    <path d="M1.5 8s2.5-4.5 6.5-4.5S14.5 8 14.5 8 12 12.5 8 12.5 1.5 8 1.5 8z" />
                    <circle cx="8" cy="8" r="1.8" />
                </svg>
            </a>
            <button data-template-delete="{{ $t->id }}" data-name="{{ $t->template_name }}" type="button"
                class="w-8 h-8 rounded-full border border-paper-200 bg-paper-0 hover:bg-accent-coral/10 hover:border-accent-coral hover:text-accent-coral grid place-items-center"
                title="{{ __('Delete') }}">
                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6">
                    <path d="M3 4h10M5 4V2.5h6V4M4 4l1 10h6l1-10" />
                </svg>
            </button>
        </div>
    </div>
@empty
    {{-- "Create template" opens the WhatsApp/Instagram + format modal (same as
         the header button) instead of jumping straight to the create form, so the
         channel step is never skipped. actionButtonAttrs renders a <button> only
         when actionHref is absent — so we omit actionHref here. --}}
    @include('user.partials.empty-state', [
        'class' => 'col-span-full',
        'message' =>
            'No templates match the current filters. Try clearing filters or submit a new template for review.',
        'resetHref' => url('/templates'),
        'actionButtonAttrs' =>
            "onclick=\"document.getElementById('type-modal').classList.remove('hidden');var c=document.getElementById('tpl-step-channel');if(c){c.classList.remove('hidden');document.getElementById('tpl-step-format').classList.add('hidden');}\"",
        'actionLabel' => 'Create template',
    ])
@endforelse
