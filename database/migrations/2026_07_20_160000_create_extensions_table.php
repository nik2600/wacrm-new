<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Installed EXTENSIONS — code shipped into this install.
 *
 * Deliberately NOT called "add-ons": that word already means the BILLING
 * add-ons at /admin/addons (a-la-carte feature packs on `packages`). Reusing
 * it would have shadowed a live route and confused two unrelated concepts.
 *
 * An extension is a ZIP that merges extra files into this installation —
 * Instagram is the first one. It is deliberately NOT the same thing as a plan
 * feature:
 *
 *   extension = "does this code exist on this server?"   (per installation, binary)
 *   plan      = "may this workspace use it, how much?"   (per tenant, tiered)
 *
 * Both are needed. Without the extension row the routes never register, so a
 * client who did not buy Instagram gets no nav entry and no dead links.
 * Without the plan flags every workspace on the cheapest tier would get the
 * whole add-on the moment it is installed.
 *
 * `files` is the manifest of everything the ZIP wrote, captured at install
 * time. It is what makes uninstall possible — without it we would be guessing
 * which files belong to the add-on and which are core.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('extensions', function (Blueprint $t) {
            $t->id();

            // Machine key from the ZIP's extension.json, e.g. "instagram". Unique:
            // installing the same add-on twice is an upgrade, not a second row.
            $t->string('slug', 64)->unique();
            $t->string('name', 128);
            $t->string('version', 32)->default('1.0.0');

            // Envato purchase code used to unlock it. Stored so the admin can
            // see WHICH licence activated this extension, and so a re-install can
            // be re-verified. Encrypted — it is a licence key, not public data.
            $t->text('purchase_code')->nullable();

            // active | disabled — disabled keeps the files but stops the routes
            // registering, so an admin can switch an extension off without a
            // full uninstall.
            $t->string('status', 16)->default('active');

            // Manifest of files written by the ZIP, relative to base_path().
            $t->longText('files')->nullable();

            $t->timestamp('installed_at')->nullable();
            $t->foreignId('installed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();

            $t->index(['slug', 'status'], 'ext_slug_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('extensions');
    }
};
