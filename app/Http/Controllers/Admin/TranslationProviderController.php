<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TranslationProvider;
use App\Services\Translation\TranslationProviderManager;
use App\Support\Audit;
use Illuminate\Http\Request;

/**
 * Admin /translation-providers — same SnapNest-style UX as
 * payment-gateways and api-keys: every driver pre-seeded, admin
 * fills in credentials, picks default, toggles Active.
 *
 *   GET   /admin/translation-providers              → list
 *   PATCH /admin/translation-providers/{id}         → save creds + mode
 *   POST  /admin/translation-providers/{id}/toggle  → activate / deactivate
 *   POST  /admin/translation-providers/{id}/default → set as default
 */
class TranslationProviderController extends Controller
{
    public function __construct(private readonly TranslationProviderManager $manager) {}

    public function index()
    {
        $providers = TranslationProvider::orderBy('sort_order')->get()->map(function (TranslationProvider $p) {
            $p->credential_fields     = $this->manager->credentialFieldsFor($p->slug);
            $p->credentials_decrypted = $p->getDecryptedCredentials();
            return $p;
        });

        return view('admin.translation-providers.index', compact('providers'));
    }

    public function update(Request $request, int $id)
    {
        $row    = TranslationProvider::findOrFail($id);
        $fields = $this->manager->credentialFieldsFor($row->slug);

        $data = $request->validate([
            'credentials'   => ['nullable', 'array'],
            'credentials.*' => ['nullable', 'string', 'max:1024'],
            'sort_order'    => ['nullable', 'integer', 'min:0'],
        ]);

        // Merge: only overwrite a key when a non-empty value was sent.
        // Lets password placeholders ("leave blank to keep") work.
        $existing = $row->getDecryptedCredentials();
        $incoming = $data['credentials'] ?? [];
        $merged   = $existing;
        foreach ($fields as $key => $spec) {
            if (array_key_exists($key, $incoming) && $incoming[$key] !== '') {
                $merged[$key] = $incoming[$key];
            }
        }
        $row->setEncryptedCredentials($merged);
        $row->sort_order = $data['sort_order'] ?? $row->sort_order;
        $row->save();

        // Record WHICH credential keys changed (never the values —
        // they're encrypted secrets). Audit row only stores key names.
        $changedKeys = array_keys(array_filter($incoming, fn ($v) => $v !== '' && $v !== null));
        Audit::log('admin.translation_provider.updated', [
            'resource' => $row,
            'meta'     => ['credential_keys_set' => $changedKeys],
        ]);

        return back()->with('success', $row->name . ' settings saved.');
    }

    public function toggle(int $id)
    {
        $row = TranslationProvider::findOrFail($id);

        // If we're activating, make sure every required credential
        // field has a value — otherwise the driver will fail silently.
        if (!$row->is_active) {
            $required = collect($this->manager->credentialFieldsFor($row->slug))
                ->filter(fn ($spec) => !empty($spec['required']))->keys();
            $creds   = $row->getDecryptedCredentials();
            $missing = $required->filter(fn ($k) => empty($creds[$k]))->all();
            if (!empty($missing)) {
                Audit::log('admin.translation_provider.toggle_blocked', [
                    'resource' => $row,
                    'result'   => 'failure',
                    'meta'     => ['missing_keys' => array_values($missing)],
                ]);
                return back()->with('error', 'Configure required fields first: ' . implode(', ', $missing));
            }
        }
        $row->update(['is_active' => !$row->is_active]);
        Audit::log($row->is_active ? 'admin.translation_provider.activated' : 'admin.translation_provider.deactivated', [
            'resource' => $row,
        ]);
        return back()->with('success', $row->is_active ? 'Activated.' : 'Deactivated.');
    }

    public function setDefault(int $id)
    {
        $row = TranslationProvider::findOrFail($id);
        if (!$row->is_active) {
            return back()->with('error', 'Activate this provider before making it the default.');
        }
        \Illuminate\Support\Facades\DB::transaction(function () use ($row) {
            TranslationProvider::query()->update(['is_default' => false]);
            $row->forceFill(['is_default' => true])->save();
        });
        Audit::log('admin.translation_provider.default_changed', ['resource' => $row]);
        return back()->with('success', $row->name . ' is now the default translation provider.');
    }

    /**
     * Toggle the official-only lockdown. When on, the fallback chain
     * skips MyMemory / Google GTX / LibreTranslate and only routes
     * through DeepL / Google Cloud. Refuses to turn on until at least
     * one official driver is active + has credentials.
     */
    public function lockdown(\Illuminate\Http\Request $request)
    {
        $current = (bool) \App\Models\SystemSetting::get('translation.official_only', false);
        $turningOn = !$current;

        if ($turningOn) {
            // Sanity check: at least one paid/official driver must be
            // active AND have an API key. Otherwise we'd brick translation.
            $officialSlugs = array_diff(
                array_keys(\App\Services\Translation\TranslationProviderManager::DRIVER_MAP),
                \App\Services\Translation\TranslationProviderManager::UNOFFICIAL_SLUGS,
            );
            $hasUsable = \App\Models\TranslationProvider::query()
                ->whereIn('slug', $officialSlugs)
                ->where('is_active', true)
                ->get()
                ->contains(fn ($p) => !empty($p->getDecryptedCredentials()));
            if (!$hasUsable) {
                return back()->with('error', 'Activate and configure at least one official paid provider (DeepL or Google Cloud) before enabling lockdown.');
            }
        }

        \App\Models\SystemSetting::set('translation.official_only', $turningOn, 'bool',
            'When true, restricts auto-reply translation to official paid APIs only (DeepL, Google Cloud).');

        Audit::log($turningOn ? 'admin.translation.lockdown_enabled' : 'admin.translation.lockdown_disabled', [
            'meta' => ['previous' => $current, 'now' => $turningOn],
        ]);

        return back()->with('success', $turningOn
            ? 'Official-providers-only mode is ON. Free drivers will be skipped.'
            : 'Official-providers-only mode is OFF. Free drivers are back in the fallback chain.');
    }
}
