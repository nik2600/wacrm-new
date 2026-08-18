<x-layouts.user :title="__('New product')" nav-key="connect" page="user-store-products-create">
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
            @include('user.store._sidebar', ['current' => 'products', 'cfg' => $cfg, 'sf' => $sf])
            <section class="space-y-5 min-w-0">
                <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500">
                    <a href="{{ route('user.store.products.index') }}" class="hover:text-wa-deep">{{ __('Products') }}</a>
                    / New
                </div>
                <h1 class="font-serif text-[26px] sm:text-[34px] tracking-[-0.02em] leading-tight">{{ __('Add a product') }}</h1>
                @include('user.store.products._form')
            </section>
        </div>
    </main>
</x-layouts.user>
