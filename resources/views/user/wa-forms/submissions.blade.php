<x-layouts.user :title="__('Form submissions')" nav-key="more" page="user-wa-forms-submissions">

    <main class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-7">

        {{-- Breadcrumb --}}
        <div class="font-mono text-[11px] uppercase tracking-[0.16em] text-ink-500 mb-1">
            <a href="{{ route('user.wa-forms.index') }}" class="hover:text-wa-deep">{{ __('WhatsApp Forms') }}</a>
            <span class="mx-1.5">/</span>{{ __('Submissions') }}
        </div>

        {{-- Hero + actions --}}
        <div class="flex flex-wrap items-end justify-between gap-3 mb-5">
            <div>
                <h1 class="font-serif text-[30px] leading-none">
                    {{ __('Responses to') }} <span class="italic text-wa-deep">{{ $form->title }}</span>
                </h1>
                <div class="font-mono text-[11px] text-ink-500 mt-2">
                    {{ $rows->total() }} {{ __('submission(s)') }}
                    @if ($form->meta_flow_id) · meta_flow_id {{ $form->meta_flow_id }} @endif
                </div>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('user.wa-forms.index') }}"
                    class="px-3 py-1.5 rounded-full border border-paper-200 text-[12px] font-semibold hover:bg-paper-50">{{ __('Back') }}</a>
                @if ($rows->total() > 0)
                    <a href="{{ route('user.wa-forms.submissions.export', $form->id) }}"
                        class="px-3 py-1.5 rounded-full bg-wa-deep text-paper-0 hover:bg-wa-teal text-[12px] font-semibold">{{ __('Export CSV') }}</a>
                @endif
            </div>
        </div>

        @if ($rows->isEmpty())
            <div class="border border-paper-200 rounded-2xl bg-paper-0 p-10 text-center shadow-card">
                <div class="font-serif text-[20px] mb-1">{{ __('No submissions yet') }}</div>
                <p class="text-[13px] text-ink-500 max-w-md mx-auto">
                    {{ __('When a customer completes this form in WhatsApp, their answers land here automatically.') }}
                </p>
            </div>
        @else
            <div class="border border-paper-200 rounded-2xl bg-paper-0 shadow-card overflow-x-auto">
                <table class="w-full text-[12.5px]">
                    <thead>
                        <tr class="border-b border-paper-200 text-left font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">
                            <th class="px-3 py-2.5 whitespace-nowrap">{{ __('Submitted') }}</th>
                            <th class="px-3 py-2.5 whitespace-nowrap">{{ __('Contact') }}</th>
                            <th class="px-3 py-2.5 whitespace-nowrap">{{ __('Phone') }}</th>
                            @foreach ($labels as $label)
                                <th class="px-3 py-2.5 whitespace-nowrap">{{ $label }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $s)
                            @php
                                $answers = is_array($s->answers_json) ? $s->answers_json : [];
                                $cName = $s->contact?->name ?: trim(($s->contact?->first_name ?? '') . ' ' . ($s->contact?->last_name ?? ''));
                                $phone = $s->caller_phone ?: ($s->contact?->mobile ?? '');
                            @endphp
                            <tr class="border-b border-paper-100 hover:bg-paper-50 align-top">
                                <td class="px-3 py-2.5 whitespace-nowrap font-mono text-[11px] text-ink-500">
                                    {{ optional($s->submitted_at)->format('d M Y · H:i') ?: '—' }}</td>
                                <td class="px-3 py-2.5 whitespace-nowrap">{{ $cName ?: '—' }}</td>
                                <td class="px-3 py-2.5 whitespace-nowrap font-mono text-[11px]">{{ $phone ?: '—' }}</td>
                                @foreach (array_keys($labels) as $fid)
                                    @php $v = $answers[$fid] ?? ''; $v = is_array($v) ? implode(', ', $v) : $v; @endphp
                                    <td class="px-3 py-2.5">{{ $v !== '' ? $v : '—' }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $rows->links() }}</div>
        @endif

    </main>
</x-layouts.user>
