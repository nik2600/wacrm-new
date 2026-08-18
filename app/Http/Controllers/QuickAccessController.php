<?php

namespace App\Http\Controllers;

use App\Support\QuickAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Saves a user's dashboard "Quick access" tiles (5×2 grid). Per-user; stored
 * on users.quick_access. Returns the resolved tiles so the UI can re-render.
 */
class QuickAccessController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $items = $request->input('items', []);
        if (!is_array($items)) {
            $items = [];
        }

        $user = $request->user();
        $user->quick_access = QuickAccess::sanitize($items);
        $user->save();

        return response()->json([
            'ok'    => true,
            'tiles' => QuickAccess::forUser($user),
        ]);
    }
}
