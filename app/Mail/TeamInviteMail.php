<?php

namespace App\Mail;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent when an existing or new user is invited to a workspace.
 * If `tempPassword` is non-null the email is for a brand-new user and
 * includes the one-time password. For existing users we just notify
 * them that they've been added (they already have credentials).
 *
 * Wrapped in a try/catch at the call site so a missing/broken mailer
 * (MAIL_MAILER=log in dev, or no SMTP credentials in self-host) never
 * breaks the invite flow itself.
 */
class TeamInviteMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $invitee,
        public Workspace $workspace,
        public User $inviter,
        public string $role,
        public ?string $tempPassword = null,
    ) {
    }

    public function envelope(): Envelope
    {
        $appName = \App\Models\SystemSetting::get('app_name', config('app.name', 'WaDesk'));
        return new Envelope(
            subject: "You've been invited to {$this->workspace->name} on {$appName}",
            to: [$this->invitee->email],
        );
    }

    public function content(): Content
    {
        // Plain HTML view (NOT markdown). The old markdown mailable auto-generated
        // a broken text/plain part that leaked raw HTML (<table class="panel">…),
        // literal **markdown**, and &#039; into the email. A self-contained HTML
        // view renders correctly in every client with no broken text alternative.
        return new Content(
            view: 'mail.team-invite',
            with: [
                'invitee'      => $this->invitee,
                'workspace'    => $this->workspace,
                'inviter'      => $this->inviter,
                'role'         => $this->role,
                'tempPassword' => $this->tempPassword,
                'loginUrl'     => url('/login'),
            ],
        );
    }
}
