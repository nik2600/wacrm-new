<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Store the extension's full extension.json alongside its file list.
 *
 * An extension has to contribute more than files: a nav entry in the admin
 * sidebar, plan feature flags, plan limit fields. Those have to be readable
 * by CORE code (the sidebar, the package form) without core knowing anything
 * about the specific extension — otherwise "Instagram" stays hardcoded in
 * core and nothing was actually decoupled.
 *
 * Declaring them as DATA in the manifest, rather than as code the extension
 * executes, keeps installing an extension from being arbitrary code execution
 * at boot: core reads a JSON blob it controls the interpretation of.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('extensions', function (Blueprint $table) {
            $table->json('manifest')->nullable()->after('files');
        });
    }

    public function down(): void
    {
        Schema::table('extensions', function (Blueprint $table) {
            $table->dropColumn('manifest');
        });
    }
};
