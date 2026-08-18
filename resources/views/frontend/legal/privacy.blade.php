@php
$sections = [
 [
 'n' => '01', 'title' => __('Information we collect'),
 'body' => '<h3>' . __('Account & profile data') . '</h3>
 <p>' . __('When you sign up we collect your name, work email, phone number, country, timezone, password (hashed via bcrypt), and any profile photo you upload.') . '</p>
 <h3>' . __('Workspace & operational data') . '</h3>
 <p>' . __('To deliver the Service we process: workspace name, connected WhatsApp numbers, broadcast templates, flow definitions, contact lists, team-member emails and roles, billing details, and audit-log events.') . '</p>
 <h3>' . __('Customer message data') . '</h3>
 <p>' . __(':brand stores the messages, attachments, and metadata that flow through your workspace. You are the data controller for this content; we act as a processor under our DPA.', ['brand' => brand_name()]) . '</p>
 <h3>' . __('Usage & telemetry') . '</h3>
 <p>' . __('Anonymous usage events (page views, feature clicks, performance metrics) and standard server logs (IP address, browser type, request time) are captured for security and product improvement.') . '</p>',
 ],
 [
 'n' => '02', 'title' => __('How we use your information'),
 'body' => '<p>' . __('We use the data we collect to:') . '</p>
 <ul>
 <li>' . __('Provide, maintain, and improve the Service') . '</li>
 <li>' . __('Process payments, send invoices, and manage your subscription') . '</li>
 <li>' . __('Respond to support requests and security incidents') . '</li>
 <li>' . __('Detect, prevent, and address abuse, fraud, and security threats') . '</li>
 <li>' . __('Send transactional emails (receipts, security alerts, product updates you opted into)') . '</li>
 <li>' . __('Comply with legal obligations including tax, accounting, and law-enforcement requests') . '</li>
 </ul>
 <p><strong>' . __('We never sell your data or your customers\' data.') . '</strong></p>',
 ],
 [
 'n' => '03', 'title' => __('Sharing of information'),
 'body' => '<p>' . __('We share data only in these limited circumstances:') . '</p>
 <ul>
 <li><strong>' . __('Sub-processors') . '</strong> — ' . __('AWS (hosting), Stripe (payments), SendGrid (email), Anthropic + OpenAI (AI features, redacted of PII). Full list at') . ' <a href="#">' . __('legal/subprocessors') . '</a>.</li>
 <li><strong>' . __('Meta WhatsApp Cloud API') . '</strong> — ' . __('to dispatch messages on your behalf.') . '</li>
 <li><strong>' . __('Legal requirements') . '</strong> — ' . __('subpoenas, court orders, or to protect rights, safety, and integrity.') . '</li>
 <li><strong>' . __('Business transfers') . '</strong> — ' . __('in the event of a merger, acquisition, or sale of assets, with prior notice.') . '</li>
 </ul>',
 ],
 [
 'n' => '04', 'title' => __('Cookies & tracking'),
 'body' => '<p>' . __('We use first-party cookies for session management, security, and analytics. We do not use cross-site advertising cookies. Detailed list in our') . ' <a href="' . url('/legal/cookies') . '">' . __('Cookie Policy') . '</a>.</p>',
 ],
 [
 'n' => '05', 'title' => __('Data retention'),
 'body' => '<p>' . __('We retain personal data for as long as your account is active. After account termination:') . '</p>
 <ul>
 <li>' . __('Customer data: exportable for 30 days, then deleted within 90 days') . '</li>
 <li>' . __('Audit logs: 7 years for Scale plans (regulatory) or 12 months (other plans)') . '</li>
 <li>' . __('Invoices & billing records: 10 years (tax law requirement)') . '</li>
 <li>' . __('Backups: encrypted, fully purged within 90 days') . '</li>
 </ul>',
 ],
 [
 'n' => '06', 'title' => __('Data security'),
 'body' => '<p>' . __('We implement industry-standard safeguards:') . '</p>
 <ul>
 <li>' . __('Encryption in transit (TLS 1.3) and at rest (AES-256)') . '</li>
 <li>' . __('SOC 2 Type II certified (audited annually)') . '</li>
 <li>' . __('ISO 27001 certified') . '</li>
 <li>' . __('Mandatory 2FA for all staff with production access') . '</li>
 <li>' . __('Quarterly third-party penetration testing') . '</li>
 <li>' . __('Bug bounty program — report to :email', ['email' => brand_email('security')]) . '</li>
 </ul>',
 ],
 [
 'n' => '07', 'title' => __('Your rights'),
 'body' => '<p>' . __('Depending on your jurisdiction (GDPR, CCPA, India DPDP Act 2023) you may have rights to:') . '</p>
 <ul>
 <li>' . __('Access — request a copy of personal data we hold about you') . '</li>
 <li>' . __('Rectification — correct inaccurate information') . '</li>
 <li>' . __('Erasure — request deletion ("right to be forgotten")') . '</li>
 <li>' . __('Portability — export data in a machine-readable format') . '</li>
 <li>' . __('Objection — opt out of certain processing') . '</li>
 <li>' . __('Withdraw consent — for any consent-based processing') . '</li>
 </ul>
 <p>' . __('Email :email to exercise any right. We respond inside 30 days.', ['email' => brand_email('privacy')]) . '</p>',
 ],
 [
 'n' => '08', 'title' => __('International transfers'),
 'body' => '<p>' . __('Customer data is hosted in the region you select (EU, US, or India on Scale plans). Where data crosses borders we rely on Standard Contractual Clauses (SCCs) approved by the European Commission and supplementary measures for adequate protection.') . '</p>',
 ],
 [
 'n' => '09', 'title' => __('Children\'s privacy'),
 'body' => '<p>' . __(':brand is a business tool not intended for users under 18. We do not knowingly collect data from children. If you believe we have, email :email.', ['brand' => brand_name(), 'email' => brand_email('privacy')]) . '</p>',
 ],
 [
 'n' => '10', 'title' => __('Changes to this policy'),
 'body' => '<p>' . __('Material changes to this Privacy Policy will be notified by email at least 30 days before they take effect. The "Updated" date at the top reflects the latest revision.') . '</p>',
 ],
 [
 'n' => '11', 'title' => __('Contact us'),
 'body' => '<p>' . __('Data Protection Officer:') . ' <a href="mailto:' . brand_email('privacy') . '">' . brand_email('privacy') . '</a></p>
 <p>' . __('EU Representative:') . ' <a href="mailto:' . brand_email('eu-rep') . '">' . brand_email('eu-rep') . '</a></p>
 <p>' . __('Mailing address:') . ' ' . __(':brand, Inc. · 42 Cubbon Park Road, Bengaluru 560001, India', ['brand' => brand_name()]) . '</p>',
 ],
 ];
@endphp

<x-frontend.legal-page
 :title="__('Privacy Policy')"
 :subtitle="__('How :brand collects, uses, shares, and protects your information when you use our platform.', ['brand' => brand_name()])"
 :updatedAt="__('March 14, 2026')"
 :effective="__('April 1, 2026')"
 :sections="$sections" />
