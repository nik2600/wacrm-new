<?php

namespace App\Http\Controllers;

use App\Models\ApiKey;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * /developers — the workspace owner's REST API key console. Mint, list,
 * and revoke customer keys that authenticate against /api/v1.
 *
 * The raw key is shown to the customer ONCE (flashed to the session on
 * mint); only its sha256 hash is stored, so it can never be revealed
 * again. Every query is scoped to the caller's current workspace.
 */
class DeveloperApiController extends Controller
{
    public function index(): View
    {
        $keys = ApiKey::query()
            ->where('workspace_id', $this->workspaceId())
            ->orderByDesc('id')
            ->get();

        return view('user.developers.index', compact('keys'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
        ]);

        // One active key per workspace. If a live key already exists, refuse —
        // the customer must revoke it before minting a replacement.
        $hasActive = ApiKey::query()
            ->where('workspace_id', $this->workspaceId())
            ->whereNull('revoked_at')
            ->exists();

        if ($hasActive) {
            return redirect()
                ->route('user.developers')
                ->with('error', 'You already have an active API key. Revoke it before creating a new one — only one key per workspace is allowed.');
        }

        [, $rawKey] = ApiKey::mint(
            $this->workspaceId(),
            Auth::id(),
            $data['name'],
            null,
            Auth::id(),
        );

        // Shown exactly once — the view reads this from the flash bag and
        // we never have the plaintext again (only its hash is stored).
        return redirect()
            ->route('user.developers')
            ->with('api_key_once', $rawKey)
            ->with('status', 'API key created. Copy it now — it won\'t be shown again.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $key = ApiKey::query()
            ->where('workspace_id', $this->workspaceId())
            ->findOrFail($id);

        if (! $key->revoked_at) {
            $key->forceFill(['revoked_at' => now()])->save();
        }

        return redirect()
            ->route('user.developers')
            ->with('status', 'API key revoked. Any app using it will stop working immediately.');
    }

    /** Current workspace the caller is acting inside. */
    private function workspaceId(): int
    {
        return (int) Auth::user()->current_workspace_id;
    }
}
