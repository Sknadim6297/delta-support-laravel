<?php

namespace App\Services;

use Throwable;

class SmtpMailTranscript
{
    public function interpret(string $transport, ?string $debug, ?Throwable $exception = null): MailDeliveryNotice
    {
        if ($exception !== null) {
            return new MailDeliveryNotice(false, $this->failureStatus($exception));
        }

        if ($this->dataAccepted((string) $debug)) {
            return new MailDeliveryNotice(true, 'accepted');
        }

        if ($transport !== 'smtp') {
            return new MailDeliveryNotice(false, 'not_smtp');
        }

        return new MailDeliveryNotice(false, 'transport');
    }

    public function dataAccepted(string $debug): bool
    {
        $afterData = false;

        foreach (preg_split('/\R/', $debug) ?: [] as $line) {
            if (preg_match('/\b354\b/', $line) === 1) {
                $afterData = true;

                continue;
            }

            if ($afterData && preg_match('/\b250\b/', $line) === 1) {
                return true;
            }
        }

        return false;
    }

    private function failureStatus(Throwable $exception): string
    {
        $message = strtolower($exception->getMessage());

        if (str_contains($message, 'authenticat') || str_contains($message, '535') || str_contains($message, '534')) {
            return 'authentication';
        }

        if (str_contains($message, 'timed out') || str_contains($message, 'timeout')) {
            return 'timeout';
        }

        return 'transport';
    }
}
