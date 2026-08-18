<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class AnnouncementsController extends Controller
{
    public function index(Request $request): View
    {
        $statusF = (string) $request->query('status', 'all');
        $q       = trim((string) $request->query('q', ''));

        $query = Announcement::query();
        if ($statusF === 'active')   $query->active();
        if ($statusF === 'inactive') $query->where('is_active', false);
        if ($statusF === 'expired')  $query->whereNotNull('expires_at')->where('expires_at', '<', now());
        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('text', 'like', "%{$q}%")->orWhere('link_label', 'like', "%{$q}%");
            });
        }

        $announcements = $query->orderBy('sort_order')->orderByDesc('id')->paginate(12)->withQueryString();

        $stats = [
            'total'    => Announcement::query()->count(),
            'active'   => Announcement::query()->active()->count(),
            'expired'  => Announcement::query()->whereNotNull('expires_at')->where('expires_at', '<', now())->count(),
            'scheduled'=> Announcement::query()->whereNotNull('starts_at')->where('starts_at', '>', now())->count(),
        ];

        return view('admin.announcements.index', compact('announcements', 'stats', 'statusF', 'q'));
    }

    public function create(): View
    {
        return view('admin.announcements.create');
    }

    public function store(Request $request): RedirectResponse
    {
        Announcement::create($this->payload($request));
        $this->bustCache();
        return redirect()->route('admin.announcements.index')->with('success', 'Announcement created.');
    }

    public function edit(int $id): View
    {
        return view('admin.announcements.edit', ['announcement' => Announcement::findOrFail($id)]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $a = Announcement::findOrFail($id);
        $a->update($this->payload($request));
        $this->bustCache();
        return back()->with('success', 'Announcement updated.');
    }

    public function destroy(int $id): RedirectResponse
    {
        Announcement::findOrFail($id)->delete();
        $this->bustCache();
        return back()->with('success', 'Announcement deleted.');
    }

    public function toggle(int $id): RedirectResponse
    {
        $a = Announcement::findOrFail($id);
        $a->update(['is_active' => !$a->is_active]);
        $this->bustCache();
        return back()->with('success', $a->is_active ? 'Announcement activated.' : 'Announcement disabled.');
    }

    private function bustCache(): void
    {
        Cache::forget('announcements.active.v1');
    }

    private function payload(Request $request): array
    {
        $data = $request->validate([
            'text'            => 'required|string|max:500',
            'link_url'        => 'nullable|string|max:500',
            'link_label'      => 'nullable|string|max:64',
            'tone'            => ['nullable', Rule::in(['info', 'promo', 'warning', 'success'])],
            'sort_order'      => 'nullable|integer|min:0|max:9999',
            'starts_at'       => 'nullable|date',
            'expires_at'      => 'nullable|date|after_or_equal:starts_at',
            'input_timezone'  => 'nullable|string|max:64',
        ]);

        // datetime-local inputs have no timezone info. Treat them as being
        // in the timezone the admin picked in the dropdown (defaults to
        // app.timezone), then convert to UTC for storage.
        $tz = $data['input_timezone'] ?: config('app.timezone');
        $parse = function (?string $raw) use ($tz) {
            if (!$raw) return null;
            try { return \Carbon\Carbon::parse($raw, $tz); }
            catch (\Throwable) { return null; }
        };
        $data['starts_at']  = $parse($data['starts_at']  ?? null);
        $data['expires_at'] = $parse($data['expires_at'] ?? null);
        unset($data['input_timezone']);

        $data['is_active']   = (bool) $request->input('is_active');
        $data['dismissible'] = (bool) $request->input('dismissible');
        $data['tone']        = $data['tone'] ?? 'info';
        $data['sort_order']  = $data['sort_order'] ?? 0;
        return $data;
    }
}
