<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // google_form_sessions — pause/resume mapping for the Google Form
        // flow node. When the flow reaches a google_form node, we send the
        // form URL via WhatsApp, then write a row here (form_id + flow
        // session_id + contact). The Apps Script the user paste-installed
        // on their Google Form fires our webhook on submit; the webhook
        // looks up the row, copies answers into session vars, and resumes
        // the flow on the "submitted" port.
        Schema::create('google_form_sessions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $t->string('flow_session_id', 64)->index();   // matches flow_sessions.session_id
            $t->string('google_form_id', 128)->index();    // Google's form ID
            $t->string('contact_phone', 32)->nullable()->index();
            $t->string('save_variable', 64)->nullable();   // node-data key — root var to attach
            $t->string('resume_port', 32)->default('submitted');
            $t->json('answers_json')->nullable();          // populated on submit
            $t->timestamp('submitted_at')->nullable();
            $t->timestamp('expires_at')->nullable()->index();
            $t->timestamps();

            $t->index(['workspace_id', 'google_form_id']);
        });

        // google_form_sessions.token — opaque per-row token used in the
        // Apps Script webhook URL so the script doesn't need full
        // workspace credentials. Each form-send mints a fresh row.
        Schema::table('google_form_sessions', function (Blueprint $t) {
            $t->string('webhook_token', 64)->unique();
        });

        // Extend the global google_calendar_scopes setting to include
        // Drive (for sharing copied docs), Sheets (read+write), Docs
        // (modify), Forms (read responses + list metadata). Existing
        // calendar scope is preserved so already-connected workspaces
        // keep working — we just need them to re-consent to pick up
        // the new scopes (handled on /google-account).
        if (Schema::hasTable('system_settings')) {
            $current = (string) DB::table('system_settings')
                ->where('key', 'google_calendar_scopes')
                ->value('value');

            // Pull the canonical set straight from the service so this never
            // drifts from DEFAULT_SCOPES (which now requests full `drive` — not
            // drive.file — so the flow-builder pickers can list the user's own
            // files, plus openid/userinfo for the connected-account identity).
            $needed = preg_split('/\s+/', \App\Services\GoogleCalendar\GoogleCalendarService::DEFAULT_SCOPES) ?: [];

            // Merge — keep anything custom the admin already added.
            $existing = preg_split('/\s+/', trim($current)) ?: [];
            $merged = array_values(array_unique(array_filter(array_merge($existing, $needed))));
            $newScopes = implode(' ', $merged);

            if ($newScopes !== $current) {
                // Set type=string explicitly: a bare insert defaulted the type
                // column to a value that cast the scope URLs to int(0), which
                // silently broke OAuth (scope=0). Pinning 'string' prevents it.
                DB::table('system_settings')->updateOrInsert(
                    ['key' => 'google_calendar_scopes'],
                    ['value' => $newScopes, 'type' => 'string', 'updated_at' => now()]
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('google_form_sessions');
        // We deliberately don't shrink google_calendar_scopes on rollback —
        // a partial roll-forward of these scopes is safer than leaving
        // existing connected workspaces with a wider grant than the
        // setting claims.
    }
};
