<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FrontendContent;
use App\Services\Frontend\FrontendContentStore;
use App\Services\Frontend\FrontendRegistry;
use App\Support\Audit;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * /admin/frontend — the live editor for the public marketing site.
 *
 * The shell (index) renders an iframe of the public page with ?fc_edit=1.
 * Inside that iframe an editor bridge (injected by the frontend layout only
 * for an authed admin) turns every [data-fc] element into an inline editor
 * and POSTs drafts back here. Theme colors + section show/hide are driven
 * by the side panel, which also posts here. Nothing is public until the
 * admin hits Publish (draft → published).
 */
class FrontendEditorController extends Controller
{
    /**
     * Theme tokens the panel exposes, with their shipped defaults. Mirror
     * of the palette in components/layouts/frontend.blade.php — keep in
     * sync. Grouped for a tidy panel layout.
     */
    public const THEME_TOKENS = [
        'WhatsApp' => [
            'theme.wa.deep'   => ['#075E54', 'Deep'],
            'theme.wa.teal'   => ['#128C7E', 'Teal'],
            'theme.wa.green'  => ['#25D366', 'Green'],
            'theme.wa.mint'   => ['#DCF8C6', 'Mint'],
            'theme.wa.bubble' => ['#E7FFDB', 'Bubble'],
            'theme.wa.chat'   => ['#ECE5DD', 'Chat'],
        ],
        'Ink (text)' => [
            'theme.ink.950' => ['#070D0C', '950'],
            'theme.ink.900' => ['#0B1F1C', '900'],
            'theme.ink.800' => ['#13312D', '800'],
            'theme.ink.700' => ['#1F4540', '700'],
            'theme.ink.600' => ['#3A5A55', '600'],
            'theme.ink.500' => ['#6B807C', '500'],
            'theme.ink.400' => ['#9AA8A4', '400'],
            'theme.ink.300' => ['#C3CCC9', '300'],
        ],
        'Paper (bg)' => [
            'theme.paper.0'   => ['#FBFAF6', '0'],
            'theme.paper.50'  => ['#F5F3EC', '50'],
            'theme.paper.100' => ['#EFEBE0', '100'],
            'theme.paper.200' => ['#E5DFD0', '200'],
            'theme.paper.300' => ['#D4CCB6', '300'],
        ],
        'Accent' => [
            'theme.accent.coral' => ['#E87A5D', 'Coral'],
            'theme.accent.amber' => ['#E5A04E', 'Amber'],
            'theme.accent.sand'  => ['#D9C9A3', 'Sand'],
            'theme.accent.plum'  => ['#5B3D8A', 'Plum'],
            'theme.accent.sky'   => ['#3E7AA1', 'Sky'],
        ],
    ];

    /**
     * One-click brand palettes. Each preset re-skins the accent/brand
     * tokens (the greens, teal, mint, bubble + the two warm accents) while
     * leaving paper/ink alone so text stays readable. Applying a preset
     * just writes these as drafts — still reversible until Publish.
     */
    public const THEME_PRESETS = [
        'emerald' => ['label' => 'Emerald', 'swatch' => '#075E54', 'tokens' => [
            'theme.wa.deep' => '#075E54', 'theme.wa.teal' => '#128C7E', 'theme.wa.green' => '#25D366',
            'theme.wa.mint' => '#DCF8C6', 'theme.wa.bubble' => '#E7FFDB',
            'theme.accent.coral' => '#E87A5D', 'theme.accent.amber' => '#E5A04E',
        ]],
        'ocean' => ['label' => 'Ocean', 'swatch' => '#0B4F6C', 'tokens' => [
            'theme.wa.deep' => '#0B4F6C', 'theme.wa.teal' => '#1B98C9', 'theme.wa.green' => '#20A4F3',
            'theme.wa.mint' => '#CDEFFF', 'theme.wa.bubble' => '#E3F6FF',
            'theme.accent.coral' => '#FF8552', 'theme.accent.amber' => '#FFB627',
        ]],
        'forest' => ['label' => 'Forest', 'swatch' => '#1B4332', 'tokens' => [
            'theme.wa.deep' => '#1B4332', 'theme.wa.teal' => '#2D6A4F', 'theme.wa.green' => '#40916C',
            'theme.wa.mint' => '#D8F3DC', 'theme.wa.bubble' => '#E9F7EF',
            'theme.accent.coral' => '#E76F51', 'theme.accent.amber' => '#E9C46A',
        ]],
        'royal' => ['label' => 'Royal', 'swatch' => '#4A2C6F', 'tokens' => [
            'theme.wa.deep' => '#4A2C6F', 'theme.wa.teal' => '#6C3FA0', 'theme.wa.green' => '#9B59D0',
            'theme.wa.mint' => '#EADCF7', 'theme.wa.bubble' => '#F3EAFB',
            'theme.accent.coral' => '#E0689A', 'theme.accent.amber' => '#E5A04E',
        ]],
        'sunset' => ['label' => 'Sunset', 'swatch' => '#B23A48', 'tokens' => [
            'theme.wa.deep' => '#B23A48', 'theme.wa.teal' => '#D9594C', 'theme.wa.green' => '#F08A4B',
            'theme.wa.mint' => '#FCE4D6', 'theme.wa.bubble' => '#FFEFE3',
            'theme.accent.coral' => '#E5533D', 'theme.accent.amber' => '#F2A65A',
        ]],
        'slate' => ['label' => 'Slate', 'swatch' => '#2B2D42', 'tokens' => [
            'theme.wa.deep' => '#2B2D42', 'theme.wa.teal' => '#4A4E69', 'theme.wa.green' => '#5C8A72',
            'theme.wa.mint' => '#E2E4ED', 'theme.wa.bubble' => '#EDEEF4',
            'theme.accent.coral' => '#D98B7B', 'theme.accent.amber' => '#C9A86A',
        ]],
    ];

    /** Field types the inline editor / panel may save. */
    private const ALLOWED_TYPES = ['text', 'richtext', 'json', 'color', 'image'];

    public function __construct(private FrontendContentStore $store) {}

    /* ───────────────────────────── shell ─────────────────────────── */

    public function index(Request $request): View
    {
        $pages = FrontendRegistry::pages();
        $page  = $request->query('page');
        if (! is_string($page) || ! isset($pages[$page])) {
            $page = array_key_first($pages);
        }

        // Resolved theme values (draft-aware so the panel shows pending edits).
        $theme = [];
        foreach (self::THEME_TOKENS as $group) {
            foreach ($group as $key => [$default, $label]) {
                $theme[$key] = $this->store->get($key, $default, true);
            }
        }

        // Hidden sections + saved order per page (draft set).
        $hidden = [];
        $order  = [];
        foreach ($pages as $slug => $def) {
            $h = $this->store->get("{$slug}.__hidden", [], true);
            $hidden[$slug] = is_array($h) ? array_values($h) : [];
            $o = $this->store->get("{$slug}.__order", [], true);
            $order[$slug] = is_array($o) ? array_values($o) : [];
        }

        // Are there any unpublished drafts? (draft !== published anywhere)
        $pendingCount = FrontendContent::whereColumn('draft', '!=', 'published')
            ->orWhere(fn ($q) => $q->whereNotNull('draft')->whereNull('published'))
            ->count();

        return view('admin.frontend.index', [
            'pages'        => $pages,
            'sections'     => FrontendRegistry::sections(),
            'activePage'   => $page,
            'previewUrl'   => $this->previewUrl($pages[$page]['route']),
            'themeTokens'  => self::THEME_TOKENS,
            'themePresets' => self::THEME_PRESETS,
            'theme'        => $theme,
            'hidden'       => $hidden,
            'order'        => $order,
            'pendingCount' => $pendingCount,
            'frontendEnabled' => (bool) \App\Models\SystemSetting::get('frontend_enabled', true),
        ]);
    }

    /* ──────────────────────────── writes ─────────────────────────── */

    /**
     * Master on/off for the public marketing HOMEPAGE. When OFF, a visit to `/`
     * redirects to login (guests) / the app (authed); every other page —
     * pricing, privacy, terms, legal — keeps working. Saved immediately (not a
     * draft) since it governs routing, not content.
     */
    public function toggleFrontend(Request $request): JsonResponse
    {
        $enabled = $request->boolean('enabled');
        \App\Models\SystemSetting::set('frontend_enabled', $enabled ? 1 : 0, 'int');

        Audit::log('admin.frontend.toggle', [
            'resource_label' => $enabled ? 'enabled' : 'disabled',
            'meta' => ['enabled' => $enabled],
        ]);

        return response()->json(['ok' => true, 'enabled' => $enabled]);
    }

    /** Save one field/colour draft. Used by both the inline editor and the theme panel. */
    public function saveDraft(Request $request): JsonResponse
    {
        $data = $request->validate([
            'key'   => ['required', 'string', 'max:191', 'regex:/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/i'],
            'value' => ['present'],
            'type'  => ['nullable', 'string', 'in:' . implode(',', self::ALLOWED_TYPES)],
        ]);

        $type  = $data['type'] ?? 'text';
        $value = $data['value'];

        // Colours: keep it to a hex so a typo can't poison the Tailwind config.
        if ($type === 'color') {
            $value = is_string($value) ? trim($value) : '';
            if (! preg_match('/^#[0-9a-f]{3}(?:[0-9a-f]{3})?$/i', $value)) {
                return response()->json(['ok' => false, 'error' => 'Invalid colour'], 422);
            }
            $type = 'text'; // stored as a raw string
        }

        $this->store->setDraft($data['key'], $value, $type === 'richtext' ? 'text' : $type);

        Audit::log('admin.frontend.draft', [
            'subject_type' => 'frontend_content',
            'resource_label' => $data['key'],
            'meta' => ['key' => $data['key'], 'type' => $type],
        ]);

        return response()->json(['ok' => true]);
    }

    /** Apply a one-click brand palette — writes all its tokens as drafts. */
    public function applyPreset(Request $request): JsonResponse
    {
        $key = (string) $request->input('preset');
        if (! isset(self::THEME_PRESETS[$key])) {
            return response()->json(['ok' => false, 'error' => 'Unknown preset'], 422);
        }

        $tokens = self::THEME_PRESETS[$key]['tokens'];
        foreach ($tokens as $tk => $hex) {
            $this->store->setDraft($tk, $hex, 'text');
        }

        Audit::log('admin.frontend.preset', [
            'resource_label' => self::THEME_PRESETS[$key]['label'],
            'meta' => ['preset' => $key],
        ]);

        return response()->json(['ok' => true, 'tokens' => $tokens]);
    }

    /** Show/hide a section on a page (writes the page's __hidden draft array). */
    public function toggleSection(Request $request): JsonResponse
    {
        $data = $request->validate([
            'page'   => ['required', 'string'],
            'slug'   => ['required', 'string'],
            'hidden' => ['required', 'boolean'],
        ]);

        $pages = FrontendRegistry::pages();
        if (! isset($pages[$data['page']]) || ! in_array($data['slug'], $pages[$data['page']]['sections'], true)) {
            return response()->json(['ok' => false, 'error' => 'Unknown section'], 422);
        }
        // Heroes (and other structural rows) can never be hidden.
        $meta = FrontendRegistry::sections()[$data['slug']] ?? [];
        if (($meta['removable'] ?? true) === false) {
            return response()->json(['ok' => false, 'error' => 'This section cannot be hidden'], 422);
        }

        $key = "{$data['page']}.__hidden";
        $set = $this->store->get($key, [], true);
        $set = is_array($set) ? array_values($set) : [];

        if ($data['hidden']) {
            if (! in_array($data['slug'], $set, true)) {
                $set[] = $data['slug'];
            }
        } else {
            $set = array_values(array_filter($set, fn ($s) => $s !== $data['slug']));
        }

        $this->store->setDraft($key, $set, 'json');

        Audit::log('admin.frontend.section', [
            'resource_label' => "{$data['page']} · {$data['slug']}",
            'meta' => ['page' => $data['page'], 'slug' => $data['slug'], 'hidden' => $data['hidden']],
        ]);

        return response()->json(['ok' => true, 'hidden' => $set]);
    }

    /** Save a new section order for a page (writes the page's __order draft). */
    public function reorder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'page'    => ['required', 'string'],
            'order'   => ['required', 'array'],
            'order.*' => ['string'],
        ]);

        $pages = FrontendRegistry::pages();
        if (! isset($pages[$data['page']])) {
            return response()->json(['ok' => false, 'error' => 'Unknown page'], 422);
        }

        // Keep only this page's removable sections (heroes are pinned and
        // never part of the saved order), in the submitted order.
        $meta = FrontendRegistry::sections();
        $valid = [];
        foreach ($data['order'] as $slug) {
            if (in_array($slug, $pages[$data['page']]['sections'], true)
                && ($meta[$slug]['removable'] ?? true) !== false
                && ! in_array($slug, $valid, true)) {
                $valid[] = $slug;
            }
        }

        $this->store->setDraft("{$data['page']}.__order", $valid, 'json');

        Audit::log('admin.frontend.reorder', [
            'resource_label' => $data['page'],
            'meta' => ['page' => $data['page'], 'order' => $valid],
        ]);

        return response()->json(['ok' => true, 'order' => $valid]);
    }

    /** Promote drafts to live. Scope = "all" or a page slug. */
    public function publish(Request $request): JsonResponse
    {
        $scope = (string) $request->input('scope', 'all');
        $prefix = null;

        if ($scope !== 'all') {
            $pages = FrontendRegistry::pages();
            if (! isset($pages[$scope])) {
                return response()->json(['ok' => false, 'error' => 'Unknown page'], 422);
            }
            $prefix = "{$scope}.";
        }

        $n = $this->store->publish($prefix);

        Audit::log('admin.frontend.publish', [
            'resource_label' => $scope,
            'meta' => ['scope' => $scope, 'changed' => $n],
        ]);

        return response()->json(['ok' => true, 'published' => $n]);
    }

    /** Throw away unpublished drafts (keep live), scope = "all" or a page slug. */
    public function discard(Request $request): JsonResponse
    {
        $scope = (string) $request->input('scope', 'all');
        $prefix = null;

        if ($scope !== 'all') {
            if (! isset(FrontendRegistry::pages()[$scope])) {
                return response()->json(['ok' => false, 'error' => 'Unknown page'], 422);
            }
            $prefix = "{$scope}.";
        }

        $n = $this->store->discard($prefix);

        Audit::log('admin.frontend.discard', [
            'resource_label' => $scope,
            'meta' => ['scope' => $scope, 'reverted' => $n],
        ]);

        return response()->json(['ok' => true, 'reverted' => $n]);
    }

    /** Revert to shipped defaults — one field, or a whole page prefix. */
    public function reset(Request $request): JsonResponse
    {
        $key   = $request->input('key');
        $scope = $request->input('scope'); // page slug, "theme", or "all"

        if (is_string($key) && $key !== '') {
            $this->store->reset($key);
            Audit::log('admin.frontend.reset', ['resource_label' => $key, 'meta' => ['key' => $key]]);
            return response()->json(['ok' => true]);
        }

        $prefix = match (true) {
            $scope === 'all'    => null,
            $scope === 'theme'  => 'theme.',
            is_string($scope) && isset(FrontendRegistry::pages()[$scope]) => "{$scope}.",
            default             => false,
        };
        if ($prefix === false) {
            return response()->json(['ok' => false, 'error' => 'Nothing to reset'], 422);
        }

        $q = FrontendContent::query();
        if ($prefix !== null) {
            $q->where('ckey', 'like', addcslashes($prefix, '%_') . '%');
        }
        $deleted = $q->delete();
        // Query-builder delete skips model events → bust the cache AND the
        // store's in-memory copy for this request.
        $this->store->flush();

        Audit::log('admin.frontend.reset', [
            'resource_label' => $scope ?: 'all',
            'meta' => ['scope' => $scope, 'deleted' => $deleted],
        ]);

        return response()->json(['ok' => true, 'deleted' => $deleted]);
    }

    /** Image upload for image-type fields (logos, hero art, etc.). */
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,gif,svg', 'max:4096'],
            'key'  => ['nullable', 'string', 'max:191', 'regex:/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/i'],
        ]);

        $file = $request->file('file');
        $name = Str::random(24) . '.' . strtolower($file->getClientOriginalExtension());
        $file->move(public_path('uploads/frontend'), $name);
        $url = url('uploads/frontend/' . $name);

        if ($key = $request->input('key')) {
            $this->store->setDraft($key, $url, 'image');
            Audit::log('admin.frontend.upload', ['resource_label' => $key, 'meta' => ['key' => $key]]);
        }

        return response()->json(['ok' => true, 'url' => $url]);
    }

    /* ──────────────────────────── helpers ────────────────────────── */

    /** Public page URL with the editor flag on. */
    private function previewUrl(string $routeName): string
    {
        return route($routeName) . '?fc_edit=1';
    }
}
