<?php

namespace App\Http\Controllers;

use App\Models\WaProductReview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Merchant moderation of storefront product reviews (S6). Reviews arrive
 * `pending` from the public storefront and only appear once approved.
 */
class WaReviewController extends Controller
{
    public function index(Request $request)
    {
        $wsId = Auth::user()?->current_workspace_id;
        abort_unless($wsId, 403);

        $status = in_array($request->query('status'), ['pending', 'approved', 'rejected'], true)
            ? $request->query('status') : 'pending';

        return view('user.store.reviews.index', [
            'reviews' => WaProductReview::with('product')
                ->where('workspace_id', $wsId)
                ->where('status', $status)
                ->orderByDesc('id')
                ->paginate(30)
                ->withQueryString(),
            'status'  => $status,
            'counts'  => [
                'pending'  => WaProductReview::where('workspace_id', $wsId)->where('status', 'pending')->count(),
                'approved' => WaProductReview::where('workspace_id', $wsId)->where('status', 'approved')->count(),
                'rejected' => WaProductReview::where('workspace_id', $wsId)->where('status', 'rejected')->count(),
            ],
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $wsId = Auth::user()?->current_workspace_id;
        abort_unless($wsId, 403);
        $review = WaProductReview::where('workspace_id', $wsId)->findOrFail($id);

        $data = $request->validate(['status' => ['required', 'in:pending,approved,rejected']]);
        $review->forceFill(['status' => $data['status']])->save();

        return back()->with('status', 'Review ' . $data['status'] . '.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $wsId = Auth::user()?->current_workspace_id;
        abort_unless($wsId, 403);
        WaProductReview::where('workspace_id', $wsId)->where('id', $id)->delete();

        return back()->with('status', 'Review deleted.');
    }
}
