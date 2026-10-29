<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use RuntimeException;

class SupportMessage extends Model
{
    public const UPDATED_AT = null;

    public const ROLE_MEMBER = 'member';

    public const ROLE_ADMIN = 'admin';

    protected $fillable = [
        'support_ticket_id',
        'sender_role',
        'sender_member_id',
        'sender_user_id',
        'body',
        'idempotency_key',
    ];

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new RuntimeException('Support messages cannot be edited.');
        });
        static::deleting(function (): void {
            throw new RuntimeException('Support messages cannot be deleted.');
        });
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'support_ticket_id');
    }

    public function attachment(): HasOne
    {
        return $this->hasOne(SupportAttachment::class);
    }

    public function authorLabel(): string
    {
        if ($this->sender_role === self::ROLE_ADMIN) {
            return 'Support';
        }

        return $this->sender_member_id ?: 'Member';
    }
}
