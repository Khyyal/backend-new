<?php

namespace Modules\Centers\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The one-time code that verifies a center's contact email.
 */
class CenterEmailVerificationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $centerName,
        public readonly string $code,
        public readonly int $ttlMinutes,
        string $locale,
    ) {
        $this->locale($locale);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('centers::email_verification.mail.subject'));
    }

    public function content(): Content
    {
        return new Content(view: 'centers::mail.email-verification');
    }
}
