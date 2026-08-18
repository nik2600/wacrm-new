@php
$sections = [
 [
 'n' => '01', 'title' => __('Permitted uses'),
 'body' => '<p>' . __(':brand is built for legitimate business communication with people who have opted in to receive messages from you. Examples of permitted use:', ['brand' => brand_name()]) . '</p>
 <ul>
 <li>' . __('Transactional notifications (orders, shipping, OTP, receipts)') . '</li>
 <li>' . __('Customer support and service-desk replies') . '</li>
 <li>' . __('Marketing broadcasts to opted-in subscribers') . '</li>
 <li>' . __('Appointment reminders, calendar booking, and follow-ups') . '</li>
 <li>' . __('Catalog browsing, in-thread checkout, and payment confirmations') . '</li>
 <li>' . __('Click-to-WhatsApp ad funnels via Meta Ads') . '</li>
 </ul>',
 ],
 [
 'n' => '02', 'title' => __('Strictly prohibited'),
 'body' => '<p>' . __('You may not use :brand to:', ['brand' => brand_name()]) . '</p>
 <h3>' . __('Spam & unsolicited messaging') . '</h3>
 <ul>
 <li>' . __('Send messages to anyone who has not explicitly opted in') . '</li>
 <li>' . __('Send bulk messages to scraped, purchased, or harvested contact lists') . '</li>
 <li>' . __('Continue messaging after a recipient has requested to stop') . '</li>
 </ul>
 <h3>' . __('Illegal content') . '</h3>
 <ul>
 <li>' . __('Distribute malware, phishing links, viruses, or harmful code') . '</li>
 <li>' . __('Promote or facilitate illegal activities including fraud, money-laundering, gambling (where prohibited), drugs, weapons, or human trafficking') . '</li>
 <li>' . __('Share child sexual abuse material (CSAM) — instantly reported to NCMEC and law enforcement') . '</li>
 </ul>
 <h3>' . __('Harmful behaviour') . '</h3>
 <ul>
 <li>' . __('Harassment, hate speech, threats, or doxing of any individual or group') . '</li>
 <li>' . __('Impersonation of any person, brand, or government entity') . '</li>
 <li>' . __('Deceptive practices including fake reviews, pyramid schemes, or false advertising') . '</li>
 </ul>
 <h3>' . __('Technical abuse') . '</h3>
 <ul>
 <li>' . __('Reverse engineering, decompiling, or attempting to extract source code') . '</li>
 <li>' . __('Probing, scanning, or testing the vulnerability of our infrastructure (except via our bug-bounty program)') . '</li>
 <li>' . __('Bypassing rate limits, quotas, or abuse-prevention measures') . '</li>
 <li>' . __('Creating multiple accounts to circumvent restrictions or limits') . '</li>
 </ul>',
 ],
 [
 'n' => '03', 'title' => __('WhatsApp policy compliance'),
 'body' => '<p>' . __('All messages sent through :brand are subject to Meta\'s', ['brand' => brand_name()]) . ' <a href="https://www.whatsapp.com/legal/business-policy" target="_blank">' . __('WhatsApp Business Policy') . '</a>. ' . __('Violating Meta\'s policy may result in your WABA being suspended by Meta, which we cannot override.') . '</p>
 <p>' . __('Key Meta restrictions:') . '</p>
 <ul>
 <li>' . __('24-hour customer-service window for free-form replies') . '</li>
 <li>' . __('Approved templates required for outbound messages outside the window') . '</li>
 <li>' . __('Prohibited industries include adult content, weapons, tobacco, supplements, and unregulated financial services') . '</li>
 </ul>',
 ],
 [
 'n' => '04', 'title' => __('Opt-in & consent'),
 'body' => '<p>' . __('You are responsible for collecting and documenting valid opt-in consent from every contact you message. Acceptable opt-in methods:') . '</p>
 <ul>
 <li>' . __('Web form with explicit consent checkbox') . '</li>
 <li>' . __('Click-to-WhatsApp ad with clear messaging disclosure') . '</li>
 <li>' . __('SMS or email confirmation of subscription') . '</li>
 <li>' . __('Customer-initiated WhatsApp conversation') . '</li>
 </ul>
 <p>' . __('We may request proof of opt-in for any contact list. Inability to provide proof results in suspension.') . '</p>',
 ],
 [
 'n' => '05', 'title' => __('Enforcement'),
 'body' => '<p>' . __('We monitor for violations using automated detection and customer reports. Consequences include:') . '</p>
 <ul>
 <li><strong>' . __('Warning') . '</strong> — ' . __('first minor violation, with 7 days to remediate') . '</li>
 <li><strong>' . __('Suspension') . '</strong> — ' . __('account paused pending review for major violations') . '</li>
 <li><strong>' . __('Immediate termination') . '</strong> — ' . __('for illegal content, repeat violations, or serious harm') . '</li>
 <li><strong>' . __('Law enforcement') . '</strong> — ' . __('we report illegal activity to relevant authorities and preserve evidence') . '</li>
 </ul>
 <p>' . __('No refunds are issued for accounts terminated due to AUP violations.') . '</p>',
 ],
 [
 'n' => '06', 'title' => __('Report a violation'),
 'body' => '<p>' . __('See abuse on :brand? Email :email with:', ['brand' => brand_name(), 'email' => brand_email('abuse')]) . '</p>
 <ul>
 <li>' . __('A description of the violation') . '</li>
 <li>' . __('Workspace name, phone number, or other identifier') . '</li>
 <li>' . __('Screenshots or message excerpts if available') . '</li>
 </ul>
 <p>' . __('We acknowledge every report inside 24 hours and complete investigation inside 7 days.') . '</p>',
 ],
 [
 'n' => '07', 'title' => __('Changes'),
 'body' => '<p>' . __('We may update this Acceptable Use Policy as new categories of abuse emerge. Material changes will be notified by email at least 30 days before they take effect.') . '</p>',
 ],
 ];
@endphp

<x-frontend.legal-page
 :title="__('Acceptable Use Policy')"
 :subtitle="__('What you can and cannot do with :brand. Violations result in immediate suspension.', ['brand' => brand_name()])"
 :updatedAt="__('March 14, 2026')"
 :effective="__('April 1, 2026')"
 :sections="$sections" />
