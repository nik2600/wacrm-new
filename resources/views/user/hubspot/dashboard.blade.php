<x-layouts.user :title="__('HubSpot')" nav-key="more" page="user-hubspot-dashboard">

    @php $isConnected = $integration && $integration->isConnected(); @endphp

    <main class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-7">
        <div class="grid grid-cols-1 lg:grid-cols-[260px_1fr] gap-6">

            {{-- Left rail --}}
            <aside class="space-y-3">
                <div class="border border-paper-200 rounded-2xl bg-paper-0 p-4 shadow-card">
                    <div class="w-12 h-12 rounded-xl mb-3 grid place-items-center" style="background:#FFE4D6">
                        <svg viewBox="0 0 24 24" class="w-7 h-7" fill="#FF7A59">
                            <circle cx="12" cy="6" r="2.5" />
                            <circle cx="6" cy="14" r="2.5" />
                            <circle cx="18" cy="14" r="2.5" />
                            <path d="M12 8.5v3M9 13l3-1.5 3 1.5" stroke="#FF7A59" stroke-width="1.5" fill="none" />
                        </svg>
                    </div>
                    <div class="font-serif text-[18px] leading-tight">{{ __('HubSpot CRM') }}</div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mt-1">
                        {{ __('Integration') }}</div>
                    <div
                        class="mt-3 inline-flex items-center gap-1.5 px-2 py-1 rounded-full text-[10px] font-mono {{ $isConnected ? 'bg-wa-mint text-wa-deep border border-wa-green/40' : 'bg-paper-50 text-ink-700 border border-paper-200' }}">
                        <span
                            class="w-1.5 h-1.5 rounded-full {{ $isConnected ? 'bg-wa-green' : 'bg-paper-200' }}"></span>
                        {{ $isConnected ? __('Connected') : __('Not connected') }}
                    </div>
                </div>

                <div class="border border-paper-200 rounded-2xl bg-paper-0 p-4 shadow-card text-[12px] text-ink-700">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-2">
                        {{ __('What this does') }}</div>
                    @php
                        $whatItDoes = [
                            __(
                                'Every new WhatsApp order creates a HubSpot contact (deduped by email) and an associated deal.',
                            ),
                            __(
                                'When the order status changes, the same deal advances its stage — paid/shipped close it won.',
                            ),
                            __(
                                'Paid customers are tagged with the customer lifecycle stage for HubSpot revenue reports.',
                            ),
                        ];
                    @endphp
                    <ul class="space-y-1.5">
                        @foreach ($whatItDoes as $item)
                            <li class="flex items-start gap-2">
                                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 mt-0.5 text-wa-deep shrink-0" fill="none"
                                    stroke="currentColor" stroke-width="2">
                                    <path d="m3.5 8.5 3 3 6-7" />
                                </svg>
                                <span>{{ $item }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </aside>

            {{-- Main --}}
            <section class="space-y-5">
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                        <a href="{{ url('/integrations') }}" class="hover:text-wa-deep">{{ __('Integrations') }}</a>
                        <span class="mx-1.5 text-ink-500/60">/</span>
                        <span>{{ __('HubSpot') }}</span>
                    </div>
                    @if ($isConnected)
                        <h1 class="font-serif font-normal tracking-tight text-[32px] sm:text-[38px] lg:text-[44px] leading-none">
                            {{ $integration->portal_name ?: __('HubSpot') }} <span
                                class="italic text-wa-deep">{{ __('portal') }}</span></h1>
                        <p class="text-[13px] text-ink-600 mt-2">{{ __('Portal ID:') }} <span
                                class="font-mono">{{ $integration->portal_id ?: '—' }}</span> · {{ __('Connected') }}
                            {{ $integration->connected_at?->diffForHumans() ?? '—' }}</p>
                    @else
                        <h1 class="font-serif font-normal tracking-tight text-[32px] sm:text-[38px] lg:text-[44px] leading-none">{{ __('Connect') }}
                            <span class="italic text-wa-deep">{{ __('HubSpot CRM') }}</span></h1>
                        <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">
                            {{ __('Push contacts + deals into HubSpot whenever a :app conversation triggers an interesting event — new chat, order placed, SKU of interest mentioned.', ['app' => brand_name()]) }}
                        </p>
                    @endif
                </div>

                @if (session('success'))
                    <div
                        class="bg-wa-mint border border-wa-green/30 rounded-lg px-4 py-2 text-[12.5px] text-wa-deep font-mono">
                        {{ session('success') }}</div>
                @endif
                @if (session('error'))
                    <div
                        class="bg-accent-coral/10 border border-accent-coral/40 rounded-lg px-4 py-2 text-[12.5px] text-[#A1431F]">
                        {{ session('error') }}</div>
                @endif

                @if (!$isConnected)
                    @if (!$appEnabled)
                        <div
                            class="bg-paper-0 border border-paper-200 rounded-2xl p-6 shadow-card flex items-start gap-5">
                            <div class="w-12 h-12 rounded-xl bg-accent-amber/20 grid place-items-center shrink-0">
                                <svg viewBox="0 0 24 24" class="w-6 h-6 text-accent-amber" fill="none"
                                    stroke="currentColor" stroke-width="1.6">
                                    <path d="M12 9v3M12 16h.01" />
                                    <circle cx="12" cy="12" r="9" />
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="font-serif text-[22px] leading-tight">
                                    {{ __("HubSpot isn't configured yet") }}</div>
                                <p class="text-[12.5px] text-ink-600 mt-1.5 max-w-2xl">
                                    An admin needs to register a HubSpot public app at <span
                                        class="font-mono">{{ __('developers.hubspot.com') }}</span> and paste the
                                    Client ID + Client Secret at <span
                                        class="font-mono text-wa-deep">/admin/settings/hubspot</span>.
                                </p>
                            </div>
                        </div>
                    @else
                        <div class="bg-paper-0 border border-paper-200 rounded-2xl p-6 shadow-card">
                            <h2 class="font-serif text-[22px] leading-tight mb-3">
                                {{ __('Connect your HubSpot portal') }}</h2>
                            <p class="text-[12.5px] text-ink-600 mb-4">
                                {{ __("You'll be redirected to HubSpot to approve the scopes:") }}
                                <span
                                    class="font-mono text-[11.5px] text-ink-700 block mt-1">{{ \App\Models\SystemSetting::get('hubspot_scopes', \App\Services\Hubspot\HubspotService::DEFAULT_SCOPES) }}</span>
                            </p>
                            <form method="POST" action="{{ url('/hubspot/connect') }}">
                                @csrf
                                <button type="submit"
                                    class="px-5 py-2.5 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12.5px] font-semibold inline-flex items-center gap-2">
                                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                        stroke-width="1.7">
                                        <path d="M3 8h10M9 4l4 4-4 4" />
                                    </svg>
                                    {{ __('Connect HubSpot') }}
                                </button>
                            </form>
                        </div>
                    @endif
                @else
                    {{-- Connected — live sync KPIs (from the activity log, no hardcoded numbers) --}}
                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                        <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                            <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">
                                {{ __('Deals created') }}</div>
                            <div class="font-serif text-[30px] leading-none mt-1.5 tabular-nums">
                                {{ number_format($stats['created'] ?? 0) }}</div>
                        </div>
                        <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                            <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">
                                {{ __('Stage updates') }}</div>
                            <div class="font-serif text-[30px] leading-none mt-1.5 tabular-nums">
                                {{ number_format($stats['updated'] ?? 0) }}</div>
                        </div>
                        <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                            <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">
                                {{ __('Failed') }}</div>
                            <div
                                class="font-serif text-[30px] leading-none mt-1.5 tabular-nums {{ ($stats['failed'] ?? 0) > 0 ? 'text-accent-coral' : '' }}">
                                {{ number_format($stats['failed'] ?? 0) }}</div>
                        </div>
                        <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                            <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">
                                {{ __('Last sync') }}</div>
                            <div class="text-[13px] font-medium mt-2.5">
                                {{ !empty($stats['last']) ? \Illuminate\Support\Carbon::parse($stats['last'])->diffForHumans() : __('Never') }}
                            </div>
                        </div>
                    </div>

                    {{-- Connected — activity log --}}
                    <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                        <div class="px-5 py-4 border-b border-paper-200 flex items-center justify-between">
                            <h2 class="font-serif text-[22px] leading-tight">{{ __('Recent activity') }}</h2>
                            <form method="POST" action="{{ url('/hubspot/' . $integration->id . '/disconnect') }}"
                                onsubmit="return confirm('Disconnect HubSpot?')">
                                @csrf
                                <button type="submit"
                                    class="px-3 py-1.5 rounded-full border border-accent-coral/40 text-accent-coral hover:bg-accent-coral/10 text-[11.5px] font-semibold">{{ __('Disconnect') }}</button>
                            </form>
                        </div>
                        <div class="overflow-x-auto">
                        <table class="w-full text-[12.5px]">
                            <thead class="bg-paper-50 text-left font-mono text-[10.5px] uppercase text-ink-500">
                                <tr>
                                    <th class="px-4 py-2.5">{{ __('Event') }}</th>
                                    <th class="px-4 py-2.5">{{ __('Object ID') }}</th>
                                    <th class="px-4 py-2.5">{{ __('Status') }}</th>
                                    <th class="px-4 py-2.5 text-right">{{ __('When') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-paper-100">
                                @forelse ($recentLogs as $log)
                                    @php $statusCss = $log->status === 'sent' ? 'bg-wa-green/15 text-wa-deep' : 'bg-accent-coral/15 text-accent-coral'; @endphp
                                    <tr class="hover:bg-paper-50">
                                        <td class="px-4 py-2.5 font-mono">{{ $log->event_type }}</td>
                                        <td class="px-4 py-2.5 font-mono text-[11.5px] text-ink-700">
                                            {{ $log->object_id ?: '—' }}</td>
                                        <td class="px-4 py-2.5"><span
                                                class="font-mono text-[10px] uppercase px-2 py-0.5 rounded-full {{ $statusCss }}">{{ $log->status }}</span>
                                        </td>
                                        <td class="px-4 py-2.5 text-right font-mono text-[11px] text-ink-500">
                                            {{ $log->created_at?->diffForHumans() ?? '—' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-4 py-8 text-center text-[12px] text-ink-500">
                                            {{ __('No HubSpot sync events yet. A contact + deal is pushed automatically the next time an order is placed, and the deal advances as its status changes.') }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                        </div>
                    </div>
                @endif
            </section>
        </div>
    </main>

</x-layouts.user>
