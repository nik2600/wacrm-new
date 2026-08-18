{{--
 Shared Categories sidebar for /guidebook (index + article view).
 Vars in: $categories (collection of {category, cnt}), $totalCount (int),
 $activeCat (string slug of selected category, or '' for All).
--}}
<aside class="bg-paper-0 border border-paper-200 rounded-[14px] shadow-card overflow-hidden xl:sticky xl:top-[20px]">
    <div class="px-4 py-3 border-b border-paper-200">
        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Categories') }}</div>
    </div>
    <ul class="divide-y divide-paper-200">
        <li>
            <a href="{{ route('user.guidebook') }}"
                class="w-full text-left px-4 py-2.5 flex items-center justify-between transition hover:bg-paper-50/60 {{ $activeCat === '' ? 'bg-wa-deep text-paper-0' : '' }}">
                <span
                    class="text-[13px] {{ $activeCat === '' ? 'font-semibold' : 'font-medium' }}">{{ __('All articles') }}</span>
                <span
                    class="font-mono text-[10.5px] px-2 py-0.5 rounded-full {{ $activeCat === '' ? 'bg-paper-0/15' : 'text-ink-500' }}">{{ $totalCount }}</span>
            </a>
        </li>
        @foreach ($categories as $c)
            @php $isActive = $activeCat === $c->category; @endphp
            <li>
                <a href="{{ route('user.guidebook', ['category' => $c->category]) }}"
                    class="w-full text-left px-4 py-2.5 flex items-center justify-between transition hover:bg-paper-50/60 {{ $isActive ? 'bg-wa-deep text-paper-0' : '' }}">
                    <span
                        class="text-[13px] {{ $isActive ? 'font-semibold' : 'font-medium' }}">{{ $c->category }}</span>
                    <span
                        class="font-mono text-[10.5px] {{ $isActive ? 'px-2 py-0.5 rounded-full bg-paper-0/15' : 'text-ink-500' }}">{{ $c->cnt }}</span>
                </a>
            </li>
        @endforeach
    </ul>
    <div class="p-4 border-t border-paper-200 bg-paper-50/40">
        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-2">{{ __('Need a human?') }}
        </div>
        <a href="{{ url('/support') }}"
            class="inline-flex items-center gap-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 px-3.5 py-2 text-[12px] font-semibold">
            <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.7">
                <path
                    d="M3 5.5A2.5 2.5 0 0 1 5.5 3h5A2.5 2.5 0 0 1 13 5.5v3A2.5 2.5 0 0 1 10.5 11H8l-3.5 2v-2A2.5 2.5 0 0 1 3 8.5v-3Z" />
            </svg>
            {{ __('Contact support') }}
        </a>
    </div>
</aside>
