<?php

namespace App\Services;

class MemberMailDelivery
{
    public function canDeliver(): bool
    {
        $mailer = strtolower(trim((string) config('mail.default')));

        if ($mailer === 'array') {
            return app()->environment('testing');
        }

        if ($mailer === 'smtp') {
            return trim((string) config('mail.mailers.smtp.host')) !== '';
        }

        return in_array($mailer, ['ses', 'ses-v2', 'postmark', 'resend', 'mailgun', 'sendmail'], true);
    }

    public function unavailableReason(): ?string
    {
        if ($this->canDeliver()) {
            return null;
        }

        $mailer = trim((string) config('mail.default'));
        if ($mailer === '') {
            $mailer = 'unset';
        }

        if ($mailer === 'log') {
            return 'Email delivery is unavailable. MAIL_MAILER is log, so a reset code was not created and was not written to the log. Configure a delivery mailer before using Forgot PIN.';
        }

        return 'Email delivery is unavailable. A reset code was not sent. The configured mailer is "'.$mailer.'".';
    }
}
