<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class MemberSecurityPinOtpMail extends Mailable
{
    public function __construct(
        public string $memberId,
        public string $otp,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your Delta Support security PIN reset code');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.member-security-pin-otp');
    }
}
