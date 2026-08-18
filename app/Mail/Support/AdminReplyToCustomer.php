<?php

namespace App\Mail\Support;

use App\Models\SupportMessage;
use App\Models\SupportTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the customer whose support ticket the admin just replied to.
 * Skipped silently for internal notes (is_internal_note=true) — gated at
 * the dispatch site (InboxController::reply), not here.
 *
 * From address resolution:
 *   - SystemSetting('from_email') if set
 *   - else config('mail.from.address')
 * Name = SystemSetting('app_name') ?? config app name.
 */
class AdminReplyToCustomer extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public SupportTicket $ticket,
        public SupportMessage $message,
    ) {}

    public function envelope(): Envelope
    {
        $fromEmail = (string) \App\Models\SystemSetting::get('from_email', config('mail.from.address', 'no-reply@wadesk.local'));
        $fromName  = (string) \App\Models\SystemSetting::get('app_name', config('app.name', 'Support'));
        return new Envelope(
            from:    new Address($fromEmail, $fromName . ' Support'),
            subject: 'Re: ' . ($this->ticket->subject ?: 'Your support ticket'),
        );
    }

    public function content(): Content
    {
        $appUrl = rtrim((string) \App\Models\SystemSetting::get('app_url', config('app.url', '')), '/');
        return new Content(
            view: 'emails.support.admin-reply',
            with: [
                'ticket'    => $this->ticket,
                'message'   => $this->message,
                'ticketUrl' => ($appUrl !== '' ? $appUrl : (function_exists('url') ? rtrim(url('/'), '/') : '')) . '/support/' . $this->ticket->id,
                'brandName' => (string) \App\Models\SystemSetting::get('app_name', config('app.name', 'Support')),
            ],
        );
    }
}
