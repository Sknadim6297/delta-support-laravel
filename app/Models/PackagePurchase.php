<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PackagePurchase extends Model
{
    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::PENDING => 'Pending Verification',
            self::APPROVED => 'Active',
            self::REJECTED => 'Rejected',
            default => ucfirst((string) $this->status),
        };
    }

    public function paymentStatusLabel(): string
    {
        return match ($this->status) {
            self::APPROVED => 'Approved',
            self::REJECTED => 'Rejected',
            default => 'Pending',
        };
    }

    public function activationLabel(): string
    {
        return $this->status === self::APPROVED ? 'Active' : 'Not active';
    }

    public function statusTone(): string
    {
        return match ($this->status) {
            self::APPROVED => 'success',
            self::REJECTED => 'danger',
            default => 'warning',
        };
    }

    protected $fillable = [
        'reference',
        'idempotency_key',
        'member_id',
        'package_id',
        'package_code',
        'package_name',
        'package_type',
        'price_usd',
        'denomination',
        'tour_title',
        'tour_details',
        'duration',
        'benefits',
        'description',
        'full_description',
        'destinations',
        'inclusions',
        'exclusions',
        'currency_label',
        'network',
        'payment_reference',
        'status',
        'rejection_reason',
        'submitted_at',
        'reviewed_at',
        'reviewed_by',
    ];

    protected $casts = [
        'price_usd' => 'decimal:4',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id', 'member_id');
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    public function incomeTerm(): HasOne
    {
        return $this->hasOne(PackageIncomeTerm::class);
    }

    public function decisions(): HasMany
    {
        return $this->hasMany(PaymentDecision::class);
    }
}
