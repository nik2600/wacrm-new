<x-layouts.user :title="__('Team Chat')" nav-key="team-inbox" page="user-team-chat">

    <style>
        /* Mention popover positioned above the textarea, inside the relative form */
        #tc-mention-pop {
            position: absolute;
            bottom: 64px;
            left: 16px;
            right: 16px;
            max-width: 280px;
        }

        /* Pulse animation for the unread channel badge */
        @keyframes tcPulse {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: .55;
            }
        }

        .tc-unread-pulse {
            animation: tcPulse 1.6s ease-in-out infinite;
        }
    </style>

    <!-- ========== TEAM CHAT FRAME ========== -->
    <div id="tc-frame" class="ti-frame no-contact border-t border-paper-200">

        <!-- ========== COL 1: WORKSPACE + MODE NAV ========== -->
        <aside class="ti-col bg-paper-0 border-r border-paper-200">
            <div class="px-3 pt-3 pb-2 shrink-0">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 px-1">{{ __('Workspace') }}
                </div>
                <div class="font-serif text-[16px] leading-tight px-1 mt-0.5">
                    {{ optional(auth()->user()?->currentWorkspace)->name ?? __('Workspace') }}
                </div>
            </div>

            <div class="flex-1 overflow-y-auto px-2 pb-2 space-y-0.5">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 px-2 pt-2 pb-1">
                    {{ __('Mode') }}</div>
                <a href="{{ url('/team-inbox') }}" class="team-nav-btn w-full">
                    <svg viewBox="0 0 16 16" class="w-4 h-4 shrink-0" fill="none" stroke="currentColor"
                        stroke-width="1.6">
                        <rect x="2" y="2" width="5" height="5" rx="1" />
                        <rect x="9" y="2" width="5" height="5" rx="1" />
                        <rect x="2" y="9" width="5" height="5" rx="1" />
                        <rect x="9" y="9" width="5" height="5" rx="1" />
                    </svg>
                    Customer chats
                </a>
                <button class="team-nav-btn w-full active">
                    <svg viewBox="0 0 16 16" class="w-4 h-4 shrink-0" fill="none" stroke="currentColor"
                        stroke-width="1.6">
                        <path d="M2 4c0-1 .8-2 2-2h8c1.2 0 2 1 2 2v6c0 1-.8 2-2 2H7l-3 3v-3H4c-1.2 0-2-1-2-2z" />
                    </svg>
                    Team chat
                </button>

                <!-- Channel list — populated by JS -->
                <div class="flex items-center justify-between px-2 pt-3 pb-1">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Channels') }}
                    </div>
                    <button id="tc-create-channel-btn" title="{{ __('Create channel') }}"
                        class="w-5 h-5 rounded hover:bg-paper-200 grid place-items-center text-ink-500 hover:text-ink-900">
                        <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M8 3v10M3 8h10" />
                        </svg>
                    </button>
                </div>
                <div id="tc-channel-list" class="space-y-0.5"></div>

                <!-- Invitations / approval — visible to all; non-admins see their own requests -->
                <div class="flex items-center justify-between px-2 pt-3 pb-1">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('People') }}
                    </div>
                    <button id="tc-invite-btn" title="{{ __('Invite teammate') }}"
                        class="w-5 h-5 rounded hover:bg-paper-200 grid place-items-center text-ink-500 hover:text-ink-900">
                        <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M8 3v10M3 8h10" />
                        </svg>
                    </button>
                </div>
                <a href="{{ url('/team-inbox/members') }}" class="team-nav-btn w-full">
                    <svg viewBox="0 0 16 16" class="w-4 h-4 shrink-0" fill="none" stroke="currentColor"
                        stroke-width="1.6">
                        <circle cx="6" cy="6" r="2.5" />
                        <path d="M2 14c0-3 1.7-5 4-5s4 2 4 5" />
                        <circle cx="11.5" cy="7" r="2" />
                        <path d="M9 14c0-2 1.5-3.5 3-3.5s3 1.5 3 3.5" />
                    </svg>
                    Members
                </a>
                <button id="tc-pending-btn" class="team-nav-btn w-full">
                    <svg viewBox="0 0 16 16" class="w-4 h-4 shrink-0" fill="none" stroke="currentColor"
                        stroke-width="1.6">
                        <circle cx="8" cy="8" r="6" />
                        <path d="M8 5v3l2 2" />
                    </svg>
                    Pending requests <span class="ct" id="tc-pending-count" style="display:none">0</span>
                </button>
            </div>
        </aside>

        <!-- ========== COL 2: MEMBERS RAIL ========== -->
        <aside class="ti-col bg-paper-0 border-r border-paper-200">
            <div class="px-3 pt-3 pb-2 border-b border-paper-200 shrink-0 space-y-2">
                <div class="flex items-center justify-between gap-2">
                    <h1 class="font-serif text-[18px] leading-tight">{{ __('Team') }} <span
                            class="italic text-wa-deep">{{ __('chat') }}</span></h1>
                </div>
                <div class="text-[11.5px] text-ink-500"><span id="tc-member-count">—</span> members</div>
                <div class="relative">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 absolute left-2.5 top-1/2 -translate-y-1/2 text-ink-500"
                        fill="none" stroke="currentColor" stroke-width="1.6">
                        <circle cx="7" cy="7" r="5" />
                        <path d="m11 11 3 3" />
                    </svg>
                    <input id="tc-search" type="search" placeholder="{{ __('Search members…') }}"
                        class="w-full pl-8 pr-3 py-1.5 border border-paper-200 rounded-md bg-paper-50 text-[12.5px] focus:outline-none focus:bg-paper-0 focus:border-wa-deep" />
                </div>
            </div>
            <div id="tc-members-list" class="flex-1 overflow-y-auto py-1">
                <div class="text-center text-ink-400 text-[11px] font-mono py-4">{{ __('Loading…') }}</div>
            </div>
        </aside>

        <!-- ========== COL 3: CHAT STREAM ========== -->
        <main class="ti-col bg-paper-0">
            <!-- Channel header -->
            <div class="px-3 sm:px-5 py-2.5 sm:py-3 border-b border-paper-200 flex items-center gap-2 sm:gap-3 shrink-0">
                <!-- Mobile-only: back to the channel list (shown below lg / ≤1023px) -->
                <button type="button" id="tc-back"
                    class="lg:hidden w-9 h-9 -ml-1 rounded-full hover:bg-paper-100 grid place-items-center text-ink-600 shrink-0"
                    title="{{ __('Back to channels') }}" aria-label="{{ __('Back') }}">
                    <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path d="M10 3L5 8l5 5" />
                    </svg>
                </button>
                <span class="hidden sm:grid w-10 h-10 rounded-full bg-wa-deep text-paper-0 place-items-center shrink-0">
                    <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor"
                        stroke-width="1.7">
                        <path d="M3 6h10M3 10h10M5 2v12M11 2v12" />
                    </svg>
                </span>
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2 min-w-0">
                        <div id="tc-channel-name" class="font-serif text-[17px] leading-tight truncate">#general</div>
                        <span id="tc-channel-type" class="pill pill-open shrink-0 hidden sm:inline-flex">{{ __('Channel') }}</span>
                    </div>
                    <div id="tc-channel-desc" class="text-[11.5px] text-ink-500 truncate">
                        {{ __('Internal workspace chat') }}</div>
                </div>
                <a href="{{ url('/team-inbox') }}"
                    class="px-2.5 sm:px-3 py-1.5 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium inline-flex items-center gap-1.5 shrink-0">
                    <svg viewBox="0 0 16 16" class="w-3 h-3 shrink-0" fill="none" stroke="currentColor"
                        stroke-width="1.6">
                        <path d="M14 8H3M6 5L3 8l3 3" />
                    </svg>
                    <span class="hidden sm:inline">Customer chats</span>
                </a>
            </div>

            <!-- Messages -->
            <div id="tc-stream" data-allow-drop="1"
                class="flex-1 overflow-y-auto px-5 py-4 space-y-1 bg-paper-50/30">
                <div class="text-center text-ink-400 text-[12px] font-mono py-12">{{ __('Loading messages…') }}</div>
            </div>

            <!-- Composer -->
            <form id="tc-composer" class="border-t border-paper-200 bg-paper-0 px-4 py-3 shrink-0 relative">
                <div id="tc-reply-banner"
                    class="hidden mb-2 px-2.5 py-1.5 rounded-md bg-wa-bubble/40 text-[11.5px] flex items-center gap-2">
                    <svg viewBox="0 0 16 16" class="w-3 h-3 text-wa-deep shrink-0" fill="none"
                        stroke="currentColor" stroke-width="1.7">
                        <path d="M5 4l-3 4 3 4M2 8h7c2.5 0 4 1.5 4 4" />
                    </svg>
                    <span class="text-ink-700">{{ __('Replying to') }} <strong id="tc-reply-name"></strong></span>
                    <span id="tc-reply-snippet" class="text-ink-500 truncate flex-1"></span>
                    <button type="button" id="tc-reply-cancel" class="text-ink-500 hover:text-ink-900">×</button>
                </div>

                <div class="flex items-end gap-2">
                    <textarea id="tc-input" rows="1" maxlength="8192"
                        placeholder="Message #general… use @ to mention, drag images here"
                        class="flex-1 px-3 py-2 border border-paper-200 rounded-md bg-paper-50 text-[13px] resize-none focus:outline-none focus:bg-paper-0 focus:border-wa-deep"></textarea>
                    <button type="button" id="tc-attach-btn"
                        class="w-9 h-9 rounded-full hover:bg-paper-100 grid place-items-center text-ink-500 shrink-0"
                        title="{{ __('Attach file or image') }}">
                        <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor"
                            stroke-width="1.6">
                            <path
                                d="M11 4l-6 6a2.5 2.5 0 0 0 3.5 3.5l7-7a4 4 0 0 0-5.5-5.5l-7 7a5.5 5.5 0 0 0 8 8l6-6" />
                        </svg>
                    </button>
                    <input type="file" id="tc-attach" class="hidden"
                        accept="image/*,application/pdf,.doc,.docx,.xls,.xlsx,.txt">
                    <button type="submit" id="tc-send"
                        class="px-4 h-9 rounded-full bg-wa-deep text-paper-0 hover:bg-wa-teal text-[12.5px] font-semibold inline-flex items-center gap-1.5 shrink-0">
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                            stroke-width="1.8">
                            <path d="M2 8l12-5-5 12-2-5z" />
                        </svg>
                        Send
                    </button>
                </div>

                <div id="tc-attach-preview"
                    class="hidden mt-2 p-2 rounded-md bg-paper-100 text-[11.5px] flex items-center gap-2">
                    <img id="tc-attach-thumb" alt=""
                        class="hidden w-12 h-12 rounded object-cover bg-paper-200">
                    <svg id="tc-attach-icon" viewBox="0 0 16 16" class="w-3.5 h-3.5 text-wa-deep" fill="none"
                        stroke="currentColor" stroke-width="1.6">
                        <path d="M9 1H3v14h10V5z M9 1v4h4" />
                    </svg>
                    <span id="tc-attach-name" class="truncate flex-1"></span>
                    <span id="tc-attach-size" class="font-mono text-[10px] text-ink-500"></span>
                    <button type="button" id="tc-attach-clear" class="text-ink-500 hover:text-ink-900">×</button>
                </div>

                <div class="mt-2 text-[10px] font-mono text-ink-400">
                    <kbd class="px-1 py-0.5 rounded border border-paper-200 bg-paper-50">↵</kbd> send ·
                    <kbd class="px-1 py-0.5 rounded border border-paper-200 bg-paper-50">{{ __('Shift+↵') }}</kbd> new
                    line ·
                    <kbd class="px-1 py-0.5 rounded border border-paper-200 bg-paper-50">@</kbd> mention
                </div>

                <!-- Anchored above the textarea -->
                <div id="tc-mention-pop"
                    class="hidden bg-paper-0 border border-paper-200 rounded-md shadow-lg max-h-64 overflow-y-auto z-30">
                </div>
            </form>
        </main>
    </div>

    <!-- ========== Create-channel modal ========== -->
    <div id="tc-create-modal" class="hidden fixed inset-0 z-50 bg-ink-900/40 grid place-items-center px-4">
        <div class="bg-paper-0 rounded-md shadow-xl w-full max-w-md">
            <div class="px-5 py-3 border-b border-paper-200 flex items-center justify-between">
                <div class="font-serif text-[16px]">{{ __('Create a channel') }}</div>
                <button class="text-ink-500 hover:text-ink-900" data-tc-close>×</button>
            </div>
            <form id="tc-create-form" class="px-5 py-4 space-y-3">
                <div>
                    <label
                        class="block font-mono text-[10px] uppercase tracking-wider text-ink-500 mb-1">{{ __('Name') }}</label>
                    <div class="flex items-center">
                        <span
                            class="px-2 py-2 bg-paper-100 border border-r-0 border-paper-200 rounded-l-md text-ink-500 text-[13px]">#</span>
                        <input name="name" required maxlength="64" placeholder="{{ __('e.g. support') }}"
                            pattern="[a-z0-9][a-z0-9-_]*"
                            class="flex-1 px-3 py-2 border border-paper-200 rounded-r-md text-[13px] focus:outline-none focus:border-wa-deep">
                    </div>
                    <div class="text-[10.5px] text-ink-500 mt-1">
                        {{ __('Lowercase, no spaces. Letters, digits, dash, underscore.') }}</div>
                </div>
                <div>
                    <label
                        class="block font-mono text-[10px] uppercase tracking-wider text-ink-500 mb-1">{{ __('Description (optional)') }}</label>
                    <input name="description" maxlength="255" placeholder="{{ __("What's this channel for?") }}"
                        class="w-full px-3 py-2 border border-paper-200 rounded-md text-[13px] focus:outline-none focus:border-wa-deep">
                </div>
                <div>
                    <label
                        class="block font-mono text-[10px] uppercase tracking-wider text-ink-500 mb-1">{{ __('Privacy') }}</label>
                    <div class="flex gap-2">
                        <label
                            class="flex-1 px-3 py-2 border border-paper-200 rounded-md cursor-pointer hover:border-wa-deep has-[:checked]:border-wa-deep has-[:checked]:bg-wa-bubble/30">
                            <input type="radio" name="type" value="public" checked class="mr-1.5"> Public
                            <div class="text-[10.5px] text-ink-500 mt-0.5">{{ __('Everyone in workspace') }}</div>
                        </label>
                        <label
                            class="flex-1 px-3 py-2 border border-paper-200 rounded-md cursor-pointer hover:border-wa-deep has-[:checked]:border-wa-deep has-[:checked]:bg-wa-bubble/30">
                            <input type="radio" name="type" value="private" class="mr-1.5"> Private
                            <div class="text-[10.5px] text-ink-500 mt-0.5">{{ __('Invite-only') }}</div>
                        </label>
                    </div>
                </div>
                <div id="tc-create-error" class="hidden text-[12px] text-red-600 font-medium"></div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" class="px-3 py-1.5 rounded-full border border-paper-200 text-[12px]"
                        data-tc-close>{{ __('Cancel') }}</button>
                    <button type="submit"
                        class="px-4 py-1.5 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold">{{ __('Create channel') }}</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ========== Invite-member modal ========== -->
    <div id="tc-invite-modal" class="hidden fixed inset-0 z-50 bg-ink-900/40 grid place-items-center px-4">
        <div class="bg-paper-0 rounded-md shadow-xl w-full max-w-md">
            <div class="px-5 py-3 border-b border-paper-200 flex items-center justify-between">
                <div class="font-serif text-[16px]">{{ __('Invite a teammate') }}</div>
                <button class="text-ink-500 hover:text-ink-900" data-tc-close>×</button>
            </div>
            <form id="tc-invite-form" class="px-5 py-4 space-y-3">
                <div>
                    <label
                        class="block font-mono text-[10px] uppercase tracking-wider text-ink-500 mb-1">{{ __('Email') }}</label>
                    <input type="email" name="invitee_email" required placeholder="teammate@company.com"
                        class="w-full px-3 py-2 border border-paper-200 rounded-md text-[13px] focus:outline-none focus:border-wa-deep">
                </div>
                <div>
                    <label
                        class="block font-mono text-[10px] uppercase tracking-wider text-ink-500 mb-1">{{ __('Name (optional)') }}</label>
                    <input type="text" name="invitee_name" maxlength="191" placeholder="{{ __('Their name') }}"
                        class="w-full px-3 py-2 border border-paper-200 rounded-md text-[13px] focus:outline-none focus:border-wa-deep">
                </div>
                <div>
                    <label
                        class="block font-mono text-[10px] uppercase tracking-wider text-ink-500 mb-1">{{ __('Note for the admin') }}</label>
                    <textarea name="note" rows="2" maxlength="500" placeholder="{{ __('Why should they join?') }}"
                        class="w-full px-3 py-2 border border-paper-200 rounded-md text-[13px] focus:outline-none focus:border-wa-deep resize-none"></textarea>
                </div>
                <div id="tc-invite-result" class="hidden text-[12px] font-medium"></div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" class="px-3 py-1.5 rounded-full border border-paper-200 text-[12px]"
                        data-tc-close>{{ __('Cancel') }}</button>
                    <button type="submit"
                        class="px-4 py-1.5 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold">{{ __('Submit invite') }}</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ========== Pending-invitations modal ========== -->
    <div id="tc-pending-modal" class="hidden fixed inset-0 z-50 bg-ink-900/40 grid place-items-center px-4">
        <div class="bg-paper-0 rounded-md shadow-xl w-full max-w-xl max-h-[80vh] flex flex-col">
            <div class="px-5 py-3 border-b border-paper-200 flex items-center justify-between shrink-0">
                <div class="font-serif text-[16px]">{{ __('Pending invitations') }}</div>
                <button class="text-ink-500 hover:text-ink-900" data-tc-close>×</button>
            </div>
            <div id="tc-pending-list" class="flex-1 overflow-y-auto px-5 py-3 space-y-2">
                <div class="text-center text-ink-400 text-[12px] font-mono py-8">{{ __('Loading…') }}</div>
            </div>
        </div>
    </div>

    <script>
        window.tcEndpoints = {
            index: @json(route('user.team-inbox.api.team-chat.index')),
            store: @json(route('user.team-inbox.api.team-chat.store')),
            markRead: @json(route('user.team-inbox.api.team-chat.mark-read')),
            destroy: @json(url('/team-inbox/api/team-chat')),
            channelsIndex: @json(route('user.team-inbox.api.team-chat.channels.index')),
            channelsStore: @json(route('user.team-inbox.api.team-chat.channels.store')),
            channelsDestroy: @json(url('/team-inbox/api/team-chat/channels')),
            invitationsIndex: @json(route('user.team-inbox.api.team-chat.invitations.index')),
            invitationsStore: @json(route('user.team-inbox.api.team-chat.invitations.store')),
            invitationsApprove: @json(url('/team-inbox/api/team-chat/invitations')),
        };
        window.tcMe = {
            id: {{ (int) auth()->id() }},
            name: @json(auth()->user()->name)
        };
    </script>

</x-layouts.user>
