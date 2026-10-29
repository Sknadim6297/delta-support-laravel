<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportNotification extends Model
{
    public const UPDATED_AT = null;

    public const AUDIENCE_MEMBER = 'member';

    public const AUDIENCE_ADMIN = 'admin';

    public const EMAIL_SENT = 'sent';

    public const EMAIL_UNAVAILABLE = 'unavailable';

    public const EMAIL_FAILED = 'failed';

    public const EMAIL_SKIPPED = 'skipped';

    protected $fillable = [
        'audience',
        'member_id',
        'user_id',
        'support_ticket_id',
        'summary',
        'email_status',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'support_ticket_id');
    }
}
