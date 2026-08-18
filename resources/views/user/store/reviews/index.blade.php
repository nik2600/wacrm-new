<x-layouts.user :title="__('Reviews')" nav-key="connect" page="user-store-reviews-index">
    @php
        $u = auth()->user();
        $cfg = $u?->current_workspace_id
            ? \App\Models\WaProviderConfig::query()->forWorkspace($u->current_workspace_id)->first()
            : null;
        $sf = $u?->current_workspace_id
            ? \App\Models\WaStorefront::where('workspace_id', $u->current_workspace_id)->first()
            : null;
    @endphp
    <main class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-7">
        <div class="grid grid-cols-1 lg:grid-cols-[260px_1fr] gap-6">
            @include('user.store._sidebar', ['current' => 'reviews', 'cfg' => $cfg, 'sf' => $sf])

            <section class="space-y-5">
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500">
                        {{ __('Store / Reviews') }}</div>
                    <h1 class="font-serif text-[26px] sm:text-[30px] lg:text-[34px] leading-tight tracking-[-0.02em]">{{ __('Product reviews') }}</h1>
                    <p class="text-[13px] text-ink-600 mt-1">
                        {{ __('Approve customer reviews to show them on the storefront product page.') }}</p>
                </div>

                @if (session('status'))
                    <div
                        class="bg-wa-mint border border-wa-green/30 rounded-lg px-4 py-2 text-[12.5px] text-wa-deep font-mono">
                        {{ session('status') }}</div>
                @endif

                <div class="flex items-center gap-1 border-b border-paper-200 overflow-x-auto">
                    @foreach (['pending' => __('Pending'), 'approved' => __('Approved'), 'rejected' => __('Rejected')] as $k => $label)
                        <a href="{{ route('user.store.reviews.index', ['status' => $k]) }}"
                            class="relative px-4 py-2.5 text-[12.5px] font-medium whitespace-nowrap shrink-0 {{ $status === $k ? 'text-wa-deep' : 'text-ink-700 hover:text-wa-deep' }}">
                            {{ $label }} <span
                                class="text-[10px] font-mono opacity-70">{{ $counts[$k] }}</span>
                            @if ($status === $k)
                                <span class="absolute left-3 right-3 -bottom-px h-0.5 bg-wa-deep rounded-full"></span>
                            @endif
                        </a>
                    @endforeach
                </div>

                <div class="space-y-3">
                    @forelse ($reviews as $r)
                        <div
                            class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card flex items-start gap-4 flex-wrap">
                            <div class="flex-1 min-w-[220px]">
                                <div class="flex items-center gap-2">
                                    <span class="text-accent-amber text-[13px]">{{ str_repeat('★', $r->rating) }}<span
                                            class="text-paper-200">{{ str_repeat('★', 5 - $r->rating) }}</span></span>
                                    <span
                                        class="font-semibold text-[13px]">{{ $r->customer_name ?: __('Anonymous') }}</span>
                                </div>
                                <div class="text-[11px] text-ink-500 mt-0.5">{{ __('on') }} <span
                                        class="font-medium">{{ $r->product?->name ?? '#' . $r->product_id }}</span> ·
                                    {{ $r->created_at?->diffForHumans() }}</div>
                                @if ($r->body)
                                    <p class="text-[12.5px] text-ink-700 mt-2">{{ $r->body }}</p>
                                @endif
                            </div>
                            <div class="flex items-center gap-2">
                                @if ($status !== 'approved')
                                    <form method="POST" action="{{ route('user.store.reviews.update', $r->id) }}">
                                        @csrf @method('PUT')<input type="hidden" name="status" value="approved">
                                        <button
                                            class="px-3 py-1.5 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[11.5px] font-semibold">{{ __('Approve') }}</button>
                                    </form>
                                @endif
                                @if ($status !== 'rejected')
                                    <form method="POST" action="{{ route('user.store.reviews.update', $r->id) }}">
                                        @csrf @method('PUT')<input type="hidden" name="status" value="rejected">
                                        <button
                                            class="px-3 py-1.5 rounded-full border border-paper-200 hover:bg-paper-50 text-[11.5px] font-medium">{{ __('Reject') }}</button>
                                    </form>
                                @endif
                                <form method="POST" action="{{ route('user.store.reviews.destroy', $r->id) }}"
                                    onsubmit="return confirm('{{ __('Delete this review?') }}')">@csrf
                                    @method('DELETE')
                                    <button
                                        class="px-3 py-1.5 rounded-full border border-paper-200 hover:bg-accent-coral/10 hover:border-accent-coral/40 text-accent-coral text-[11.5px] font-medium">{{ __('Delete') }}</button>
                                </form>
                            </div>
                        </div>
                    @empty
                        <div
                            class="bg-paper-0 border border-dashed border-paper-200 rounded-2xl p-8 text-center text-ink-500 text-[13px]">
                            {{ __('No :status reviews.', ['status' => $status]) }}</div>
                    @endforelse
                </div>
                @if ($reviews->hasPages())
                    <div>{{ $reviews->links() }}</div>
                @endif
            </section>
        </div>
    </main>
</x-layouts.user>
