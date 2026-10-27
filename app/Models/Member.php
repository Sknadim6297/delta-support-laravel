<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Member extends Model
{
    use HasFactory;

    protected $hidden = [
        'password',
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
    ];

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
