<x-layouts.user :title="__('Flow Builder')" nav-key="flows" page="user-flows-builder" :hide-header="true">

    @php
        $flowId = isset($flow) && $flow ? $flow->id : null;
        $flowName = isset($flow) && $flow ? $flow->flow_name ?? 'New flow' : 'New flow';
        $flowJson = $flowJson ?? ['flowNodes' => [], 'flowEdges' => []];
        $isPublished = isset($flow) && $flow && $flow->is_published;
        $category = isset($flow) && $flow ? $flow->category ?? '' : '';
        $flowType = $flowType ?? (isset($flow) && $flow ? ($flow->flow_type ?: 'chat') : 'chat');
    @endphp

    <div id="root" data-flow-id="{{ $flowId }}" data-flow-name="{{ $flowName }}"
        data-flow-category="{{ $category }}" data-flow-published="{{ $isPublished ? '1' : '0' }}"
        data-flow-type="{{ $flowType }}"
        {{-- Whether the Instagram channel is offered at all. TRUE when either
             Instagram system is present in THIS workspace: the native addon
             extension (InstagramAccount) OR an Instaflow-bridge account
             connected via the one-click "Connect Instagram" button
             (WorkspaceIgAccount) — the latter is how most deployments run IG,
             and gating only on the addon meant a user with a connected IG
             account still saw WhatsApp-only in the builder. Without ANY
             Instagram present the channel stays hidden so you can't draw a flow
             that could never run. Plan entitlement is checked separately by the
             routes; this is purely "can this workspace run an Instagram flow". --}}
        @php
            $wsIdForFlow = (int) (auth()->user()->current_workspace_id ?? 0);
            // Instaflow-connected IG accounts, embedded straight into the page.
            // The builder ALSO fetches these from /flows/api/picker, but a stale
            // browser-cached copy of that GET kept the trigger's account dropdown
            // empty even after connecting — so we hand the accounts to the JS in
            // the HTML itself (data-ig-accounts) as the guaranteed source. Same
            // {key,id,label,username} shape the picker returns.
            $igAccountsForFlow = collect();
            if ($wsIdForFlow && class_exists(\App\Models\WorkspaceIgAccount::class)) {
                try {
                    $igAccountsForFlow = \App\Models\WorkspaceIgAccount::where('workspace_id', $wsIdForFlow)
                        ->orderBy('username')->get()
                        ->map(fn ($a) => [
                            'key'      => 'instagram:' . $a->id,
                            'id'       => (int) $a->id,
                            'label'    => '@' . ($a->username ?: ('account ' . $a->id)),
                            'username' => (string) $a->username,
                        ])->values();
                } catch (\Throwable $e) { $igAccountsForFlow = collect(); }
            }
            $igAvailable = \App\Models\Extension::enabled('instagram') || $igAccountsForFlow->isNotEmpty();
        @endphp
        data-ext-instagram="{{ $igAvailable ? '1' : '0' }}"
        data-ig-accounts="{{ json_encode($igAccountsForFlow, JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG) }}"
        data-flow-json="{{ json_encode($flowJson, JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG) }}">
        <div class="h-screen w-screen grid place-items-center">
            <div class="text-center">
                <div class="font-serif text-[18px] text-ink-700">{{ __('Loading flow builder...') }}</div>
            </div>
        </div>
    </div>

</x-layouts.user>
