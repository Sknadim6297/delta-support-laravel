<?php

namespace App\Services;

use App\Models\Member;
use App\Models\PackagePurchase;

class LocalTestMarker
{
    public const EMAIL_DOMAIN = '@local-test.invalid';

    public const PAYMENT_PREFIX = 'LOCAL-TEST-';

    public const LABEL = 'Local test';

    public function isEmail(?string $email): bool
    {
        return str_ends_with(strtolower((string) $email), self::EMAIL_DOMAIN);
    }

    public function isPurchase(?PackagePurchase $purchase): bool
    {
        if ($purchase === null || ! str_starts_with((string) $purchase->payment_reference, self::PAYMENT_PREFIX)) {
            return false;
        }

        $email = Member::query()->where('member_id', $purchase->member_id)->value('email');

        return $this->isEmail(is_string($email) ? $email : null);
    }

    /**
     * @return list<string>
     */
    public function memberIds(): array
    {
        return Member::query()
            ->where('email', 'like', '%'.self::EMAIL_DOMAIN)
            ->orderBy('id')
            ->pluck('member_id')
            ->all();
    }

    /**
     * @return list<int>
     */
    public function purchaseIds(): array
    {
        $members = $this->memberIds();
        if ($members === []) {
            return [];
        }

        return PackagePurchase::query()
            ->where('payment_reference', 'like', self::PAYMENT_PREFIX.'%')
            ->whereIn('member_id', $members)
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }
}
