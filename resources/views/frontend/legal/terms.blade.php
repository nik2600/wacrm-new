@php
$sections = [
 [
 'n' => '01', 'title' => __('Acceptance of terms'),
 'body' => '<p>' . __('By creating an account, accessing, or using the :brand platform ("Service"), you agree to be bound by these Terms of Service ("Terms"). If you are accepting on behalf of a company, you represent that you have authority to bind that entity.', ['brand' => brand_name()]) . '</p>
 <p>' . __('If you do not agree to these Terms, do not use the Service.') . '</p>',
 ],
 [
 'n' => '02', 'title' => __('Account & registration'),
 'body' => '<p>' . __('To use the Service you must register for an account by providing accurate and complete information, including a valid business email and verified WhatsApp number where applicable.') . '</p>
 <ul>
 <li>' . __('You are responsible for safeguarding your account credentials and for all activity under your account.') . '</li>
 <li>' . __('You must notify us immediately at :email of any unauthorised access.', ['email' => brand_email('security')]) . '</li>
 <li>' . __('You must be at least 18 years old to create an account.') . '</li>
 </ul>',
 ],
 [
 'n' => '03', 'title' => __('Subscription & billing'),
 'body' => '<p>' . __('Paid plans are billed in advance on a recurring monthly or annual basis. Pay-as-you-go message credits are deducted from your wallet as you ship.') . '</p>
 <h3>' . __('Billing cycle') . '</h3>
 <p>' . __('Subscription fees auto-renew at the end of each billing period unless cancelled before the renewal date. You can cancel anytime from your account settings.') . '</p>
 <h3>' . __('Taxes') . '</h3>
 <p>' . __('All fees are exclusive of applicable taxes (GST, VAT, sales tax). Taxes are added at checkout based on your billing address.') . '</p>',
 ],
 [
 'n' => '04', 'title' => __('Use of the service'),
 'body' => '<p>' . __('You agree to use the Service in compliance with all applicable laws, including data-protection laws (GDPR, CCPA, DPDP Act 2023), anti-spam laws, and Meta\'s WhatsApp Business Policy.') . '</p>
 <p>' . __(':brand is a multi-tenant SaaS — you receive a non-exclusive, non-transferable, revocable licence to use the Service per your plan tier.', ['brand' => brand_name()]) . '</p>',
 ],
 [
 'n' => '05', 'title' => __('Acceptable use'),
 'body' => '<p>' . __('You may not use :brand to send spam, phishing messages, malware, illegal content, or messages without proper opt-in consent. Detailed restrictions are listed in our', ['brand' => brand_name()]) . ' <a href="' . url('/legal/acceptable-use') . '">' . __('Acceptable Use Policy') . '</a>.</p>
 <p>' . __('Violations may result in immediate suspension of your account without refund.') . '</p>',
 ],
 [
 'n' => '06', 'title' => __('Customer data & privacy'),
 'body' => '<p>' . __('You retain all rights to customer data uploaded to the Service. We process this data as a processor under our') . ' <a href="' . url('/legal/privacy') . '">' . __('Privacy Policy') . '</a> ' . __('and standard Data Processing Agreement (available on request).') . '</p>
 <ul>
 <li>' . __('Data residency: EU, US, or India — selected per workspace on Scale plans.') . '</li>
 <li>' . __('Encryption: in transit (TLS 1.3) and at rest (AES-256).') . '</li>
 <li>' . __('Backups: encrypted, retained 30 days.') . '</li>
 </ul>',
 ],
 [
 'n' => '07', 'title' => __('Intellectual property'),
 'body' => '<p>' . __('All :brand software, designs, trademarks, and documentation remain the property of :brand, Inc. You may not copy, modify, reverse engineer, or create derivative works.', ['brand' => brand_name()]) . '</p>
 <p>' . __('Feedback you provide may be used by us without obligation. You retain ownership of your content, templates, flows, and customer data.') . '</p>',
 ],
 [
 'n' => '08', 'title' => __('Confidentiality'),
 'body' => '<p>' . __('Each party agrees to keep the other\'s confidential information confidential and to use it only for purposes of the Service. This obligation survives termination for 3 years.') . '</p>',
 ],
 [
 'n' => '09', 'title' => __('Warranties & disclaimers'),
 'body' => '<p>' . __('The Service is provided "as is" without warranty of any kind. We do not guarantee uninterrupted operation or that the Service will meet your specific requirements.') . '</p>
 <p>' . __('Scale plan customers receive a 99.95% uptime SLA with credits per the SLA document. All other plans have no uptime guarantee.') . '</p>',
 ],
 [
 'n' => '10', 'title' => __('Limitation of liability'),
 'body' => '<p>' . __('To the maximum extent permitted by law, :brand\'s aggregate liability under these Terms is capped at the fees you paid us in the 12 months preceding the claim.', ['brand' => brand_name()]) . '</p>
 <p>' . __('We are not liable for indirect, incidental, consequential, special, or punitive damages, including lost profits or data.') . '</p>',
 ],
 [
 'n' => '11', 'title' => __('Indemnification'),
 'body' => '<p>' . __('You agree to indemnify and hold :brand harmless from any claims, damages, or expenses arising from your violation of these Terms, your use of the Service, or your customer data.', ['brand' => brand_name()]) . '</p>',
 ],
 [
 'n' => '12', 'title' => __('Termination'),
 'body' => '<p>' . __('You may cancel your subscription anytime from account settings — cancellation takes effect at the end of the current billing period.') . '</p>
 <p>' . __('We may suspend or terminate your account immediately if you breach these Terms, violate the Acceptable Use Policy, or fail to pay fees when due.') . '</p>
 <p>' . __('Upon termination you may export your data for 30 days. After 30 days, customer data is permanently deleted from our systems and backups within 90 days.') . '</p>',
 ],
 [
 'n' => '13', 'title' => __('Governing law & disputes'),
 'body' => '<p>' . __('These Terms are governed by the laws of India (for customers billed in INR) or Delaware, USA (for all other customers). Disputes will be resolved by binding arbitration in Bengaluru or San Francisco, respectively.') . '</p>',
 ],
 [
 'n' => '14', 'title' => __('Changes to these terms'),
 'body' => '<p>' . __('We may update these Terms from time to time. Material changes will be notified by email at least 30 days before they take effect. Continued use of the Service constitutes acceptance.') . '</p>',
 ],
 [
 'n' => '15', 'title' => __('Contact'),
 'body' => '<p>' . __('Questions about these Terms? Email') . ' <a href="mailto:' . brand_email('legal') . '">' . brand_email('legal') . '</a>.</p>
 <p>' . __(':brand, Inc. · 42 Cubbon Park Road, Bengaluru 560001, India · CIN U72900KA2024PTC123456', ['brand' => brand_name()]) . '</p>',
 ],
 ];
@endphp

<x-frontend.legal-page
 :title="__('Terms of Service')"
 :subtitle="__('These terms govern your access to and use of :brand. By using the service, you agree to be bound by them.', ['brand' => brand_name()])"
 :updatedAt="__('March 14, 2026')"
 :effective="__('April 1, 2026')"
 :sections="$sections" />
