<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 6 — Settings tabs end-to-end. Single migration covering
 * everything we'd otherwise split across nine files:
 *
 *   users               → 2FA columns + theme preference
 *   workspaces          → brand_*, notification_prefs JSON
 *   packages            → allow_byok_ai_keys + ai_token_limit_monthly
 *   admin_ai_keys       → global admin-owned API keys (fallback when
 *                         a plan doesn't allow BYOK)
 *   ai_token_usage      → ledger of tokens spent, per workspace,
 *                         used to enforce monthly cap
 *   security_audit_log  → records 2FA enable/disable, session revoke,
 *                         password change, etc. Linked from the
 *                         security tab.
 *
 * Workspace's `notification_prefs` JSON shape:
 *   {
 *     "device_disconnected":      { "inapp": true,  "email": true,  "slack": false },
 *     "campaign_completed":       { "inapp": true,  "email": false, "slack": false },
 *     "wallet_low_balance":       { "inapp": true,  "email": true,  "slack": true  },
 *     "new_customer_reply":       { "inapp": true,  "email": false, "slack": false },
 *     "weekly_summary":           { "inapp": false, "email": true,  "slack": false }
 *   }
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('two_factor_enabled')->default(false)->after('password');
            $table->text('two_factor_secret')->nullable()->after('two_factor_enabled');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_secret');
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_confirmed_at');
            // paper | bright | dark
            $table->string('theme_preference', 16)->default('paper')->after('two_factor_recovery_codes');
        });

        Schema::table('workspaces', function (Blueprint $table) {
            $table->string('brand_primary', 16)->nullable()->after('plan_overrides');
            $table->string('brand_accent', 16)->nullable()->after('brand_primary');
            $table->string('brand_background', 16)->nullable()->after('brand_accent');
            $table->string('brand_logo_path', 255)->nullable()->after('brand_background');
            $table->string('brand_favicon_path', 255)->nullable()->after('brand_logo_path');
            $table->json('notification_prefs')->nullable()->after('brand_favicon_path');
        });

        Schema::table('packages', function (Blueprint $table) {
            // Plan-level flag: does the workspace get to plug in their
            // own AI provider keys? When false, AiKeyResolver falls
            // back to the admin's global keys.
            $table->boolean('allow_byok_ai_keys')->default(false)->after('webhooks_limit');
            // Monthly hard cap on AI tokens spent against admin keys.
            // null = unlimited (only set this for enterprise-tier plans).
            $table->unsignedInteger('ai_token_limit_monthly')->nullable()->after('allow_byok_ai_keys');
        });

        // Admin-owned global AI keys. One row per provider. Used when
        // the workspace's plan doesn't grant BYOK (or BYOK is on but
        // the workspace hasn't set their own key for a provider).
        Schema::create('admin_ai_keys', function (Blueprint $table) {
            $table->id();
            // openai | anthropic | gemini | mistral
            $table->string('provider', 32)->unique();
            $table->string('name', 80);
            $table->text('api_key')->nullable();      // encrypted at rest via cast
            $table->string('default_model', 80)->nullable();
            $table->string('extra_config', 500)->nullable();  // JSON: organization_id, project_id, etc.
            $table->boolean('is_active')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Per-workspace AI token usage ledger. Inserted after every
        // AI provider call. Aggregated by month to enforce
        // packages.ai_token_limit_monthly.
        Schema::create('ai_token_usage', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id')->index();
            $table->string('provider', 32);
            $table->string('model', 80)->nullable();
            $table->unsignedInteger('prompt_tokens')->default(0);
            $table->unsignedInteger('completion_tokens')->default(0);
            $table->unsignedInteger('total_tokens')->default(0);
            // 'admin' | 'workspace' — which key paid for this call
            $table->string('billed_against', 16)->default('admin');
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['workspace_id', 'created_at']);
        });

        // Security audit log — same shape as SnapNest's. Captures who
        // did what, when, from where. Surfaced in the security tab.
        Schema::create('security_audit_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedBigInteger('workspace_id')->nullable()->index();
            $table->string('event', 64);          // two_factor_enabled, session_revoked, etc.
            $table->string('status', 16);         // success | failed | info
            $table->string('ip', 64)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->json('payload')->nullable();  // event-specific context
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_audit_log');
        Schema::dropIfExists('ai_token_usage');
        Schema::dropIfExists('admin_ai_keys');

        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn(['allow_byok_ai_keys', 'ai_token_limit_monthly']);
        });
        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropColumn([
                'brand_primary', 'brand_accent', 'brand_background',
                'brand_logo_path', 'brand_favicon_path', 'notification_prefs',
            ]);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'two_factor_enabled', 'two_factor_secret',
                'two_factor_confirmed_at', 'two_factor_recovery_codes',
                'theme_preference',
            ]);
        });
    }
};
