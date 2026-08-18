<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seed the 20-language pack into the `languages` table.
 *
 * Idempotent — uses upsert keyed on `code` so re-running this migration
 * (or re-seeding) won't dupe. Codes match Laravel locale conventions
 * (matches the `lang/<code>.json` filenames produced by fasttrans_cli.py).
 *
 * Three of these are RTL — Arabic, Urdu, Hebrew — for which the layout's
 * <html dir="..."> is flipped automatically by app.locale resolution.
 */
return new class extends Migration {
    public function up(): void
    {
        $now = now();
        $rows = [
            // [code, English name, native name, direction, sort_order]
            ['en',    'English',    'English',          'ltr', 1],
            ['es',    'Spanish',    'Español',          'ltr', 2],
            ['hi',    'Hindi',      'हिन्दी',           'ltr', 3],
            ['ar',    'Arabic',     'العربية',          'rtl', 4],
            ['pt',    'Portuguese', 'Português',        'ltr', 5],
            ['ru',    'Russian',    'Русский',          'ltr', 6],
            ['ja',    'Japanese',   '日本語',           'ltr', 7],
            ['de',    'German',     'Deutsch',          'ltr', 8],
            ['fr',    'French',     'Français',         'ltr', 9],
            ['it',    'Italian',    'Italiano',         'ltr', 10],
            ['ko',    'Korean',     '한국어',           'ltr', 11],
            ['zh-CN', 'Chinese (Simplified)', '简体中文', 'ltr', 12],
            ['tr',    'Turkish',    'Türkçe',           'ltr', 13],
            ['id',    'Indonesian', 'Bahasa Indonesia', 'ltr', 14],
            ['vi',    'Vietnamese', 'Tiếng Việt',       'ltr', 15],
            ['th',    'Thai',       'ไทย',              'ltr', 16],
            ['pl',    'Polish',     'Polski',           'ltr', 17],
            ['nl',    'Dutch',      'Nederlands',       'ltr', 18],
            ['ur',    'Urdu',       'اردو',             'rtl', 19],
            ['he',    'Hebrew',     'עברית',            'rtl', 20],
            ['bn',    'Bengali',    'বাংলা',            'ltr', 21],
        ];

        foreach ($rows as [$code, $name, $native, $dir, $order]) {
            DB::table('languages')->updateOrInsert(
                ['code' => $code],
                [
                    'name'        => $name,
                    'native_name' => $native,
                    'direction'   => $dir,
                    'is_active'   => true,
                    'sort_order'  => $order,
                    'updated_at'  => $now,
                    'created_at'  => DB::raw('COALESCE(created_at, NOW())'),
                ],
            );
        }
    }

    public function down(): void
    {
        $codes = ['en','es','hi','ar','pt','ru','ja','de','fr','it','ko','zh-CN','tr','id','vi','th','pl','nl','ur','he','bn'];
        DB::table('languages')->whereIn('code', $codes)->delete();
    }
};
