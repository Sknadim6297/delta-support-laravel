<?php

namespace App\Services;

class MailDeliveryNotice
{
    public function __construct(
        public bool $accepted,
        public string $status,
    ) {}

    public function sentence(string $email): string
    {
        return match ($this->status) {
            'accepted' => 'The mail server accepted a password setup link for '.$email.'. Check the inbox and spam folder. Inbox delivery is not confirmed.',
            'authentication' => 'The mail server rejected the sender login. The password setup email for '.$email.' was not accepted.',
            'timeout' => 'The mail server did not respond in time. The password setup email for '.$email.' was not accepted.',
            'queued' => 'The password setup email for '.$email.' was queued and has not been accepted by the mail server.',
            'not_smtp' => 'Mail is not configured for SMTP. The password setup email for '.$email.' was not accepted.',
            default => 'The mail server did not accept the password setup email for '.$email.'.',
        };
    }
}
