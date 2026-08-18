@php
$sections = [
 [
 'n' => '01', 'title' => __('Free trial'),
 'body' => '<p>' . __('Every paid plan ships with a 14-day free trial. No credit card required. You can use every feature included in your selected plan during the trial.') . '</p>
 <p>' . __('If you do not upgrade by the end of the trial, your workspace automatically moves to the Starter (free) tier. Your data and configuration are preserved.') . '</p>',
 ],
 [
 'n' => '02', 'title' => __('Subscription billing'),
 'body' => '<p>' . __('Subscriptions are billed in advance on a recurring monthly or annual basis from the day you upgrade. Annual plans receive a 20% discount (selectable at checkout).') . '</p>
 <h3>' . __('Payment methods') . '</h3>
 <p>' . __('We accept payments through 22 gateways including Stripe, Razorpay, PayPal, Paytm, Cashfree, PhonePe, Paystack, and Flutterwave. Currency is auto-detected from your billing address.') . '</p>
 <h3>' . __('Failed payments') . '</h3>
 <p>' . __('If a renewal payment fails we retry 3 times over 7 days. After 7 days of failed payments your workspace moves to read-only mode. Data is preserved for 30 days.') . '</p>',
 ],
 [
 'n' => '03', 'title' => __('Refund eligibility'),
 'body' => '<p>' . __('We offer refunds in the following cases:') . '</p>
 <ul>
 <li><strong>' . __('14-day money-back guarantee') . '</strong> — ' . __('Cancel within 14 days of your first paid charge for a full refund, for any reason. One-time per workspace.') . '</li>
 <li><strong>' . __('Annual plan refunds') . '</strong> — ' . __('Cancel mid-cycle and receive a prorated refund of the unused months, minus a 10% restocking fee.') . '</li>
 <li><strong>' . __('Service downtime') . '</strong> — ' . __('Scale plan customers receive SLA credits per the published 99.95% uptime guarantee.') . '</li>
 <li><strong>' . __('Duplicate charges') . '</strong> — ' . __('Refunded immediately on request.') . '</li>
 </ul>',
 ],
 [
 'n' => '04', 'title' => __('Non-refundable items'),
 'body' => '<p>' . __('The following are non-refundable:') . '</p>
 <ul>
 <li>' . __('Used message credits and WhatsApp Business API conversation fees (these are paid to Meta, not us)') . '</li>
 <li>' . __('Add-on credits already consumed') . '</li>
 <li>' . __('Monthly subscriptions past the 14-day window (you can cancel to stop future charges)') . '</li>
 <li>' . __('Customisation, migration, and onboarding services already delivered') . '</li>
 </ul>',
 ],
 [
 'n' => '05', 'title' => __('Prorated upgrades & downgrades'),
 'body' => '<p>' . __('Upgrading mid-cycle: you are charged the prorated difference immediately and the new plan takes effect that day.') . '</p>
 <p>' . __('Downgrading mid-cycle: the new plan takes effect at the next renewal date. No refund is issued for the unused portion of the current cycle.') . '</p>',
 ],
 [
 'n' => '06', 'title' => __('Wallet credits'),
 'body' => '<p>' . __('Pay-as-you-go message credits are added to your workspace wallet on purchase and consumed as you ship messages. Unused credits do not expire.') . '</p>
 <p>' . __('Refunds for unused wallet credits are available within 30 days of purchase, minus payment-gateway processing fees (typically 2.9% + $0.30).') . '</p>',
 ],
 [
 'n' => '07', 'title' => __('How to request a refund'),
 'body' => '<p>' . __('Email :email with:', ['email' => brand_email('billing')]) . '</p>
 <ul>
 <li>' . __('Your workspace name and account email') . '</li>
 <li>' . __('The invoice number or charge date') . '</li>
 <li>' . __('A brief reason (optional but helps us improve)') . '</li>
 </ul>
 <p>' . __('Refunds are processed within 5 business days. The credit may take an additional 5–10 business days to appear on your statement, depending on your bank.') . '</p>',
 ],
 [
 'n' => '08', 'title' => __('Chargebacks'),
 'body' => '<p>' . __('Please contact us before initiating a chargeback — we resolve 99% of billing issues inside 24 hours. Initiating a chargeback without first contacting us will result in account suspension and a chargeback fee equal to the disputed amount.') . '</p>',
 ],
 [
 'n' => '09', 'title' => __('Contact billing'),
 'body' => '<p>' . __('Billing questions:') . ' <a href="mailto:' . brand_email('billing') . '">' . brand_email('billing') . '</a></p>
 <p>' . __('Median reply time: 2 hours during business hours.') . '</p>',
 ],
 ];
@endphp

<x-frontend.legal-page
 :title="__('Refund & Payment Policy')"
 :subtitle="__('How payments, refunds, prorated credits, and cancellations work on :brand.', ['brand' => brand_name()])"
 :updatedAt="__('March 14, 2026')"
 :effective="__('April 1, 2026')"
 :sections="$sections" />
