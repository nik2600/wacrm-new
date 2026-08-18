<?php

namespace App\Support;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;

/**
 * Applies the SMTP credentials saved at /admin/settings/mail to
 * Laravel's mail config + flushes the bound MailManager so the next
 * Mail::send() picks them up.
 *
 * Called from:
 *   - AppServiceProvider::boot() (per request, runs before any Mailable)
 *   - AdminPagesController::settingMailUpdate() (so the redirect target
 *     immediately reflects the new values without rebuilding the mailer)
 *
 * Falls back to whatever's in config()/.env when a SystemSetting row
 * is empty — never overwrites a working .env-based setup with blanks.
 */
class MailConfig
{
    public static function apply(): void
    {
        try {
            $mailer = (string) SystemSetting::get('mail_mailer', '');
            $host   = (string) SystemSetting::get('mail_host', '');
            $port   = (string) SystemSetting::get('mail_port', '');
            $user   = (string) SystemSetting::get('mail_username', '');
            $passEnc= (string) SystemSetting::get('mail_password', '');
            $enc    = (string) SystemSetting::get('mail_encryption', '');
            $fromN  = (string) SystemSetting::get('mail_from_name', '');
            $fromA  = (string) SystemSetting::get('mail_from_address', '');

            // The password is stored as a Crypt::encryptString blob. If
            // decryption fails (older plain-text row, or wrong APP_KEY),
            // fall through to whatever was there — better than no creds.
            $pass = '';
            if ($passEnc !== '') {
                try {
                    $pass = Crypt::decryptString($passEnc);
                } catch (\Throwable $e) {
                    $pass = $passEnc;
                }
            }

            if ($mailer !== '') Config::set('mail.default', $mailer);
            if ($host   !== '') Config::set('mail.mailers.smtp.host', $host);
            if ($port   !== '') Config::set('mail.mailers.smtp.port', (int) $port);
            if ($user   !== '') Config::set('mail.mailers.smtp.username', $user);
            if ($pass   !== '') Config::set('mail.mailers.smtp.password', $pass);
            if ($enc    !== '') Config::set('mail.mailers.smtp.encryption', $enc);
            if ($fromA  !== '') Config::set('mail.from.address', $fromA);
            if ($fromN  !== '') Config::set('mail.from.name', $fromN);

            // Force the MailManager to rebuild the mailer instance with
            // the freshly-set config. Without this, an already-resolved
            // mailer keeps the boot-time config.
            try {
                app()->forgetInstance('mail.manager');
                Mail::clearResolvedInstances();
            } catch (\Throwable $e) {
                // best-effort — if the container API differs in a future
                // Laravel version, the config change still applies to
                // any mailer resolved AFTER this call.
            }
        } catch (\Throwable $e) {
            // Don't take the request down because we couldn't read a
            // settings row. The next page load tries again.
            error_log('[MailConfig] apply failed: ' . $e->getMessage());
        }
    }
}
