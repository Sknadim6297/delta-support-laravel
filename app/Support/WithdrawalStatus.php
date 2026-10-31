<?php

namespace App\Support;

class WithdrawalStatus
{
    public const DRAFT = 'draft';

    public const PENDING_REVIEW = 'pending_review';

    public const APPROVED = 'approved';

    public const PROCESSING = 'processing';

    public const COMPLETED = 'completed';

    public const REJECTED = 'rejected';

    public const CANCELLED = 'cancelled';

    public const FAILED = 'failed';

    /**
     * @var list<string>
     */
    public const VALUES = [
        self::DRAFT,
        self::PENDING_REVIEW,
        self::APPROVED,
        self::PROCESSING,
        self::COMPLETED,
        self::REJECTED,
        self::CANCELLED,
        self::FAILED,
    ];

    /**
     * Permitted transitions. The status map itself does not move a balance.
     * Manual settlement debits the wallet in the same transaction that records Paid.
     *
     * @var array<string, list<string>>
     */
    public const TRANSITIONS = [
        self::DRAFT => [self::PENDING_REVIEW],
        self::PENDING_REVIEW => [self::APPROVED, self::REJECTED, self::CANCELLED],
        self::APPROVED => [self::PROCESSING, self::COMPLETED],
        self::PROCESSING => [self::COMPLETED, self::FAILED],
        self::COMPLETED => [],
        self::REJECTED => [],
        self::CANCELLED => [],
        self::FAILED => [],
    ];

    public static function label(string $status): string
    {
        return match ($status) {
            self::DRAFT => 'Draft',
            self::PENDING_REVIEW => 'Pending Review',
            self::APPROVED => 'Approved',
            self::PROCESSING => 'Processing',
            self::COMPLETED => 'Paid',
            self::REJECTED => 'Rejected',
            self::CANCELLED => 'Cancelled',
            self::FAILED => 'Failed',
            default => $status,
        };
    }

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }
}
