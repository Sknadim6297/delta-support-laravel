<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Member extends Model
{
    use HasFactory;

    public const NAME_PATTERN = '/^[\p{L}\p{M}][\p{L}\p{M} .\'\x{2019}-]*$/u';

    /**
     * @return array<int, string>
     */
    public static function nameRules(): array
    {
        return ['required', 'string', 'min:3', 'max:100', 'regex:'.self::NAME_PATTERN];
    }

    protected $hidden = [
        'password',
        'security_pin_hash',
    ];

    protected $fillable = [
        'member_id',
        'rank_id',
        'sponsor_id',
        'sponsor_name',
        'member_name',
        'wallet_address',
        'activation_wallet_amount',
        'working_wallet_amount',
        'roi_wallet_amount',
        'mobile_no',
        'pan_card_no',
        'email',
        'password',
        'status',
    ];

    protected $casts = [
        'activation_wallet_amount' => 'decimal:4',
        'working_wallet_amount' => 'decimal:4',
        'roi_wallet_amount' => 'decimal:4',
        'security_pin_set_at' => 'datetime',
        'security_pin_locked_until' => 'datetime',
    ];

    public function hasSecurityPin(): bool
    {
        return is_string($this->security_pin_hash) && $this->security_pin_hash !== '';
    }

    public function securityPinResets(): HasMany
    {
        return $this->hasMany(MemberSecurityPinReset::class, 'member_id', 'member_id');
    }

    public function rank(): BelongsTo
    {
        return $this->belongsTo(Rank::class, 'rank_id');
    }

    public function sponsor(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'sponsor_id', 'member_id');
    }

    public function sponsoredMembers(): HasMany
    {
        return $this->hasMany(Member::class, 'sponsor_id', 'member_id');
    }

    public function investments(): HasMany
    {
        return $this->hasMany(Investment::class, 'member_id', 'member_id');
    }

    public function packagePurchases(): HasMany
    {
        return $this->hasMany(PackagePurchase::class, 'member_id', 'member_id');
    }

    /**
     * Approved and pending purchases used on admin member directories.
     */
    public function directoryPackagePurchases(): HasMany
    {
        return $this->packagePurchases()
            ->whereIn('status', [PackagePurchase::APPROVED, PackagePurchase::PENDING])
            ->orderByDesc('id');
    }

    /**
     * @return array{tone: string, badge: string, lines: list<string>}
     */
    public function packageDirectoryDisplay(): array
    {
        $purchases = $this->relationLoaded('directoryPackagePurchases')
            ? $this->directoryPackagePurchases
            : ($this->relationLoaded('packagePurchases')
                ? $this->packagePurchases
                    ->whereIn('status', [PackagePurchase::APPROVED, PackagePurchase::PENDING])
                    ->sortByDesc('id')
                    ->values()
                : $this->directoryPackagePurchases()->get());

        $approved = $purchases->where('status', PackagePurchase::APPROVED)->values();
        if ($approved->isNotEmpty()) {
            return [
                'tone' => 'success',
                'badge' => 'Approved package',
                'lines' => $approved->map(fn (PackagePurchase $purchase): string => $purchase->directoryLabel())->unique()->values()->all(),
            ];
        }

        $pending = $purchases->where('status', PackagePurchase::PENDING)->values();
        if ($pending->isNotEmpty()) {
            return [
                'tone' => 'warning',
                'badge' => 'Pending package',
                'lines' => $pending->map(fn (PackagePurchase $purchase): string => $purchase->directoryLabel())->unique()->values()->all(),
            ];
        }

        return [
            'tone' => 'muted',
            'badge' => 'Unpaid',
            'lines' => [],
        ];
    }

    public function roiTransactions(): HasMany
    {
        return $this->hasMany(RoiTransaction::class, 'member_id', 'member_id');
    }

    public static function authorizedMemberIds(string $rootMemberId): array
    {
        $authorizedIds = [$rootMemberId];
        $pending = [$rootMemberId];

        while ($pending !== []) {
            $children = self::query()
                ->whereIn('sponsor_id', $pending)
                ->pluck('member_id')
                ->all();

            $children = array_values(array_diff($children, $authorizedIds));

            if ($children === []) {
                break;
            }

            $authorizedIds = array_merge($authorizedIds, $children);
            $pending = $children;
        }

        return $authorizedIds;
    }
}
