@php
$sections = [
 [
 'n' => '01', 'title' => __('What are cookies'),
 'body' => '<p>' . __('Cookies are small text files placed on your device when you visit a website. They enable the site to remember actions and preferences (login, language, font size, etc.) so you do not have to re-enter them on each visit.') . '</p>
 <p>' . __('We also use related technologies including localStorage, sessionStorage, and pixel tags. For brevity we refer to all of these as "cookies" in this policy.') . '</p>',
 ],
 [
 'n' => '02', 'title' => __('Essential cookies'),
 'body' => '<p>' . __('Required for the Service to function. Cannot be disabled.') . '</p>
 <h3>' . __('Examples') . '</h3>
 <ul>
 <li><code>laravel_session</code> — ' . __('keeps you logged in (HttpOnly, Secure, SameSite=Lax)') . '</li>
 <li><code>XSRF-TOKEN</code> — ' . __('CSRF protection for form submissions') . '</li>
 <li><code>wadesk_workspace</code> — ' . __('remembers which workspace you last viewed') . '</li>
 <li><code>theme_preference</code> — ' . __('remembers light / dark / doodle theme choice') . '</li>
 </ul>',
 ],
 [
 'n' => '03', 'title' => __('Functional cookies'),
 'body' => '<p>' . __('Improve the user experience but are not strictly required. You can opt out via the cookie banner.') . '</p>
 <ul>
 <li><code>wa_locale</code> — ' . __('preferred interface language') . '</li>
 <li><code>announcement_dismissed</code> — ' . __('tracks which announcement bars you have closed') . '</li>
 <li><code>onboarding_step</code> — ' . __('resumes the setup wizard where you left off') . '</li>
 </ul>',
 ],
 [
 'n' => '04', 'title' => __('Analytics cookies'),
 'body' => '<p>' . __('Help us understand how the Service is used so we can improve it. All analytics are first-party and anonymised — no third-party trackers, no cross-site advertising.') . '</p>
 <ul>
 <li><code>plausible_visitor</code> — ' . __('anonymous page-view counter (Plausible, EU-hosted)') . '</li>
 <li><code>posthog_session</code> — ' . __('product analytics (PostHog Cloud EU, scrubbed of PII)') . '</li>
 </ul>
 <p>' . __('You can opt out by setting your browser\'s "Do Not Track" header — we honor it.') . '</p>',
 ],
 [
 'n' => '05', 'title' => __('Third-party cookies'),
 'body' => '<p>' . __(':brand does not embed third-party advertising networks. The only third-party cookies you may encounter are from services you explicitly connect to (Stripe checkout, Google Calendar OAuth, Shopify OAuth, etc.), and only during the connect flow.', ['brand' => brand_name()]) . '</p>',
 ],
 [
 'n' => '06', 'title' => __('How to control cookies'),
 'body' => '<h3>' . __('Via our cookie banner') . '</h3>
 <p>' . __('On your first visit you see a banner with "Accept all" and "Manage preferences" options. You can revisit your choices anytime from the footer link "Cookie settings".') . '</p>
 <h3>' . __('Via your browser') . '</h3>
 <p>' . __('Most browsers let you block or delete cookies. Note that blocking essential cookies will prevent you from logging in or using the Service.') . '</p>
 <ul>
 <li><a href="https://support.google.com/chrome/answer/95647" target="_blank">' . __('Chrome cookie settings') . '</a></li>
 <li><a href="https://support.mozilla.org/en-US/kb/cookies-information-websites-store-on-your-computer" target="_blank">' . __('Firefox cookie settings') . '</a></li>
 <li><a href="https://support.apple.com/guide/safari/manage-cookies-sfri11471" target="_blank">' . __('Safari cookie settings') . '</a></li>
 </ul>',
 ],
 [
 'n' => '07', 'title' => __('Updates to this policy'),
 'body' => '<p>' . __('We may update this Cookie Policy when we add or remove cookies. Material changes will be posted on the cookie banner for 30 days.') . '</p>',
 ],
 [
 'n' => '08', 'title' => __('Contact'),
 'body' => '<p>' . __('Questions about cookies? Email') . ' <a href="mailto:' . brand_email('privacy') . '">' . brand_email('privacy') . '</a>.</p>',
 ],
 ];
@endphp

<x-frontend.legal-page
 :title="__('Cookie Policy')"
 :subtitle="__('What cookies and similar technologies :brand uses, why, and how you can control them.', ['brand' => brand_name()])"
 :updatedAt="__('March 14, 2026')"
 :effective="__('April 1, 2026')"
 :sections="$sections" />
