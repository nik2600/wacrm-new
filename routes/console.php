<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Project policy: NO `php artisan schedule:run` dependency. Every
// periodic job runs INLINE on an existing AJAX-poll endpoint instead.
// The artisan commands listed below still exist for on-demand /
// support invocation, but are NOT wired to Schedule::command:
//
//   - inbox:escalate       → swept by TeamInboxController::queue()
//                            (every ~5s while any operator polls)
//   - inbox:wake-snoozed   → swept by TeamInboxController::queue()
//                            (every ~5s, cache-gated to 30s/workspace)
//   - support:sla-scan     → swept by TeamInboxController::queue()
//                            (every ~5s, cache-gated to 60s/workspace)
//   - WABA template status → swept by TemplatesController::refresh()
//                            on every page-load + AJAX poll
//
// Trade-off: workspaces with NO active operator don't get sweeps. For
// SLA + snooze this is acceptable — both surface again the moment
// someone opens /team-inbox. If a host wants stricter cadence, they
// can still call any of those commands manually from cron themselves.
