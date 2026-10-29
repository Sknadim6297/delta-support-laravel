<?php

namespace App\Services;

use App\Models\Member;
use App\Models\SupportAttachment;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class SupportTicketWorkflow
{
    /** @var list<string> */
    public const MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'application/pdf',
    ];

    /** @var array<string, string> */
    private const EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
    ];

    public function __construct(private readonly SupportNotifier $notifier)
    {
    }

    /**
     * @param  array{subject: string, category: string, priority: string, message: string, idempotency_key: string}  $input
     */
    public function open(Member $member, array $input, ?UploadedFile $file = null): SupportTicket
    {
        $key = $this->uuid($input['idempotency_key'] ?? '', 'idempotency_key');
        $existing = SupportTicket::query()->where('idempotency_key', $key)->first();
        if ($existing) {
            if ($existing->member_id !== $member->member_id) {
                throw new RuntimeException('This submission key belongs to another member.');
            }

            return $existing;
        }

        $subject = $this->text($input['subject'] ?? '', 150, 'subject');
        $body = $this->text($input['message'] ?? '', 5000, 'message');
        $category = (string) ($input['category'] ?? '');
        $priority = (string) ($input['priority'] ?? '');
        if (! array_key_exists($category, SupportTicket::CATEGORY_LABELS)) {
            throw ValidationException::withMessages(['category' => 'Choose a support category.']);
        }
        if (! in_array($priority, SupportTicket::PRIORITIES, true)) {
            throw ValidationException::withMessages(['priority' => 'Choose a priority.']);
        }

        return DB::transaction(function () use ($member, $subject, $body, $category, $priority, $key, $file): SupportTicket {
            $again = SupportTicket::query()->where('idempotency_key', $key)->first();
            if ($again) {
                return $again;
            }

            $ticket = SupportTicket::query()->create([
                'reference' => 'TMP-'.Str::uuid(),
                'member_id' => $member->member_id,
                'subject' => $subject,
                'category' => $category,
                'priority' => $priority,
                'status' => SupportTicket::OPEN,
                'idempotency_key' => $key,
            ]);
            $ticket->forceFill([
                'reference' => 'TKT'.str_pad((string) $ticket->id, 6, '0', STR_PAD_LEFT),
            ])->save();
            $this->message($ticket, SupportMessage::ROLE_MEMBER, $body, (string) Str::uuid(), $member->member_id, null, $file);
            $this->notifier->admins($ticket->fresh(), 'New ticket '.$ticket->reference.' from '.$member->member_id.'.');

            return $ticket->fresh();
        });
    }

    public function replyAsMember(SupportTicket $ticket, Member $member, string $body, string $key, ?UploadedFile $file = null): SupportMessage
    {
        $this->assertOwner($ticket, $member);
        if (! in_array($ticket->status, [SupportTicket::OPEN, SupportTicket::AWAITING_ADMIN, SupportTicket::AWAITING_MEMBER], true)) {
            throw ValidationException::withMessages(['message' => 'This ticket is closed to new replies.']);
        }

        return DB::transaction(function () use ($ticket, $member, $body, $key, $file): SupportMessage {
            $locked = SupportTicket::query()->whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            $key = $this->uuid($key, 'idempotency_key');
            $already = SupportMessage::query()->where('idempotency_key', $key)->exists();
            $message = $this->message($locked, SupportMessage::ROLE_MEMBER, $this->text($body, 5000, 'message'), $key, $member->member_id, null, $file);
            if ($already) {
                return $message;
            }
            if ($locked->status !== SupportTicket::AWAITING_ADMIN) {
                $locked->forceFill(['status' => SupportTicket::AWAITING_ADMIN])->save();
            }
            $this->notifier->admins($locked, 'Member replied on '.$locked->reference.'.');

            return $message;
        });
    }

    public function replyAsAdmin(SupportTicket $ticket, User $admin, string $body, string $key, ?UploadedFile $file = null): SupportMessage
    {
        if ($ticket->status === SupportTicket::CLOSED) {
            throw ValidationException::withMessages(['message' => 'Reopen the ticket before replying.']);
        }

        return DB::transaction(function () use ($ticket, $admin, $body, $key, $file): SupportMessage {
            $locked = SupportTicket::query()->whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === SupportTicket::CLOSED) {
                throw ValidationException::withMessages(['message' => 'Reopen the ticket before replying.']);
            }
            $key = $this->uuid($key, 'idempotency_key');
            $already = SupportMessage::query()->where('idempotency_key', $key)->exists();
            $message = $this->message($locked, SupportMessage::ROLE_ADMIN, $this->text($body, 5000, 'message'), $key, null, (int) $admin->id, $file);
            if ($already) {
                return $message;
            }
            $locked->forceFill([
                'status' => SupportTicket::AWAITING_MEMBER,
                'closed_at' => null,
                'closed_by' => null,
            ])->save();
            $this->notifier->member($locked, 'Support replied on '.$locked->reference.'.');

            return $message;
        });
    }

    public function closeAsMember(SupportTicket $ticket, Member $member): SupportTicket
    {
        $this->assertOwner($ticket, $member);

        return $this->transition($ticket, SupportTicket::CLOSED, 'member:'.$member->member_id);
    }

    public function changeStatus(SupportTicket $ticket, User $admin, string $status): SupportTicket
    {
        if (! in_array($status, SupportTicket::STATUSES, true) || $status === SupportTicket::OPEN) {
            throw ValidationException::withMessages(['status' => 'Choose a permitted status.']);
        }

        return $this->transition($ticket, $status, 'admin:'.$admin->id);
    }

    private function transition(SupportTicket $ticket, string $status, string $actor): SupportTicket
    {
        return DB::transaction(function () use ($ticket, $status, $actor): SupportTicket {
            $locked = SupportTicket::query()->whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            if (! $this->canMove($locked->status, $status, str_starts_with($actor, 'admin:'))) {
                throw ValidationException::withMessages(['status' => 'That status change is not available.']);
            }
            $locked->forceFill([
                'status' => $status,
                'closed_at' => $status === SupportTicket::CLOSED ? now() : null,
                'closed_by' => $status === SupportTicket::CLOSED ? $actor : null,
            ])->save();

            return $locked->fresh();
        });
    }

    private function canMove(string $from, string $to, bool $admin): bool
    {
        if ($from === $to) {
            return false;
        }
        if ($to === SupportTicket::CLOSED) {
            return $from !== SupportTicket::CLOSED;
        }
        if (! $admin) {
            return false;
        }
        if ($from === SupportTicket::CLOSED) {
            return $to === SupportTicket::AWAITING_ADMIN;
        }

        return in_array($to, [SupportTicket::AWAITING_ADMIN, SupportTicket::AWAITING_MEMBER, SupportTicket::RESOLVED], true);
    }

    private function message(SupportTicket $ticket, string $role, string $body, string $key, ?string $memberId, ?int $userId, ?UploadedFile $file): SupportMessage
    {
        $existing = SupportMessage::query()->where('idempotency_key', $key)->first();
        if ($existing) {
            if ((int) $existing->support_ticket_id !== (int) $ticket->id) {
                throw new RuntimeException('This submission key belongs to another ticket.');
            }

            return $existing;
        }

        $message = SupportMessage::query()->create([
            'support_ticket_id' => $ticket->id,
            'sender_role' => $role,
            'sender_member_id' => $memberId,
            'sender_user_id' => $userId,
            'body' => $body,
            'idempotency_key' => $key,
        ]);
        if ($file) {
            $this->storeFile($message, $file);
        }

        return $message;
    }

    private function storeFile(SupportMessage $message, UploadedFile $file): SupportAttachment
    {
        $mime = (string) $file->getMimeType();
        $extension = self::EXTENSIONS[$mime] ?? null;
        if ($extension === null || ! in_array($mime, self::MIME_TYPES, true)) {
            throw ValidationException::withMessages(['attachment' => 'Attach a JPG, PNG, WebP, or PDF file.']);
        }
        if ($file->getSize() > 2 * 1024 * 1024) {
            throw ValidationException::withMessages(['attachment' => 'The attachment must be 2 MB or smaller.']);
        }

        $path = 'support-attachments/'.Str::uuid().'.'.$extension;
        $stored = $file->storeAs('support-attachments', basename($path), 'local');
        if (! is_string($stored) || ! str_starts_with($stored, 'support-attachments/') || str_contains($stored, '..')) {
            throw new RuntimeException('The attachment could not be stored.');
        }

        return SupportAttachment::query()->create([
            'support_message_id' => $message->id,
            'disk' => 'local',
            'path' => $stored,
            'original_name' => $this->originalName($file->getClientOriginalName()),
            'mime' => $mime,
            'size' => (int) $file->getSize(),
        ]);
    }

    private function originalName(string $name): string
    {
        $name = str_replace(["\0", '\\', '/'], '', $name);
        $name = trim($name);
        if ($name === '') {
            return 'attachment';
        }

        return mb_substr($name, 0, 120);
    }

    private function assertOwner(SupportTicket $ticket, Member $member): void
    {
        if ($ticket->member_id !== $member->member_id) {
            throw new RuntimeException('This ticket belongs to another member.');
        }
    }

    private function text(string $value, int $max, string $key): string
    {
        $value = trim($value);
        if ($value === '' || mb_strlen($value) > $max) {
            throw ValidationException::withMessages([$key => 'Enter a message within the allowed length.']);
        }

        return $value;
    }

    private function uuid(string $value, string $key): string
    {
        $value = trim($value);
        if (! Str::isUuid($value)) {
            throw ValidationException::withMessages([$key => 'Submit the form once with a valid key.']);
        }

        return $value;
    }
}
