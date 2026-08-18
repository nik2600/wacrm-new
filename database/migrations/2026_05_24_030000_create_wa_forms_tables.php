<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // wa_forms — workspace-scoped reusable WhatsApp Flow definitions.
        // Each row is one form (Meta's "Flow") that the flow-builder's
        // `wa_form` node references by id when it fires.
        Schema::create('wa_forms', function (Blueprint $t) {
            $t->id();
            $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $t->string('title', 140);            // operator-facing label
            $t->text('purpose')->nullable();     // description / what it's for
            $t->string('slug', 160);             // workspace-unique
            $t->string('audience_type', 32)->default('lead_capture');
            // lead_capture | survey | appointment | feedback | onboarding | other

            // Submission limits — operator can cap how many times a single
            // contact can submit (anti-spam, draw entries, etc).
            $t->unsignedInteger('submission_cap')->default(0); // 0 = unlimited
            $t->text('cap_reached_note')->nullable();          // shown when cap hit

            $t->string('send_button_label', 40)->default('Send');
            $t->text('thank_you_note')->nullable();

            // Form definition — the screens + fields. Stored as JSON so the
            // builder can evolve without migrations. Shape:
            //   {
            //     screens: [
            //       { id, label, fields: [
            //           { id, kind, label, hint, required, options[]?, … }
            //       ]}
            //     ],
            //     theme: { primary, ... },
            //     post_submit: { kind: 'message'|'webhook'|'flow_advance', ... }
            //   }
            $t->json('definition_json')->nullable();

            // Publishing state — every form has a draft until pushed live.
            // Once published, `meta_flow_id` is what we reference when
            // sending an interactive flow message via the WABA API.
            $t->string('status', 16)->default('draft'); // draft | published | paused
            $t->string('meta_flow_id', 80)->nullable();
            $t->timestamp('published_at')->nullable();
            $t->text('publish_error')->nullable();

            $t->unsignedInteger('submission_count')->default(0);
            $t->timestamps();
            $t->softDeletes();

            $t->unique(['workspace_id', 'slug']);
            $t->index(['workspace_id', 'status']);
        });

        // wa_form_submissions — one row per completed form fill.
        // Surfaces in the form detail page + /team-inbox conversation
        // when the customer who filled it has an open thread.
        Schema::create('wa_form_submissions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('form_id')->constrained('wa_forms')->cascadeOnDelete();
            $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $t->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('conversation_id')->nullable()->constrained()->nullOnDelete();

            $t->string('flow_token', 64)->nullable()->index();
            $t->string('caller_phone', 32)->nullable();
            $t->json('answers_json')->nullable();   // { field_id: value, ... }
            $t->json('meta_payload')->nullable();   // raw webhook for debugging
            $t->timestamp('submitted_at')->nullable();
            $t->timestamps();

            $t->index(['form_id', 'submitted_at']);
            $t->index(['workspace_id', 'submitted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_form_submissions');
        Schema::dropIfExists('wa_forms');
    }
};
