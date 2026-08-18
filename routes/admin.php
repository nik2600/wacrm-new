<?php

use App\Http\Controllers\Admin\AdminNotificationController;
use App\Http\Controllers\Admin\AiDashboardController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\FinancialController;
use App\Http\Controllers\Admin\HealthController;
use App\Http\Controllers\Admin\ContactMessagesController;
use App\Http\Controllers\Admin\FrontendEditorController;
use App\Http\Controllers\Admin\SiteSettingsController;
use App\Http\Controllers\Admin\LanguagesController;
use App\Http\Controllers\Admin\OverviewController;
use App\Http\Controllers\Admin\PremiumController;
use App\Http\Controllers\Admin\SecurityController;
use App\Http\Controllers\Admin\StorageSettingsController;
use App\Http\Controllers\Admin\BlogController;
use App\Http\Controllers\Admin\UsersController;
use App\Http\Controllers\Admin\WorkspacesController;
use App\Http\Controllers\AdminPagesController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\RoleController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin (platform operations) routes
|--------------------------------------------------------------------------
|
| These routes are loaded by the RouteServiceProvider via the bootstrap
| `withRouting()` registration. They are automatically prefixed with
| `/admin` and named `admin.*` — therefore the route definitions below
| should NOT include the `Route::prefix('admin')->name('admin.')->group(...)`
| wrapper. Each route is written directly.
|
*/

Route::get('/', [OverviewController::class, 'index'])->name('dashboard');
Route::get('/financial', [FinancialController::class, 'index'])->name('financial');
Route::get('/premium',   [PremiumController::class,   'index'])->name('premium');
Route::get('/ai-dashboard', [AiDashboardController::class, 'index'])->name('ai-dashboard');
Route::get('/health',       [HealthController::class,      'index'])->name('health');

Route::prefix('users')->name('users.')->group(function () {
    Route::get   ('/',                  [UsersController::class, 'index'])->name('index');
    Route::get   ('/create',            [UsersController::class, 'create'])->name('create');
    Route::post  ('/',                  [UsersController::class, 'store'])->name('store');
    Route::get   ('/import',            [UsersController::class, 'importForm'])->name('import');
    Route::post  ('/import',            [UsersController::class, 'import'])->name('import.store');
    Route::get   ('/import/template',   [AdminPagesController::class, 'userImportTemplate'])->name('import.template');
    Route::get   ('/trash',             [UsersController::class, 'trash'])->name('trash');
    Route::post  ('/trash/empty',       [UsersController::class, 'emptyTrash'])->name('trash.empty');
    Route::post  ('/{id}/restore',      [UsersController::class, 'restore'])->whereNumber('id')->name('restore');
    Route::delete('/{id}/force',        [UsersController::class, 'forceDelete'])->whereNumber('id')->name('force-delete');
    Route::get   ('/{id}/edit',         [UsersController::class, 'edit'])->whereNumber('id')->name('edit');
    Route::put   ('/{id}',              [UsersController::class, 'update'])->whereNumber('id')->name('update');
    Route::delete('/{id}',              [UsersController::class, 'destroy'])->whereNumber('id')->name('destroy');
    Route::post  ('/{id}/toggle',       [UsersController::class, 'toggleStatus'])->whereNumber('id')->name('toggle');
    Route::post  ('/{id}/reset',        [UsersController::class, 'resetPassword'])->whereNumber('id')->name('reset');
    Route::post  ('/{id}/force-logout', [UsersController::class, 'forceLogout'])->whereNumber('id')->name('force-logout');
});

Route::prefix('roles')->name('roles.')->group(function () {
    Route::get('/',          [RoleController::class, 'index'])->name('index');
    Route::get('/create',    [RoleController::class, 'create'])->name('create');
    Route::post('/',         [RoleController::class, 'store'])->name('store');
    Route::get('/{id}/edit', [RoleController::class, 'edit'])->name('edit');
    Route::put('/{id}',      [RoleController::class, 'update'])->name('update');
    Route::post('/{id}/duplicate', [RoleController::class, 'duplicate'])->name('duplicate');
    Route::post('/{id}/reassign',  [RoleController::class, 'reassign'])->name('reassign');
    Route::delete('/{id}',   [RoleController::class, 'destroy'])->name('destroy');
});

Route::prefix('permissions')->name('permissions.')->group(function () {
    Route::get('/',        [PermissionController::class, 'index'])->name('index');
    Route::get('/create',  [PermissionController::class, 'create'])->name('create');
    Route::post('/',       [PermissionController::class, 'store'])->name('store');
    Route::delete('/{id}', [PermissionController::class, 'destroy'])->name('destroy');
});

Route::prefix('workspaces')->name('workspaces.')->group(function () {
    Route::get   ('/',                 [WorkspacesController::class, 'index'])->name('index');
    Route::get   ('/create',           [WorkspacesController::class, 'create'])->name('create');
    Route::post  ('/',                 [WorkspacesController::class, 'store'])->name('store');
    Route::get   ('/{id}',             [WorkspacesController::class, 'detail'])->whereNumber('id')->name('detail');
    Route::put   ('/{id}',             [WorkspacesController::class, 'update'])->whereNumber('id')->name('update');
    Route::delete('/{id}',             [WorkspacesController::class, 'destroy'])->whereNumber('id')->name('destroy');
    Route::post  ('/{id}/toggle',      [WorkspacesController::class, 'toggleStatus'])->whereNumber('id')->name('toggle');
    // #31-36 — Save per-workspace plan limit overrides.
    Route::post  ('/{id}/overrides',   [AdminPagesController::class, 'workspaceSaveOverrides'])->whereNumber('id')->name('overrides');
});

Route::prefix('devices')->name('devices.')->group(function () {
    Route::get('/',       [AdminPagesController::class, 'devices'])->name('index');
    // Static segments MUST precede the /{id} catch-all or 'export'/'bulk-resync'
    // would be swallowed as an id (and rejected by whereNumber, 404ing the button).
    Route::get('/export',       [AdminPagesController::class, 'devicesExport'])->name('export');
    Route::post('/bulk-resync', [AdminPagesController::class, 'devicesBulkResync'])->name('bulk-resync');
    Route::get('/{id}', [AdminPagesController::class, 'deviceDetail'])->whereNumber('id')->name('detail');
    // Re-sync pulls live connection state from the Node bridge and updates the
    // row; force-disconnect terminates the live socket; remove deletes the row
    // (killing its session first so no orphan stays running).
    Route::post  ('/{id}/resync',     [AdminPagesController::class, 'deviceResync'])->whereNumber('id')->name('resync');
    Route::post  ('/{id}/disconnect', [AdminPagesController::class, 'deviceDisconnect'])->whereNumber('id')->name('disconnect');
    Route::delete('/{id}',            [AdminPagesController::class, 'deviceDestroy'])->whereNumber('id')->name('destroy');
});

Route::prefix('packages')->name('packages.')->group(function () {
    Route::get   ('/',                   [AdminPagesController::class, 'packages'])->name('index');
    Route::get   ('/create',             [AdminPagesController::class, 'packageCreate'])->name('create');
    Route::post  ('/',                   [AdminPagesController::class, 'packageStore'])->name('store');
    Route::get   ('/analytics/overview', [AdminPagesController::class, 'packageAnalytics'])->name('analytics');
    // CSV export — same filters as the analytics page (`days`, `package`).
    // Streams the leaderboard + share + KPI summary as one downloadable CSV.
    Route::get   ('/analytics/export',   [AdminPagesController::class, 'packageAnalyticsExport'])->name('analytics.export');
    Route::get   ('/{id}/edit',          [AdminPagesController::class, 'packageEdit'])->name('edit');
    Route::patch ('/{id}',               [AdminPagesController::class, 'packageUpdate'])->name('update');
    Route::delete('/{id}',               [AdminPagesController::class, 'packageDestroy'])->name('destroy');
    Route::post  ('/{id}/toggle',        [AdminPagesController::class, 'packageToggle'])->name('toggle');
});

// Payment gateways — every gateway is pre-seeded; admin just edits
// credentials + toggles active. No install / destroy step.
Route::prefix('payment-gateways')->name('payment-gateways.')->group(function () {
    $pg = \App\Http\Controllers\Admin\PaymentGatewayController::class;
    Route::get  ('/',              [$pg, 'index'])->name('index');
    Route::patch('/{id}',          [$pg, 'update'])->whereNumber('id')->name('update');
    Route::post ('/{id}/toggle',   [$pg, 'toggle'])->whereNumber('id')->name('toggle');
});

// Back-compat: old links pointed at /admin/ai-keys (missing the "p"). Auto-
// redirect to the real page so any stale link/cached bundle lands correctly
// instead of 404ing.
Route::redirect('ai-keys', 'admin/api-keys');

// Admin global AI provider keys (OpenAI / Anthropic / Gemini / Mistral).
// Same pattern as payment-gateways: pre-seeded, admin just edits.
Route::prefix('api-keys')->name('api-keys.')->group(function () {
    $ak = \App\Http\Controllers\Admin\AdminAiKeyController::class;
    Route::get  ('/',              [$ak, 'index'])->name('index');
    Route::patch('/{id}',          [$ak, 'update'])->whereNumber('id')->name('update');
    Route::post ('/{id}/toggle',   [$ak, 'toggle'])->whereNumber('id')->name('toggle');
});

// Translation providers — MyMemory / LibreTranslate / DeepL / Google.
// Same pre-seed-and-edit shape as the other catalog admin pages.
Route::prefix('translation-providers')->name('translation-providers.')->group(function () {
    $tp = \App\Http\Controllers\Admin\TranslationProviderController::class;
    Route::get  ('/',              [$tp, 'index'])->name('index');
    Route::patch('/{id}',          [$tp, 'update'])->whereNumber('id')->name('update');
    Route::post ('/{id}/toggle',   [$tp, 'toggle'])->whereNumber('id')->name('toggle');
    Route::post ('/{id}/default',  [$tp, 'setDefault'])->whereNumber('id')->name('default');
    Route::post ('/lockdown',      [$tp, 'lockdown'])->name('lockdown');
});

// Translation usage dashboard — read-only.
Route::get('/translation-usage', [\App\Http\Controllers\Admin\TranslationUsageController::class, 'index'])
    ->name('translation-usage');

Route::prefix('currencies')->name('currencies.')->group(function () {
    $cc = \App\Http\Controllers\Admin\CurrencyController::class;
    Route::get   ('/',                  [$cc, 'index'])->name('index');
    Route::post  ('/',                  [$cc, 'store'])->name('store');
    Route::patch ('/{id}',              [$cc, 'update'])->whereNumber('id')->name('update');
    Route::delete('/{id}',              [$cc, 'destroy'])->whereNumber('id')->name('destroy');
    Route::post  ('/{id}/toggle',       [$cc, 'toggle'])->whereNumber('id')->name('toggle');
    Route::post  ('/fetch-rates',       [$cc, 'fetchRates'])->name('fetch-rates');
    Route::post  ('/default',           [$cc, 'setDefault'])->name('default');
});

Route::prefix('languages')->name('languages.')->group(function () {
    Route::get   ('/',             [LanguagesController::class, 'index'])->name('index');
    Route::post  ('/',             [LanguagesController::class, 'store'])->name('store');
    Route::patch ('/{id}',         [LanguagesController::class, 'update'])->whereNumber('id')->name('update');
    Route::post  ('/{id}/toggle',  [LanguagesController::class, 'toggle'])->whereNumber('id')->name('toggle');
    Route::post  ('/{id}/default', [LanguagesController::class, 'setDefault'])->whereNumber('id')->name('default');
    Route::delete('/{id}',         [LanguagesController::class, 'destroy'])->whereNumber('id')->name('destroy');
});

Route::prefix('credit-packages')->name('credit-packages.')->group(function () {
    $c = \App\Http\Controllers\Admin\AdminCreditPackagesController::class;
    Route::get('/',              [$c, 'index'])->name('index');
    Route::get('/create',        [$c, 'create'])->name('create');
    Route::post('/',             [$c, 'store'])->name('store');
    Route::get('/{id}/edit',     [$c, 'edit'])->whereNumber('id')->name('edit');
    Route::put('/{id}',          [$c, 'update'])->whereNumber('id')->name('update');
    Route::post('/{id}/toggle',  [$c, 'toggle'])->whereNumber('id')->name('toggle');
    Route::delete('/{id}',       [$c, 'destroy'])->whereNumber('id')->name('destroy');
});

// Add-ons — separate admin section (à-la-carte feature packs). List + toggle +
// delete live here; create/edit reuse the packages form pre-set to type=addon.
Route::prefix('addons')->name('addons.')->group(function () {
    $ac = \App\Http\Controllers\Admin\AddonsController::class;
    Route::get('/',             [$ac, 'index'])->name('index');
    Route::get('/create',       [$ac, 'create'])->name('create');
    Route::post('/',            [$ac, 'store'])->name('store');
    Route::get('/{id}/edit',    [$ac, 'edit'])->whereNumber('id')->name('edit');
    Route::put('/{id}',         [$ac, 'store'])->whereNumber('id')->name('update');
    Route::post('/{id}/toggle', [$ac, 'toggle'])->whereNumber('id')->name('toggle');
    Route::delete('/{id}',      [$ac, 'destroy'])->whereNumber('id')->name('destroy');
});

Route::prefix('billing-history')->name('billing-history.')->group(function () {
    $bh = \App\Http\Controllers\Admin\BillingHistoryController::class;
    Route::get('/',          [$bh, 'index'])->name('index');
    Route::get('/analytics', [$bh, 'analytics'])->name('analytics');
});

Route::prefix('order-history')->name('order-history.')->group(function () {
    $oh = \App\Http\Controllers\Admin\OrderHistoryController::class;
    Route::get('/',          [$oh, 'index'])->name('index');
    Route::get('/analytics', [$oh, 'analytics'])->name('analytics');
    // Offline / bank-transfer payment-proof review.
    Route::post('/{id}/approve', [$oh, 'approve'])->whereNumber('id')->name('approve');
    Route::post('/{id}/reject',  [$oh, 'reject'])->whereNumber('id')->name('reject');
});

Route::prefix('invoices')->name('invoices.')->group(function () {
    $iv = \App\Http\Controllers\Admin\InvoicesController::class;
    Route::get('/',     [$iv, 'index'])->name('index');
    Route::post('/',    [$iv, 'store'])->name('store');
    Route::get('/{id}', [$iv, 'show'])->whereNumber('id')->name('view');
    // Row actions — the index menu used to only offer View/Open workspace, so
    // an admin could never mark an outstanding bill paid, void it, or delete it.
    Route::post('/{id}/mark-paid', [$iv, 'markPaid'])->whereNumber('id')->name('mark-paid');
    Route::post('/{id}/void',      [$iv, 'void'])->whereNumber('id')->name('void');
    Route::delete('/{id}',         [$iv, 'destroy'])->whereNumber('id')->name('destroy');
});

Route::prefix('coupons')->name('coupons.')->group(function () {
    $c = \App\Http\Controllers\Admin\CouponController::class;
    Route::get   ('/',              [$c, 'index'])->name('index');
    Route::get   ('/create',        [$c, 'create'])->name('create');
    Route::post  ('/',              [$c, 'store'])->name('store');
    Route::get   ('/{id}/edit',     [$c, 'edit'])->whereNumber('id')->name('edit');
    Route::patch ('/{id}',          [$c, 'update'])->whereNumber('id')->name('update');
    Route::post  ('/{id}/toggle',   [$c, 'toggle'])->whereNumber('id')->name('toggle');
    Route::delete('/{id}',          [$c, 'destroy'])->whereNumber('id')->name('destroy');
});

Route::prefix('pricing-faqs')->name('pricing-faqs.')->group(function () {
    $f = \App\Http\Controllers\Admin\PricingFaqController::class;
    Route::get   ('/',              [$f, 'index'])->name('index');
    Route::post  ('/',              [$f, 'store'])->name('store');
    Route::patch ('/{id}',          [$f, 'update'])->whereNumber('id')->name('update');
    Route::post  ('/{id}/toggle',   [$f, 'toggle'])->whereNumber('id')->name('toggle');
    Route::delete('/{id}',          [$f, 'destroy'])->whereNumber('id')->name('destroy');
});

Route::prefix('checkout-settings')->name('checkout-settings.')->group(function () {
    $cs = \App\Http\Controllers\Admin\CheckoutSettingsController::class;
    Route::get ('/',  [$cs, 'index'])->name('index');
    Route::post('/',  [$cs, 'update'])->name('update');
});
Route::get('/team-inbox',    [\App\Http\Controllers\Admin\AdminTeamInboxController::class, 'index'])->name('team-inbox');

Route::prefix('team-inbox/api')->name('team-inbox.api.')->group(function () {
    $c = \App\Http\Controllers\Admin\AdminTeamInboxController::class;
    Route::get('/bootstrap',                  [$c, 'bootstrap'])->name('bootstrap');
    Route::get('/queue',                      [$c, 'queue'])->name('queue');
    Route::get('/conversations/{id}',         [$c, 'show'])->whereNumber('id')->name('show');
    Route::post('/conversations/{id}/note',   [$c, 'platformNote'])->whereNumber('id')->name('note');
    Route::post('/conversations/{id}/spam',   [$c, 'flagSpam'])->whereNumber('id')->name('spam');
    Route::post('/workspaces/{id}/flag',      [$c, 'flagWorkspace'])->whereNumber('id')->name('flag');
    Route::delete('/flags/{id}',              [$c, 'clearFlag'])->whereNumber('id')->name('flag.clear');
    Route::post('/workspaces/{id}/suspend',   [$c, 'suspendWorkspace'])->whereNumber('id')->name('suspend');
    Route::post('/workspaces/{id}/unsuspend', [$c, 'unsuspendWorkspace'])->whereNumber('id')->name('unsuspend');
    Route::get('/audit',                      [$c, 'auditLog'])->name('audit');
});

Route::post('/impersonate/{workspaceId}', [\App\Http\Controllers\Admin\ImpersonationController::class, 'start'])
    ->whereNumber('workspaceId')->name('impersonate.start');
Route::post('/impersonate/stop',          [\App\Http\Controllers\Admin\ImpersonationController::class, 'stop'])->name('impersonate.stop');
Route::prefix('announcements')->name('announcements.')->group(function () {
    $a = \App\Http\Controllers\Admin\AnnouncementsController::class;
    Route::get   ('/',              [$a, 'index'])->name('index');
    Route::get   ('/create',        [$a, 'create'])->name('create');
    Route::post  ('/',              [$a, 'store'])->name('store');
    Route::get   ('/{id}/edit',     [$a, 'edit'])->whereNumber('id')->name('edit');
    Route::patch ('/{id}',          [$a, 'update'])->whereNumber('id')->name('update');
    Route::post  ('/{id}/toggle',   [$a, 'toggle'])->whereNumber('id')->name('toggle');
    Route::delete('/{id}',          [$a, 'destroy'])->whereNumber('id')->name('destroy');
});
Route::prefix('legal-pages')->name('legal-pages.')->group(function () {
    $lp = \App\Http\Controllers\Admin\LegalPagesController::class;
    Route::get   ('/',              [$lp, 'index'])->name('index');
    Route::get   ('/{slug}/edit',   [$lp, 'edit'])->name('edit');
    Route::patch ('/{slug}',        [$lp, 'update'])->name('update');
    Route::post  ('/{slug}/toggle', [$lp, 'toggle'])->name('toggle');
});
Route::prefix('guidebook')->name('guidebook.')->group(function () {
    $g = \App\Http\Controllers\Admin\GuidebookController::class;
    Route::get   ('/',               [$g, 'index'])->name('index');
    Route::get   ('/create',         [$g, 'create'])->name('create');
    Route::post  ('/',               [$g, 'store'])->name('store');
    Route::get   ('/{id}/edit',      [$g, 'edit'])->whereNumber('id')->name('edit');
    Route::patch ('/{id}',           [$g, 'update'])->whereNumber('id')->name('update');
    Route::post  ('/{id}/toggle',    [$g, 'togglePublish'])->whereNumber('id')->name('toggle');
    Route::delete('/{id}',           [$g, 'destroy'])->whereNumber('id')->name('destroy');
});

Route::prefix('meta-ads')->name('meta-ads.')->group(function () {
    // Platform fallback Meta Ads (CTWA) credentials — used when a
    // workspace hasn't connected its own keys on /meta-ads.
    //
    // The rest of this section (list / create / edit / analytics) was a static
    // mock with no menu entry and was removed; only the real credentials screen
    // remains.
    Route::get('/keys',            [\App\Http\Controllers\Admin\MetaAdsKeysController::class, 'edit'])->name('keys');
    Route::post('/keys',           [\App\Http\Controllers\Admin\MetaAdsKeysController::class, 'update'])->name('keys.update');
});

Route::prefix('support')->name('support.')->group(function () {
    $inb = \App\Http\Controllers\Admin\Support\InboxController::class;
    $ti  = \App\Http\Controllers\Admin\Support\TeamInboxController::class;
    $ag  = \App\Http\Controllers\Admin\Support\AgentsController::class;
    $sla = \App\Http\Controllers\Admin\Support\SlaController::class;
    $cust= \App\Http\Controllers\Admin\Support\CustomersController::class;
    $pb  = \App\Http\Controllers\Admin\Support\PlaybooksController::class;
    $rep = \App\Http\Controllers\Admin\Support\ReportsController::class;

    Route::get   ('/',                       [$inb, 'index'])->name('index');
    Route::get   ('/team-inbox',             [$ti, 'index'])->name('team');
    Route::get   ('/agents',                 [$ag, 'index'])->name('agents');
    Route::post  ('/agents',                 [$ag, 'store'])->name('agents.store');
    Route::post  ('/agents/{id}/toggle',     [$ag, 'toggle'])->whereNumber('id')->name('agents.toggle');
    Route::delete('/agents/{id}',            [$ag, 'destroy'])->whereNumber('id')->name('agents.destroy');
    Route::get   ('/sla',                    [$sla, 'index'])->name('sla');
    Route::post  ('/sla/policies',           [$sla, 'storePolicy'])->name('sla.store');
    Route::patch ('/sla/policies/{id}',      [$sla, 'updatePolicy'])->whereNumber('id')->name('sla.update');
    Route::delete('/sla/policies/{id}',      [$sla, 'destroyPolicy'])->whereNumber('id')->name('sla.destroy');
    Route::get   ('/customers',              [$cust, 'index'])->name('customers');
    Route::get   ('/customers/{wsId}',       [$cust, 'show'])->whereNumber('wsId')->name('customers.show');
    Route::get   ('/playbooks',              [$pb, 'index'])->name('playbooks');
    Route::post  ('/playbooks',              [$pb, 'store'])->name('playbooks.store');
    Route::post  ('/playbooks/{id}/toggle',  [$pb, 'toggle'])->whereNumber('id')->name('playbooks.toggle');
    Route::delete('/playbooks/{id}',         [$pb, 'destroy'])->whereNumber('id')->name('playbooks.destroy');
    Route::get   ('/reports',                [$rep, 'index'])->name('reports');
    Route::get   ('/reports/export.csv',     [$rep, 'exportCsv'])->name('reports.export');
    // Per-ticket actions (defined LAST so /team-inbox /sla /agents above
    // don't get swallowed by the {id} wildcard).
    Route::get   ('/{id}',                   [$inb, 'show'])->whereNumber('id')->name('show');
    Route::post  ('/{id}/reply',             [$inb, 'reply'])->whereNumber('id')->name('reply');
    Route::post  ('/{id}/assign',            [$inb, 'assign'])->whereNumber('id')->name('assign');
    Route::post  ('/{id}/status',            [$inb, 'setStatus'])->whereNumber('id')->name('status');
    Route::post  ('/{id}/priority',          [$inb, 'setPriority'])->whereNumber('id')->name('priority');
    Route::post  ('/{id}/move',              [$ti, 'move'])->whereNumber('id')->name('move');
    Route::post  ('/{tid}/run-playbook/{pid}', [$pb, 'runOnTicket'])->whereNumber('tid')->whereNumber('pid')->name('playbooks.run');
});

Route::get('/analytics', [AdminPagesController::class, 'analytics'])->name('analytics');
Route::prefix('audit-log')->name('audit-log.')->group(function () {
    Route::get('/',        [AuditLogController::class, 'index'])->name('index');
    Route::get('/export',  [AuditLogController::class, 'export'])->name('export');
    Route::get('/{id}',    [AuditLogController::class, 'show'])->whereNumber('id')->name('show');
});
Route::prefix('security')->name('security.')->group(function () {
    Route::get('/',                        [SecurityController::class, 'index'])->name('index');
    Route::patch('/',                      [SecurityController::class, 'update'])->name('update');
    // Phone-mask toggle — its own dead-simple POST (JS-free, own <form>).
    Route::post('/mask',                   [SecurityController::class, 'saveMask'])->name('mask');
    Route::post('/danger/revoke-sessions', [SecurityController::class, 'revokeAllSessions'])->name('danger.revoke');
    Route::post('/danger/force-reset',     [SecurityController::class, 'forcePasswordReset'])->name('danger.reset');
    Route::post('/danger/rotate-webhooks', [SecurityController::class, 'rotateWebhookSecrets'])->name('danger.rotate-webhooks');
    Route::post('/danger/halt',            [SecurityController::class, 'emergencyStopSends'])->name('danger.halt');
    Route::post('/danger/resume',          [SecurityController::class, 'emergencyResumeSends'])->name('danger.resume');
});

Route::prefix('storage')->name('storage.')->group(function () {
    Route::get ('/',     [StorageSettingsController::class, 'index'])->name('index');
    Route::post('/',     [StorageSettingsController::class, 'save'])->name('save');
    Route::post('/test', [StorageSettingsController::class, 'test'])->name('test');
});

// Real-time (Pusher) — platform-level broadcasting keys that power the live
// Team Inbox feed for every workspace.
Route::prefix('settings/realtime')->name('settings.realtime.')->group(function () {
    Route::get ('/',     [\App\Http\Controllers\Admin\RealtimeSettingsController::class, 'index'])->name('index');
    Route::post('/',     [\App\Http\Controllers\Admin\RealtimeSettingsController::class, 'save'])->name('save');
    Route::post('/test', [\App\Http\Controllers\Admin\RealtimeSettingsController::class, 'test'])->name('test');
});

Route::prefix('blog')->name('blog.')->group(function () {
    Route::get   ('/',                 [BlogController::class, 'index'])->name('index');
    Route::get   ('/create',           [BlogController::class, 'create'])->name('create');
    Route::post  ('/',                 [BlogController::class, 'store'])->name('store');
    Route::get   ('/{id}/edit',        [BlogController::class, 'edit'])->whereNumber('id')->name('edit');
    Route::put   ('/{id}',             [BlogController::class, 'update'])->whereNumber('id')->name('update');
    Route::post  ('/{id}/toggle',      [BlogController::class, 'toggle'])->whereNumber('id')->name('toggle');
    Route::delete('/{id}',             [BlogController::class, 'destroy'])->whereNumber('id')->name('destroy');
});

// Admin-curated Bot-Flow starter templates → tenants clone them on /flows.
Route::prefix('flow-templates')->name('flow-templates.')->group(function () {
    Route::get   ('/',                 [\App\Http\Controllers\Admin\FlowTemplateController::class, 'index'])->name('index');
    Route::get   ('/create',           [\App\Http\Controllers\Admin\FlowTemplateController::class, 'create'])->name('create');
    Route::post  ('/',                 [\App\Http\Controllers\Admin\FlowTemplateController::class, 'store'])->name('store');
    Route::get   ('/{id}/edit',        [\App\Http\Controllers\Admin\FlowTemplateController::class, 'edit'])->whereNumber('id')->name('edit');
    Route::put   ('/{id}',             [\App\Http\Controllers\Admin\FlowTemplateController::class, 'update'])->whereNumber('id')->name('update');
    Route::post  ('/{id}/toggle',      [\App\Http\Controllers\Admin\FlowTemplateController::class, 'toggle'])->whereNumber('id')->name('toggle');
    Route::delete('/{id}',             [\App\Http\Controllers\Admin\FlowTemplateController::class, 'destroy'])->whereNumber('id')->name('destroy');
});

Route::prefix('frontend')->name('frontend.')->group(function () {
    Route::get   ('/',                 [FrontendEditorController::class, 'index'])->name('index');
    Route::post  ('/toggle-frontend',  [FrontendEditorController::class, 'toggleFrontend'])->name('toggle-frontend');
    Route::post  ('/draft',            [FrontendEditorController::class, 'saveDraft'])->name('draft');
    Route::post  ('/preset',           [FrontendEditorController::class, 'applyPreset'])->name('preset');
    Route::post  ('/section',          [FrontendEditorController::class, 'toggleSection'])->name('section');
    Route::post  ('/reorder',          [FrontendEditorController::class, 'reorder'])->name('reorder');
    Route::post  ('/publish',          [FrontendEditorController::class, 'publish'])->name('publish');
    Route::post  ('/discard',          [FrontendEditorController::class, 'discard'])->name('discard');
    Route::post  ('/reset',            [FrontendEditorController::class, 'reset'])->name('reset');
    Route::post  ('/upload',           [FrontendEditorController::class, 'upload'])->name('upload');
});

Route::prefix('site-settings')->name('site-settings.')->group(function () {
    Route::get ('/', [SiteSettingsController::class, 'index'])->name('index');
    Route::post('/', [SiteSettingsController::class, 'update'])->name('update');
});

Route::prefix('contact-messages')->name('contact-messages.')->group(function () {
    Route::get   ('/',                 [ContactMessagesController::class, 'index'])->name('index');
    Route::post  ('/read-all',         [ContactMessagesController::class, 'markAllRead'])->name('read-all');
    Route::post  ('/{id}/read',        [ContactMessagesController::class, 'markRead'])->whereNumber('id')->name('read');
    Route::delete('/{id}',             [ContactMessagesController::class, 'destroy'])->whereNumber('id')->name('destroy');
});

// Platform notification feed for the admin (distinct from the user feed).
Route::prefix('notifications')->name('notifications.')->group(function () {
    Route::get   ('/',         [AdminNotificationController::class, 'index'])->name('index');
    Route::get   ('/recent',   [AdminNotificationController::class, 'recent'])->name('recent');
    Route::post  ('/read-all', [AdminNotificationController::class, 'markAllRead'])->name('read-all');
    Route::delete('/',         [AdminNotificationController::class, 'clearAll'])->name('clear');
});

// Extensions — purchase verify → upload ZIP → merge into this install.
//
// NOT "add-ons": that word is already taken by the BILLING add-ons at
// /admin/addons (à-la-carte feature packs a customer buys on top of a plan,
// stored as packages with type='addon'). This is a different thing entirely —
// shipping CODE into the installation — so it gets its own noun rather than
// shadowing a route that already means something else.
Route::prefix('extensions')->name('extensions.')->group(function () {
    $ac = \App\Http\Controllers\Admin\ExtensionController::class;
    Route::get   ('/',            [$ac, 'index'])->name('index');
    Route::post  ('/verify',      [$ac, 'verify'])->name('verify');
    Route::post  ('/upload',      [$ac, 'upload'])->name('upload');
    // Connect a standalone Instaflow deployment by URL + shared secret (no upload).
    Route::post  ('/instaflow/connect', [$ac, 'connectInstaflow'])->name('instaflow.connect');
    // Disconnect it — clears the stored URL / secret / connection flag.
    Route::post  ('/instaflow/disconnect', [$ac, 'disconnectInstaflow'])->name('instaflow.disconnect');
    Route::post  ('/{id}/toggle', [$ac, 'toggle'])->name('toggle');
    Route::delete('/{id}',        [$ac, 'destroy'])->name('destroy');
    // In-place modules (addon/<slug>/) — deactivate/re-activate without deleting files.
    Route::post  ('/module/{slug}/toggle', [$ac, 'moduleToggle'])->name('module.toggle');
});

// Updater — Envato purchase verify → backup → upload package → apply → migrate.
Route::prefix('update')->name('update.')->group(function () {
    $uc = \App\Http\Controllers\Admin\UpdateController::class;
    Route::get ('/',          [$uc, 'index'])->name('index');
    Route::post('/verify',    [$uc, 'verify'])->name('verify');
    Route::post('/backup',    [$uc, 'backup'])->name('backup');
    Route::post('/upload',    [$uc, 'upload'])->name('upload');
    Route::post('/apply',     [$uc, 'apply'])->name('apply');
    Route::post('/migrate',   [$uc, 'migrate'])->name('migrate');
    Route::post('/finalize',  [$uc, 'finalize'])->name('finalize');
    Route::post('/rollback',  [$uc, 'rollback'])->name('rollback');
});

Route::prefix('settings')->name('settings.')->group(function () {
    Route::get('/',                [AdminPagesController::class, 'settings'])->name('index');
    Route::get('/export',          [AdminPagesController::class, 'settingsExport'])->name('export');
    Route::post('/affiliate',      [AdminPagesController::class, 'settingsAffiliateUpdate'])->name('affiliate.update');
    Route::post('/providers',      [AdminPagesController::class, 'settingsProvidersUpdate'])->name('providers.update');
    Route::get('/general',         [AdminPagesController::class, 'settingGeneral'])->name('general');
    Route::patch('/general',       [AdminPagesController::class, 'settingGeneralUpdate'])->name('general.update');
    Route::get('/wadesk-message',  [AdminPagesController::class, 'settingWaDeskMessage'])->name('wadesk-message');
    // Quick "Update timing" — saves ONLY the sender-pacing fields (msg_gap /
    // batches_gap / bw_msg_gap / enable_batches) without re-submitting the
    // whole providers form, and pushes them to the Node bridge immediately.
    Route::post('/wadesk-message/pacing', [AdminPagesController::class, 'settingsPacingUpdate'])->name('pacing.update');
    // Coexistence webhook self-check — asks Meta which webhook fields the
    // platform App is subscribed to, so an admin can confirm smb_message_echoes
    // etc. are live (the usual cause of "phone-typed replies don't sync").
    Route::post('/wadesk-message/check-webhooks', [AdminPagesController::class, 'checkCoexistenceWebhooks'])->name('coexistence.check');
    // One-click subscribe of the coexistence fields on the Meta App (union with
    // whatever is already subscribed). Meta re-verifies the callback on submit.
    Route::post('/wadesk-message/subscribe-webhooks', [AdminPagesController::class, 'subscribeCoexistenceWebhooks'])->name('coexistence.subscribe');
    // One-click: build the registration-OTP authentication template in code and
    // submit it to Meta against the selected WABA device (no hand-crafting).
    Route::post('/wadesk-message/otp-template', [AdminPagesController::class, 'createOtpTemplate'])->name('otp-template.create');
    Route::get('/wallet-rules',    [AdminPagesController::class, 'settingWalletRules'])->name('wallet-rules');
    // Per-country × category credit rates (fair pricing) — saved separately
    // from the flat wallet-rules form so the existing save is untouched.
    Route::post('/message-rates',  [AdminPagesController::class, 'messageRatesUpdate'])->name('message-rates.update');
    Route::get('/message',         [AdminPagesController::class, 'settingMessage'])->name('message');
    Route::post('/message',        [AdminPagesController::class, 'settingMessageUpdate'])->name('message.update');
    Route::get('/mail',            [AdminPagesController::class, 'settingMail'])->name('mail');
    Route::patch('/mail',          [AdminPagesController::class, 'settingMailUpdate'])->name('mail.update');
    Route::post('/mail/test',      [AdminPagesController::class, 'settingMailTest'])->name('mail.test');
    Route::get('/integration',     [AdminPagesController::class, 'settingIntegration'])->name('integration');
    Route::post('/integration',    [AdminPagesController::class, 'settingIntegrationUpdate'])->name('integration.update');
    Route::get('/shopify',         [AdminPagesController::class, 'settingShopify'])->name('shopify');
    Route::post('/shopify',        [AdminPagesController::class, 'settingShopifyUpdate'])->name('shopify.update');
    Route::get('/woocommerce',     [AdminPagesController::class, 'settingWoocommerce'])->name('woocommerce');
    Route::post('/woocommerce',    [AdminPagesController::class, 'settingWoocommerceUpdate'])->name('woocommerce.update');
    Route::get('/hubspot',         [AdminPagesController::class, 'settingHubspot'])->name('hubspot');
    Route::post('/hubspot',        [AdminPagesController::class, 'settingHubspotUpdate'])->name('hubspot.update');
    Route::get('/slack',           [AdminPagesController::class, 'settingSlack'])->name('slack');
    Route::post('/slack',          [AdminPagesController::class, 'settingSlackUpdate'])->name('slack.update');
    Route::get('/trello',          [AdminPagesController::class, 'settingTrello'])->name('trello');
    Route::post('/trello',         [AdminPagesController::class, 'settingTrelloUpdate'])->name('trello.update');
    Route::get('/social-login',    [AdminPagesController::class, 'settingSocialLogin'])->name('social-login');
    Route::post('/social-login',   [AdminPagesController::class, 'settingSocialLoginUpdate'])->name('social-login.update');
    Route::get('/google-calendar', [AdminPagesController::class, 'settingGoogleCalendar'])->name('google-calendar');
    Route::post('/google-calendar',[AdminPagesController::class, 'settingGoogleCalendarUpdate'])->name('google-calendar.update');
    Route::get('/catalog',         [AdminPagesController::class, 'settingCatalog'])->name('catalog');
    Route::patch('/catalog',       [AdminPagesController::class, 'settingCatalogUpdate'])->name('catalog.update');
    Route::get('/pwa',             [AdminPagesController::class, 'settingPwa'])->name('pwa');
    Route::patch('/pwa',           [AdminPagesController::class, 'settingPwaUpdate'])->name('pwa.update');
    // Team-Inbox PWA push — one-click VAPID key generation (replaces the old
    // teaminbox:push-setup artisan command; no SSH needed).
    Route::post('/pwa/generate-keys', [AdminPagesController::class, 'settingPwaGenerateKeys'])->name('pwa.generate-keys');
    Route::get('/privacy',         [AdminPagesController::class, 'settingPrivacy'])->name('privacy');
    Route::patch('/privacy',       [AdminPagesController::class, 'settingPrivacyUpdate'])->name('privacy.update');
    Route::get('/auth-pages',      [AdminPagesController::class, 'settingAuthPages'])->name('auth-pages');
    Route::post('/auth-pages',     [AdminPagesController::class, 'settingAuthPagesUpdate'])->name('auth-pages.update');
    Route::post('/auth-pages/variant', [AdminPagesController::class, 'settingAuthPagesVariant'])->name('auth-pages.variant');
    // Appearance — recolour the whole dashboard (user + admin) via theme tokens.
    Route::get('/appearance',       [\App\Http\Controllers\Admin\AppearanceController::class, 'index'])->name('appearance');
    Route::post('/appearance',      [\App\Http\Controllers\Admin\AppearanceController::class, 'update'])->name('appearance.update');
    Route::post('/appearance/reset',[\App\Http\Controllers\Admin\AppearanceController::class, 'reset'])->name('appearance.reset');
    Route::get('/menu-order',      [AdminPagesController::class, 'settingMenuOrder'])->name('menu-order');
    Route::post('/menu-order',     [AdminPagesController::class, 'settingMenuOrderUpdate'])->name('menu-order.update');
    Route::get('/auth-pages/preview/{page}', [AdminPagesController::class, 'settingAuthPagesPreview'])->name('auth-pages.preview');
    Route::post('/auth-pages/inline',             [AdminPagesController::class, 'settingAuthPagesInline'])->name('auth-pages.inline');
    Route::post('/auth-pages/inline-media',       [AdminPagesController::class, 'settingAuthPagesInlineMedia'])->name('auth-pages.inline-media');
    Route::post('/auth-pages/inline-media-clear', [AdminPagesController::class, 'settingAuthPagesInlineMediaClear'])->name('auth-pages.inline-media-clear');
    Route::get('/analytics',       [AdminPagesController::class, 'settingAnalytics'])->name('analytics');
    Route::patch('/analytics',     [AdminPagesController::class, 'settingAnalyticsUpdate'])->name('analytics.update');
    Route::get('/seo',             [AdminPagesController::class, 'settingSeo'])->name('seo');
    Route::patch('/seo',           [AdminPagesController::class, 'settingSeoUpdate'])->name('seo.update');
    Route::get('/seo/sitemap/download', [\App\Http\Controllers\SitemapController::class, 'download'])->name('seo.sitemap.download');
    Route::get('/footer',          [AdminPagesController::class, 'settingFooter'])->name('footer');
    Route::post('/footer',         [AdminPagesController::class, 'settingFooterUpdate'])->name('footer.update');
    Route::get('/custom',          [AdminPagesController::class, 'settingCustom'])->name('custom');
    Route::post('/custom',         [AdminPagesController::class, 'settingCustomUpdate'])->name('custom.update');
});
