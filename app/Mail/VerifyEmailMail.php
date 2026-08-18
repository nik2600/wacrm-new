<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VerifyEmailMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User   $user,
        public string $verifyUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Verify your email — ' . config('app.name'),
        );
    }

    public function content(): Content
    {
        // Plain self-contained HTML view (NOT markdown). The markdown mailable
        // auto-generated a broken text/plain part that some clients displayed as
        // raw HTML / literal markup, and its indentation broke rendering. Same
        // fix as TeamInviteMail.
        return new Content(
            view: 'emails.verify-email',
            with: [
                'name'      => $this->user->name,
                'verifyUrl' => $this->verifyUrl,
                'appName'   => config('app.name'),
            ],
        );
    }
}
