@forelse ($rows as $a)
    @php
        $editPayload = [
            'id' => $a->id,
            'attribute_name' => $a->attribute_name,
            'attribute_key' => $a->attribute_key,
            'attribute_value' => $a->attribute_value,
            'description' => $a->description,
            'status' => (bool) $a->status,
        ];
    @endphp
    <tr class="hover:bg-paper-50/60" data-attr-row="{{ $a->id }}">
        <td class="px-4 py-3 font-medium">{{ $a->attribute_name }}</td>
        <td class="px-2 py-3 font-mono text-[12px] text-ink-700">{{ $a->attribute_key }}</td>
        <td class="px-2 py-3 text-ink-500 font-mono text-[12px]">{{ $a->attribute_value ?: '/' }}</td>
        <td class="px-2 py-3 text-ink-500 text-[12.5px]">{{ $a->description ?: '/' }}</td>
        <td class="px-2 py-3">
            @if ($a->status)
                <span
                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-wa-mint text-wa-deep text-[10.5px] font-mono"><span
                        class="w-1.5 h-1.5 rounded-full bg-wa-green"></span>Active</span>
            @else
                <span
                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-paper-50 text-ink-500 text-[10.5px] font-mono"><span
                        class="w-1.5 h-1.5 rounded-full bg-paper-200"></span>Off</span>
            @endif
        </td>
        <td class="px-4 py-3 text-right whitespace-nowrap">
            <button type="button" data-attr-edit="{{ $a->id }}"
                data-attr-edit-payload="{{ json_encode($editPayload, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) }}"
                class="w-7 h-7 rounded-full hover:bg-paper-100 inline-flex items-center justify-center"
                title="{{ __('Edit') }}">
                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6">
                    <path d="M11 2l3 3-8 8H3v-3l8-8z" />
                </svg>
            </button>
            <button type="button" data-attr-delete="{{ $a->id }}" data-name="{{ $a->attribute_name }}"
                class="w-7 h-7 rounded-full hover:bg-accent-coral/15 text-accent-coral inline-flex items-center justify-center"
                title="{{ __('Delete') }}">
                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6">
                    <path d="M3 4h10M6 4V2.5h4V4M5 4l1 9h4l1-9" />
                </svg>
            </button>
        </td>
    </tr>
@empty
    <tr>
        <td colspan="6" class="px-4 py-4">
            {{-- actionButtonAttrs (not actionHref) so the button opens the
                 New-attribute modal via the same data-attr-modal-open JS hook the
                 top-right button uses. Was actionHref="#new-attribute" — a bare
                 anchor that only set the URL hash and never opened the modal (the
                 reported "Create attribute button not working"). --}}
            @include('user.partials.empty-state', [
                'message' =>
                    'No attributes match the current filters. Create attributes to store reusable customer fields.',
                'resetHref' => url('/attributes'),
                'actionButtonAttrs' => 'data-attr-modal-open',
                'actionLabel' => 'Create attribute',
            ])
        </td>
    </tr>
@endforelse
