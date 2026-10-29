<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportAttachment extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'support_message_id',
        'disk',
        'path',
        'original_name',
        'mime',
        'size',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(SupportMessage::class, 'support_message_id');
    }
}
