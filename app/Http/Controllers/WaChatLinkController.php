<?php

namespace App\Http\Controllers;

use App\Models\WaChatLink;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * WhatsApp deep-link generator. The operator picks a number + a pre-
 * typed message, we mint /l/{slug} short links + click analytics.
 * Public redirect endpoint lives outside the auth group below.
 */
class WaChatLinkController extends Controller
{
    public function index(): View
    {
        $wsId = (int) (Auth::user()?->current_workspace_id ?? 0);

        $links = WaChatLink::query()
            ->where('workspace_id', $wsId)
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $stats = [
            'all'    => WaChatLink::where('workspace_id', $wsId)->count(),
            'active' => WaChatLink::where('workspace_id', $wsId)->where('status', 'active')->count(),
            'clicks' => (int) WaChatLink::where('workspace_id', $wsId)->sum('click_count'),
        ];
        return view('user.wa-links.index', compact('links', 'stats'));
    }

    public function create(): View
    {
        return view('user.wa-links.builder', ['link' => null, 'mode' => 'create']);
    }

    public function edit(int $id): View
    {
        $wsId = (int) (Auth::user()?->current_workspace_id ?? 0);
        $link = WaChatLink::where('workspace_id', $wsId)->findOrFail($id);
        return view('user.wa-links.builder', ['link' => $link, 'mode' => 'edit']);
    }

    public function apiSave(Request $request): JsonResponse
    {
        $user = Auth::user();
        $wsId = (int) ($user?->current_workspace_id ?? 0);
        if (!$wsId) return response()->json(['ok' => false, 'error' => 'no_workspace'], 400);

        $data = $request->validate([
            'id'              => 'nullable|integer',
            'name'            => 'required|string|max:140',
            'country_code'    => 'required|string|max:8',
            'phone_number'    => 'required|string|max:24',
            'welcome_message' => 'nullable|string|max:4000',
            'slug'            => 'nullable|string|max:64|regex:/^[a-z0-9-]+$/',
            'utm_source'      => 'nullable|string|max:80',
            'utm_medium'      => 'nullable|string|max:80',
            'utm_campaign'    => 'nullable|string|max:80',
            'expires_at'      => 'nullable|date',
            'status'          => 'nullable|in:active,paused',
        ]);

        // Normalise digits — strip everything non-numeric.
        $data['phone_number'] = preg_replace('/\D+/', '', $data['phone_number']);
        $cc = $data['country_code'];
        if ($cc !== '' && $cc[0] !== '+') $cc = '+' . preg_replace('/\D+/', '', $cc);
        $data['country_code'] = $cc;

        // E.164 minimum length — the country code + national number must
        // be at least 7 digits combined and at most 15 (ITU-T spec). A
        // 1-digit phone (which the previous validation happily accepted)
        // turns into wa.me/1?text=... and opens to a junk WhatsApp chat.
        // Fail loudly so the operator notices before they share a broken
        // link on Instagram bio / Google Ads / business cards.
        $combinedDigits = preg_replace('/\D+/', '', $data['country_code'] . $data['phone_number']);
        if (strlen($combinedDigits) < 7 || strlen($combinedDigits) > 15) {
            return response()->json([
                'ok' => false,
                'errors' => ['phone_number' => ['Phone number must be 7–15 digits including country code (E.164).']],
            ], 422);
        }

        $link = !empty($data['id'])
            ? WaChatLink::where('workspace_id', $wsId)->find($data['id'])
            : null;
        if (!$link) {
            $link = new WaChatLink();
            $link->workspace_id = $wsId;
            $link->user_id      = $user->id;
        }

        // Slug — operator-customisable. Auto-generate if blank, then
        // bump the suffix until unique. Per-workspace + global unique
        // (because /l/{slug} is the public namespace).
        $wantSlug = trim((string) ($data['slug'] ?? ''));
        if ($wantSlug === '') $wantSlug = WaChatLink::freshSlug();
        $slug = $wantSlug; $i = 1;
        while (WaChatLink::where('slug', $slug)
            ->where('id', '!=', $link->id ?? 0)
            ->exists()) {
            $slug = $wantSlug . '-' . (++$i);
        }
        $link->slug = $slug;

        // Mass-assign the rest. `id` and `slug` are handled above.
        unset($data['id'], $data['slug']);
        $link->fill($data);
        $link->save();

        return response()->json([
            'ok'         => true,
            'id'         => $link->id,
            'slug'       => $link->slug,
            'wa_url'     => $link->waUrl(),
            'short_url'  => url($link->shortPath()),
        ]);
    }

    public function duplicate(int $id): RedirectResponse
    {
        $wsId = (int) (Auth::user()?->current_workspace_id ?? 0);
        $src  = WaChatLink::where('workspace_id', $wsId)->findOrFail($id);

        $copy = $src->replicate();
        $copy->name        = $src->name . ' (copy)';
        $copy->slug        = WaChatLink::freshSlug();
        $copy->click_count = 0;
        $copy->last_clicked_at = null;
        $copy->save();
        return redirect()->route('user.wa-links.edit', $copy->id);
    }

    public function destroy(int $id): JsonResponse
    {
        $wsId = (int) (Auth::user()?->current_workspace_id ?? 0);
        $link = WaChatLink::where('workspace_id', $wsId)->findOrFail($id);
        $link->delete();
        return response()->json(['ok' => true]);
    }

    /**
     * Public redirect — /l/{slug}. No auth, no CSRF.
     * Increments the click counter atomically + bumps last_clicked_at,
     * then 302s to the wa.me URL. Paused / expired links return 410.
     */
    public function publicRedirect(string $slug): RedirectResponse|Response
    {
        $link = WaChatLink::where('slug', $slug)->first();
        if (!$link) return response('Short link not found.', 404);
        if (!$link->isActive()) return response('This link is paused or expired.', 410);

        // Atomic counter bump — no SELECT/UPDATE race.
        WaChatLink::where('id', $link->id)->update([
            'click_count'     => \DB::raw('click_count + 1'),
            'last_clicked_at' => now(),
        ]);
        return redirect()->away($link->waUrl());
    }
}
