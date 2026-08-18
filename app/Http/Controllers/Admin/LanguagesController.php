<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Language;
use App\Models\SystemSetting;
use App\Support\Audit;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LanguagesController extends Controller
{
    public function index(): View
    {
        $languages = Language::query()->orderBy('sort_order')->orderBy('name')->get();
        $defaultCode = (string) SystemSetting::get('default_language', config('app.locale', 'en'));
        $stats = [
            'total'   => $languages->count(),
            'active'  => $languages->where('is_active', true)->count(),
            'default' => $defaultCode,
        ];
        return view('admin.languages.index', compact('languages', 'stats', 'defaultCode'));
    }

    /**
     * Adding new languages is intentionally locked. The 20-language pack
     * is fixed (seeded by 2026_05_26_180000_seed_languages.php) because
     * each new language needs a matching lang/<code>.json translation
     * file. Admin can edit / toggle / set-default existing rows but not
     * add new ones until a fresh translation pipeline is in place.
     */
    public function store(Request $request): RedirectResponse
    {
        return back()->with('error', __('Adding new languages is disabled. The 20-language pack is fixed.'));
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $lang = Language::findOrFail($id);
        $data = $request->validate([
            'name'        => 'required|string|max:80',
            'native_name' => 'nullable|string|max:80',
            'direction'   => 'required|in:ltr,rtl',
            'is_active'   => 'sometimes|boolean',
            'sort_order'  => 'nullable|integer|min:0',
        ]);
        $data['is_active'] = (bool) ($data['is_active'] ?? false);
        $lang->update($data);
        Audit::log('admin.language.updated', [
            'resource' => $lang,
            'meta'     => ['code' => $lang->code, 'is_active' => $lang->is_active],
        ]);
        return back()->with('success', "{$lang->name} updated.");
    }

    public function toggle(int $id): RedirectResponse
    {
        $lang = Language::findOrFail($id);
        $lang->update(['is_active' => ! $lang->is_active]);
        // Bust the locale-switcher cache so the header dropdown picks up
        // the change without a deploy.
        \App\Support\LocaleSettings::flush();
        Audit::log($lang->is_active ? 'admin.language.activated' : 'admin.language.deactivated', [
            'resource' => $lang,
        ]);
        return back()->with('success', "{$lang->name} " . ($lang->is_active ? 'activated' : 'disabled') . '.');
    }

    public function setDefault(Request $request, int $id): RedirectResponse
    {
        $lang = Language::findOrFail($id);
        if (! $lang->is_active) {
            return back()->with('error', "Activate {$lang->name} first before setting it as default.");
        }
        $previous = (string) SystemSetting::get('default_language', 'en');
        SystemSetting::set('default_language', $lang->code, 'string', 'Default platform language for new users.');
        \App\Support\LocaleSettings::flush();
        Audit::log('admin.language.default_changed', [
            'resource' => $lang,
            'meta'     => ['previous' => $previous, 'now' => $lang->code],
        ]);
        return back()->with('success', "{$lang->name} is now the default language.");
    }

    public function destroy(int $id): RedirectResponse
    {
        $lang = Language::findOrFail($id);
        $defaultCode = (string) SystemSetting::get('default_language', 'en');
        if ($lang->code === $defaultCode) {
            return back()->with('error', "Can't delete the default language. Set another language as default first.");
        }
        $snapshot = ['code' => $lang->code, 'name' => $lang->name];
        $lang->delete();
        Audit::log('admin.language.deleted', ['meta' => $snapshot]);
        return back()->with('success', "Language removed.");
    }
}
