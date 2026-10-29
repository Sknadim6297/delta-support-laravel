<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportTicket extends Model
{
    public const OPEN = 'open';

    public const AWAITING_ADMIN = 'awaiting_admin';

    public const AWAITING_MEMBER = 'awaiting_member';

    public const RESOLVED = 'resolved';

    public const CLOSED = 'closed';

    /** @var list<string> */
    public const STATUSES = [
        self::OPEN,
        self::AWAITING_ADMIN,
        self::AWAITING_MEMBER,
        self::RESOLVED,
        self::CLOSED,
    ];

    /** @var list<string> */
    public const CATEGORIES = [
        'account',
        'wallet',
        'transfer',
        'withdrawal',
        'package',
        'income',
        'payment',
        'other',
    ];

    /** @var array<string, string> */
    public const CATEGORY_LABELS = [
        'account' => 'Account',
        'wallet' => 'Wallet',
        'transfer' => 'Transfer',
        'withdrawal' => 'Withdrawal',
        'package' => 'Package',
        'income' => 'ROI / Level Income',
        'payment' => 'Payment',
        'other' => 'Other',
    ];

    /** @var list<string> */
    public const PRIORITIES = ['low', 'normal', 'high'];

    protected $fillable = [
        'reference',
        'member_id',
        'subject',
        'category',
        'priority',
        'status',
        'idempotency_key',
        'closed_at',
        'closed_by',
    ];

    protected $casts = [
        'closed_at' => 'datetime',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id', 'member_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportMessage::class)->orderBy('id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(SupportNotification::class);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::OPEN => 'Open',
            self::AWAITING_ADMIN => 'Awaiting Admin',
            self::AWAITING_MEMBER => 'Awaiting Member',
            self::RESOLVED => 'Resolved',
            self::CLOSED => 'Closed',
            default => ucfirst((string) $this->status),
        };
    }

    public function statusTone(): string
    {
        return match ($this->status) {
            self::RESOLVED => 'success',
            self::CLOSED => 'muted',
            default => 'warning',
        };
    }

    public function categoryLabel(): string
    {
        return self::CATEGORY_LABELS[$this->category] ?? ucfirst((string) $this->category);
    }

    public function isClosed(): bool
    {
        return $this->status === self::CLOSED;
    }
}
