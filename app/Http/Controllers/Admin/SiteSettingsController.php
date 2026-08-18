<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Models\SystemSetting;
use App\Support\Audit;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * /admin/site-settings — the public site's shared "identity": contact
 * emails, phone, address, and social links that repeat across the footer,
 * contact, and about pages. Stored as SystemSetting `site.*` keys and read
 * everywhere via the global site_info() helper, so one edit updates them
 * all. (Marketing COPY is edited in the live Frontend editor instead.)
 */
class SiteSettingsController extends Controller
{
    /**
     * Every field this page manages, grouped for the form. Each entry:
     * [key, label, type ('text'|'email'|'url'|'tel'), default, placeholder].
     */
    public const GROUPS = [
        'Company' => [
            ['company_name', 'Company name', 'text', 'WaDesk Inc.', 'WaDesk Inc.'],
            ['tagline',      'Tagline',      'text', '', 'The complete WhatsApp business platform'],
            ['founded_year', 'Founded year', 'text', '', '2024'],
            ['address',      'Address',      'text', '', '4th Floor, Prestige Tower, MG Road'],
            ['city_country', 'City · Country','text', '', 'Bengaluru · India'],
        ],
        'Contact emails' => [
            ['email_general',  'General email',  'email', '', 'team@yourdomain.com'],
            ['email_support',  'Support email',  'email', '', 'support@yourdomain.com'],
            ['email_sales',    'Sales email',    'email', '', 'sales@yourdomain.com'],
            ['email_security', 'Security email', 'email', '', 'security@yourdomain.com'],
            ['email_careers',  'Careers email',  'email', '', 'careers@yourdomain.com'],
        ],
        'Phone & WhatsApp' => [
            ['phone',    'Phone (display)',          'tel',  '', '+91 80123 45678'],
            ['whatsapp', 'WhatsApp number (digits)', 'text', '', '918012345678'],
        ],
        'Social links' => [
            ['social_x',         'X (Twitter)', 'url', '', 'https://x.com/yourhandle'],
            ['social_linkedin',  'LinkedIn',    'url', '', 'https://linkedin.com/company/...'],
            ['social_instagram', 'Instagram',   'url', '', 'https://instagram.com/...'],
            ['social_youtube',   'YouTube',     'url', '', 'https://youtube.com/@...'],
            ['social_facebook',  'Facebook',    'url', '', 'https://facebook.com/...'],
            ['social_github',    'GitHub',      'url', '', 'https://github.com/...'],
        ],
    ];

    public function index(): View
    {
        $values = [];
        foreach (self::GROUPS as $fields) {
            foreach ($fields as [$key]) {
                $values[$key] = SystemSetting::get('site.' . $key, null);
            }
        }

        return view('admin.site-settings.index', [
            'groups'       => self::GROUPS,
            'values'       => $values,
            'unreadCount'  => ContactMessage::where('is_read', false)->count(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        // Build validation rules from the field map.
        $rules = [];
        foreach (self::GROUPS as $fields) {
            foreach ($fields as [$key, , $type]) {
                $rules[$key] = match ($type) {
                    'email' => ['nullable', 'email', 'max:190'],
                    'url'   => ['nullable', 'url', 'max:255'],
                    default => ['nullable', 'string', 'max:255'],
                };
            }
        }
        $data = $request->validate($rules);

        foreach ($data as $key => $value) {
            SystemSetting::set('site.' . $key, (string) ($value ?? ''), 'string');
        }

        Audit::log('admin.site_settings.updated', [
            'resource_label' => 'Site settings',
            'meta' => ['fields' => array_keys($data)],
        ]);

        return back()->with('success', __('Site settings saved.'));
    }
}
