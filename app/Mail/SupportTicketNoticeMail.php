<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class SupportTicketNoticeMail extends Mailable
{
    public function __construct(
        public string $reference,
        public string $summary,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Delta Support ticket '.$this->reference);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.support-ticket-notice');
    }
}
