<?php

namespace Database\Seeders;

use App\Models\WaTemplate;
use Illuminate\Database\Seeder;

/**
 * Seeds 12 sample WA templates that match the static cards the
 * old prototype index page hardcoded (Travel / Healthcare /
 * Education / E-Commerce / Festival / Finance). Idempotent: a
 * full wipe + repopulate so re-running gives stable IDs for
 * screenshots and curl-based tests.
 */
class WaTemplateSeeder extends Seeder
{
    public function run(): void
    {
        WaTemplate::query()->delete();

        $rows = [
            // Travel (6)
            ['Pre-Travel_Reminder',   'travel',     'utility',        "Hi {{name}},\n\nYour exciting trip to {{destination}} is just around the corner. Flight: {{flight_number}}. Departure: {{departure_date}}. Hotel: {{hotel_name}}.\n\nMake sure to arrive at the airport at least {{hours}} before your flight.\n\nSafe travels,\n{{company}}", 'approved'],
            ['Booking_Confirmation',  'travel',     'utility',        "Hi {{name}}, your trip is confirmed.\nDestination: {{destination}}\nDates: {{start_date}} - {{end_date}}\nFlight: {{flight_number}}\nHotel: {{hotel_name}}.\n\nView your itinerary anytime from your account.", 'approved'],
            ['Last_Minute_Deal',      'travel',     'marketing',      "Hi {{name}}, last-minute deal: {{destination}} from {{price}}. Travel between {{start_date}} and {{end_date}}. Reply BOOK to claim.", 'pending'],
            // Healthcare (5)
            ['Prescription_Renewal',  'healthcare', 'utility',        "Hi {{name}}, time to renew your prescription for {{medication}}. Submit a renewal request before {{date}} so you don't run out.", 'approved'],
            ['Appointment_Reminder',  'healthcare', 'utility',        "Hi {{name}}, reminder of your appointment with {{doctor}} at {{clinic}} tomorrow {{date}} at {{time}}. Please arrive 10 minutes early.", 'approved'],
            ['Appointment_Confirm',   'healthcare', 'utility',        "Hi {{name}}, your appointment with {{doctor}} on {{date}} at {{time}} is confirmed. Location: {{address}}.", 'approved'],
            // Education (3)
            ['Class_Reminder',        'education',  'utility',        "Hi {{name}}, your class for {{course}} is scheduled tomorrow {{date}} at {{time}}.\nWhat to bring: notebook + pen, pre-read chapters 4-6.", 'approved'],
            ['Students_Welcome',      'education',  'marketing',      "Dear {{name}},\nWelcome to the {{school}} family. Your first steps:\n1. Confirm attendance\n2. Pick electives\n3. Join the student WhatsApp group", 'pending'],
            ['Course_Enrollment',     'education',  'utility',        "Hi {{name}}, congratulations on enrolling in {{course}}.\nStart: {{start_date}} | Duration: {{weeks}} weeks | Instructor: {{instructor}}", 'approved'],
            // E-commerce + Finance + Festival
            ['Order_Shipped',         'ecommerce',  'utility',        "Hi {{name}}, your order {{order_id}} has shipped. Track it here: {{tracking_url}}", 'approved'],
            ['Diwali_Drop_VIP',       'festival',   'marketing',      "Hi {{name}}, Diwali drop is live for VIPs. Use code {{code}} for {{discount}} off — ends {{expiry}}.", 'pending'],
            ['Loan_Status_Update',    'finance',    'utility',        "Hi {{name}}, your loan application {{ref}} is currently {{status}}. We'll notify you again once it advances.", 'rejected', 'Body too generic — add a per-applicant detail before resubmitting.'],
        ];

        foreach ($rows as $row) {
            [$name, $category, $metaCategory, $body, $status] = array_pad($row, 6, null);
            $rejectionReason = $row[5] ?? null;
            WaTemplate::create([
                'user_id'          => null,
                'template_name'    => $name,
                'category'         => $category,
                'meta_category'    => $metaCategory,
                'template_type'    => 'standard',
                'template_body'    => $body,
                'language'         => 'en_US',
                'status'           => $status,
                'approved_at'      => $status === 'approved' ? now()->subDays(rand(1, 30)) : null,
                'rejection_reason' => $rejectionReason,
            ]);
        }
    }
}
