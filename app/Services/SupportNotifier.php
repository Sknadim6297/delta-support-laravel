<?php

namespace App\Services;

use App\Mail\SupportTicketNoticeMail;
use App\Models\Member;
use App\Models\SupportNotification;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SupportNotifier
{
    public function __construct(private readonly MemberMailDelivery $mail)
    {
    }

    public function admins(SupportTicket $ticket, string $summary): string
    {
        $summary = $this->summary($summary);
        $admins = User::query()->where('is_admin', true)->get(['id', 'email']);
        foreach ($admins as $admin) {
            $this->store(SupportNotification::AUDIENCE_ADMIN, null, (int) $admin->id, $ticket, $summary, $this->emailStatus($admin->email, $ticket, $summary));
        }
        if ($admins->isEmpty()) {
            $this->store(SupportNotification::AUDIENCE_ADMIN, null, null, $ticket, $summary, SupportNotification::EMAIL_SKIPPED);
        }

        return $this->flash($summary);
    }

    public function member(SupportTicket $ticket, string $summary): string
    {
        $summary = $this->summary($summary);
        $email = Member::query()->where('member_id', $ticket->member_id)->value('email');
        $this->store(
            SupportNotification::AUDIENCE_MEMBER,
            $ticket->member_id,
            null,
            $ticket,
            $summary,
            $this->emailStatus(is_string($email) ? $email : null, $ticket, $summary)
        );

        return $this->flash($summary);
    }

    public function flash(string $summary): string
    {
        unset($summary);
        if (! $this->mail->canDeliver()) {
            return 'The in-app notice was saved. Email was not sent because mail delivery is unavailable.';
        }

        return 'The in-app notice was saved.';
    }

    private function store(string $audience, ?string $memberId, ?int $userId, SupportTicket $ticket, string $summary, string $emailStatus): void
    {
        SupportNotification::query()->create([
            'audience' => $audience,
            'member_id' => $memberId,
            'user_id' => $userId,
            'support_ticket_id' => $ticket->id,
            'summary' => $summary,
            'email_status' => $emailStatus,
        ]);
    }

    private function emailStatus(?string $email, SupportTicket $ticket, string $summary): string
    {
        if (! $this->mail->canDeliver()) {
            return SupportNotification::EMAIL_UNAVAILABLE;
        }
        if (! is_string($email) || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return SupportNotification::EMAIL_SKIPPED;
        }

        try {
            Mail::to($email)->send(new SupportTicketNoticeMail($ticket->reference, $summary));

            return SupportNotification::EMAIL_SENT;
        } catch (Throwable $exception) {
            Log::warning('Support ticket email was not delivered.', [
                'ticket_id' => $ticket->id,
                'exception' => $exception::class,
            ]);

            return SupportNotification::EMAIL_FAILED;
        }
    }

    private function summary(string $summary): string
    {
        $summary = trim(preg_replace('/\s+/', ' ', $summary) ?? '');

        return mb_substr($summary, 0, 180);
    }
}
