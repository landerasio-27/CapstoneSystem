<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The mail that carries a password reset code.
 *
 * The code is written in the clear here — this is the one copy of it that
 * exists, and the office needs to be able to read it off the screen of whatever
 * mail client they keep open. The row behind it keeps only the hash (see
 * PasswordResetCode), so the mail cannot be worked back out of the database.
 */
class ResetPasswordCode extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $code,
        public int $minutesToExpire,
        public string $accountName,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your password reset code — ISU Supply Management System',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.password-reset-code',
        );
    }
}
