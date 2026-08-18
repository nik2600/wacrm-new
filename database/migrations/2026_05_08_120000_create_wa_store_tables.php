<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WhatsApp Store + provider abstraction — four tables in one feature add.
 *
 * Why one migration, not four:
 *   they're inert without each other (a wa_order references a wa_product
 *   and a wa_storefront; a wa_storefront belongs to a workspace whose
 *   provider lives in wa_provider_configs). One migration = one cohesive
 *   add, easy to roll back as a unit.
 *
 * The shape is deliberately mode-agnostic: products live in our DB
 * regardless of whether the workspace sends via WABA / Baileys / Twilio.
 * WABA mode mirrors them up to Meta's catalog as a side-effect, but
 * losing that mirror doesn't lose the data. Same for orders — the
 * `source` column tells us how each one came in (WABA `messages.order`
 * webhook, storefront `wa.me` deep-link parser, Twilio inbound) so the
 * /orders inbox can render any of them in one place.
 */
return new class extends Migration {
    public function up(): void
    {
        // ---- wa_provider_configs : per-workspace provider + creds ----
        // The big fix from the old codebase: the workspace's chosen
        // provider lives in EXACTLY ONE place (this row) instead of
        // smeared across .env, GeneralSetting and UserWaba. The
        // dispatcher reads provider + credentials_json from here; if
        // no row exists, it falls back to admin-level .env defaults.
        Schema::create('wa_provider_configs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id');
            $table->string('provider', 16);            // 'waba' | 'baileys' | 'twilio'
            $table->string('status', 16)->default('pending'); // 'pending' | 'connected' | 'disconnected' | 'failed'
            $table->json('credentials_json')->nullable();      // encrypted blob — keys per provider
            $table->json('meta_json')->nullable();             // catalog_id, business_id, last sync etc.
            $table->string('phone_number', 32)->nullable();    // for display only
            $table->string('display_label', 64)->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('last_health_at')->nullable();
            $table->timestamps();

            $table->unique('workspace_id');
            $table->index(['provider', 'status']);
            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
        });

        // ---- wa_products : the source of truth, mode-independent ----
        Schema::create('wa_products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('user_id');         // creator
            $table->string('sku', 96)->nullable();
            $table->string('name', 191);
            $table->string('slug', 191);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('price_minor')->default(0); // paise / cents
            $table->string('currency_code', 3)->default('INR');
            $table->string('image_url', 1024)->nullable();
            $table->json('gallery_json')->nullable();      // extra images
            $table->boolean('in_stock')->default(true);
            $table->unsignedInteger('stock_qty')->nullable(); // null = unlimited
            $table->unsignedInteger('sort_order')->default(0);
            // Optional Meta catalog mirror — populated only when the
            // workspace is on WABA mode and catalog sync ran.
            $table->string('meta_retailer_id', 96)->nullable();
            $table->string('meta_sync_status', 16)->nullable(); // 'synced' | 'pending' | 'error'
            $table->timestamp('meta_synced_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['workspace_id', 'slug']);
            $table->index(['workspace_id', 'in_stock', 'sort_order']);
            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        // ---- wa_storefronts : per-workspace storefront settings ----
        // Default subdomain `<slug>.<host>` always works; custom_domain
        // is opt-in (nullable, unique). Theme is a free-form key
        // mapped to a Blade view at runtime — this lets us add themes
        // without migrations.
        Schema::create('wa_storefronts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id');
            $table->string('slug', 64)->unique();
            $table->string('custom_domain', 191)->nullable()->unique();
            $table->boolean('custom_domain_verified')->default(false);
            $table->string('theme_key', 32)->default('aurora');
            $table->boolean('enabled')->default(true);
            $table->json('settings_json')->nullable();   // logo_url, brand_color, hero_text, footer_text
            $table->timestamps();

            $table->unique('workspace_id');
            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
        });

        // ---- wa_orders : every order from every channel ----
        // One row per order regardless of how it came in. The /orders
        // inbox queries this table and renders source-specific badges.
        Schema::create('wa_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id');
            $table->string('source', 16);              // 'waba' | 'storefront' | 'twilio' | 'manual'
            $table->string('customer_phone', 32);
            $table->string('customer_name', 191)->nullable();
            $table->string('customer_email', 191)->nullable();
            $table->json('items_json');                // array of {product_id?, retailer_id?, name, qty, price_minor}
            $table->unsignedBigInteger('total_minor')->default(0);
            $table->string('currency_code', 3)->default('INR');
            $table->string('status', 16)->default('new'); // new | confirmed | paid | shipped | cancelled
            $table->string('payment_link', 1024)->nullable();
            $table->text('notes')->nullable();
            $table->string('wa_message_id', 191)->nullable();   // upstream wamid
            $table->unsignedBigInteger('storefront_id')->nullable();
            $table->json('meta_json')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'status', 'created_at']);
            $table->index(['workspace_id', 'source']);
            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('storefront_id')->references('id')->on('wa_storefronts')->nullOnDelete();
        });

        // ---- system_settings seed for the multi-provider switch ----
        //
        // A FRESH install comes up on the official WhatsApp Cloud API only.
        // The Unofficial API and Twilio stay off until an admin ticks them in
        // /admin/settings — the Unofficial engine is testing-only and carries
        // ban risk, so it must never be what a brand-new install sends over by
        // accident.
        //
        // These are seeded HERE, in the original migration, rather than in a
        // new one on purpose: this migration has already run on every existing
        // install, so it will not re-run and cannot change a live site's
        // engines. Only genuinely fresh installs pick up the new defaults.
        // insertOrIgnore keeps it a no-op if a row somehow already exists.
        $seed = [
            ['allowed_send_methods', 'json',   json_encode(['waba']),
                'Which send providers are enabled platform-wide. Workspaces can only connect to providers in this list.'],
            ['default_send_method',  'string', 'waba',
                'Default engine for sends that do not pin a sender.'],
            // Back-compat visual slug of default_send_method ('business-api' = waba).
            ['active_engine_slug',   'string', 'business-api',
                'Default engine card (back-compat: the visual slug of default_send_method).'],
        ];

        foreach ($seed as [$key, $type, $value, $description]) {
            \DB::table('system_settings')->insertOrIgnore([
                'key'         => $key,
                'type'        => $type,
                'value'       => $value,
                'description' => $description,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_orders');
        Schema::dropIfExists('wa_storefronts');
        Schema::dropIfExists('wa_products');
        Schema::dropIfExists('wa_provider_configs');
        \DB::table('system_settings')
            ->whereIn('key', ['allowed_send_methods', 'default_send_method', 'active_engine_slug'])
            ->delete();
    }
};
