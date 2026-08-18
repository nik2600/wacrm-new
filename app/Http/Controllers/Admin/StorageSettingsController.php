<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\CloudStorageManager;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * /admin/storage — choose & configure a cloud media provider (S3 / Wasabi /
 * Bunny / Spaces / R2 / MinIO). When enabled, all client media uploads route to
 * the bucket; when off, everything stays on the local disk.
 */
class StorageSettingsController extends Controller
{
    public function index(CloudStorageManager $mgr)
    {
        return view('admin.storage.index', [
            'cfg'         => $mgr->configForForm(),
            'providers'   => CloudStorageManager::PROVIDERS,
            'enabled'     => $mgr->isEnabled(),
            'activeLabel' => $mgr->providerLabel(),
            'hasConfig'   => $mgr->hasProviderConfig(),
        ]);
    }

    public function save(Request $request, CloudStorageManager $mgr)
    {
        $this->validatePayload($request);
        $mgr->save($this->payload($request));

        return back()->with('success', __('Storage settings saved.'));
    }

    /** Save the posted config, then write+delete a probe object on the bucket. */
    public function test(Request $request, CloudStorageManager $mgr)
    {
        $this->validatePayload($request);
        $mgr->save($this->payload($request));

        $result = $mgr->testConnection();

        if ($request->expectsJson()) {
            return response()->json($result, $result['ok'] ? 200 : 422);
        }

        return back()->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    private function validatePayload(Request $request): void
    {
        $request->validate([
            'enabled'    => ['nullable', 'boolean'],
            'provider'   => ['required', Rule::in(array_keys(CloudStorageManager::PROVIDERS))],
            'visibility' => ['nullable', 'in:public,private'],
            'base_path'  => ['nullable', 'string', 'max:191'],
            'providers'  => ['nullable', 'array'],
        ]);
    }

    private function payload(Request $request): array
    {
        $provider = (string) $request->input('provider');

        // The form posts one flat `cfg[...]` field set; store it under the
        // chosen provider so the manager's per-provider structure is preserved.
        return [
            'enabled'    => $request->boolean('enabled'),
            'provider'   => $provider,
            'visibility' => (string) $request->input('visibility', 'private'),
            'base_path'  => (string) $request->input('base_path', ''),
            'providers'  => [$provider => (array) $request->input('cfg', [])],
        ];
    }
}
