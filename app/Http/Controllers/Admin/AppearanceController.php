<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\Request;

/**
 * Global dashboard appearance — lets the platform admin recolour EVERY theme
 * token (Tailwind v4 @theme vars) across BOTH the user + admin dashboards.
 * Values save to SystemSetting `theme.color.*` and are injected live by
 * theme_css() into each layout's <head> (no rebuild). Empty = shipped default.
 */
class AppearanceController extends Controller
{
    public function index()
    {
        $palette = theme_palette();
        $values  = [];
        foreach (array_keys($palette) as $k) {
            $values[$k] = theme_color($k);
        }

        $metrics       = theme_metrics();
        $metricValues  = [];
        foreach (array_keys($metrics) as $k) {
            $metricValues[$k] = theme_metric($k);
        }

        return view('admin.settings.appearance', compact('palette', 'values', 'metrics', 'metricValues'));
    }

    public function update(Request $request)
    {
        $colors = (array) $request->input('colors', []);
        foreach (array_keys(theme_palette()) as $k) {
            $val = trim((string) ($colors[$k] ?? ''));
            $ok  = $val !== '' && preg_match('/^#[0-9A-Fa-f]{3,8}$/', $val);
            SystemSetting::set('theme.color.' . $k, $ok ? $val : '', 'string', 'Dashboard theme colour override');
        }

        // Metrics are clamped to the range declared in theme_metrics() rather
        // than trusted from the request — the sliders enforce min/max in the
        // browser, but a hand-crafted POST could otherwise set zoom to 0 and
        // leave the admin with an unusable, un-fixable dashboard.
        $sliders = (array) $request->input('metrics', []);
        foreach (theme_metrics() as $k => $meta) {
            $raw = $sliders[$k] ?? null;
            if ($raw === null || $raw === '' || ! is_numeric($raw)) {
                continue;
            }
            $clamped = max((int) $meta[2], min((int) $meta[3], (int) $raw));
            SystemSetting::set('theme.metric.' . $k, (string) $clamped, 'string', 'Dashboard appearance metric');
        }

        return back()->with('status', __('Appearance saved — the whole dashboard has been updated.'));
    }

    public function reset(Request $request)
    {
        foreach (array_keys(theme_palette()) as $k) {
            SystemSetting::set('theme.color.' . $k, '', 'string', 'Dashboard theme colour override (reset)');
        }
        foreach (theme_metrics() as $k => $meta) {
            SystemSetting::set('theme.metric.' . $k, (string) (int) $meta[1], 'string', 'Dashboard appearance metric (reset)');
        }
        return back()->with('status', __('Appearance reset to the shipped defaults.'));
    }
}
