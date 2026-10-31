<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MemberPasswordSetupMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $memberId,
        public string $memberName,
        public string $setupUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Set your Delta Support password');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.member-password-setup');
    }
}
