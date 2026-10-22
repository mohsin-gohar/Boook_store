<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The six-digit code issued by the "forgot password" flow.
 *
 * The code is sent by e-mail rather than returned in the API response, so the
 * endpoint cannot be used to take over an account. In development the `log`
 * mailer writes the code to `storage/logs/laravel.log` instead of sending it.
 */
class PasswordResetCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $email,
        public string $code,
    ) {}

    /**
     * Build the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Folio password reset code',
        );
    }

    /**
     * Build the message content.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.password-reset-code',
            with: [
                'code' => $this->code,
                'email' => $this->email,
            ],
        );
    }
}
