<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminWelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User   $user,
        public string $loginUrl,
        public ?string $resetUrl = null,
        public ?string $plainPassword = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Welcome to ' . config('app.name'),
        );
    }

    public function content(): Content
    {
        // Plain self-contained HTML view (NOT markdown) — same fix as the other
        // mailables: markdown auto-generated a broken text/plain part that leaked
        // raw HTML into some clients.
        return new Content(
            view: 'emails.admin-welcome',
            with: [
                'name'          => $this->user->name,
                'email'         => $this->user->email,
                'loginUrl'      => $this->loginUrl,
                'resetUrl'      => $this->resetUrl,
                'plainPassword' => $this->plainPassword,
                'appName'       => config('app.name'),
            ],
        );
    }
}
