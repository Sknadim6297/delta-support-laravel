<?php

namespace App\Services;

use App\Models\Member;
use Illuminate\Validation\ValidationException;

/**
 * The configured admin root is a terminal sponsor. It is valid without a member row.
 * It never receives commission, and a circular chain is rejected before it is saved.
 */
class SponsorChain
{
    public function rootId(): string
    {
        return strtoupper((string) config('deltasupport.package_income.admin_root_id', 'ST666666'));
    }

    public function isRoot(?string $sponsorId): bool
    {
        return strtoupper(trim((string) $sponsorId)) === $this->rootId();
    }

    public function assertAssignable(string $memberId, string $sponsorId): void
    {
        $memberId = strtoupper(trim($memberId));
        $sponsorId = strtoupper(trim($sponsorId));

        if ($sponsorId === '' || $sponsorId === $memberId) {
            throw ValidationException::withMessages([
                'sponsor_id' => 'A member cannot sponsor themselves.',
            ]);
        }

        if ($this->isRoot($sponsorId)) {
            return;
        }

        if (! Member::query()->where('member_id', $sponsorId)->exists()) {
            throw ValidationException::withMessages([
                'sponsor_id' => 'Invalid Sponsor ID.',
            ]);
        }

        $this->assertChainEnds($sponsorId, $memberId);
    }

    public function assertChainEnds(string $sponsorId, ?string $memberId = null): void
    {
        $sponsorId = strtoupper(trim($sponsorId));
        if ($this->isRoot($sponsorId)) {
            return;
        }

        $seen = [];
        if ($memberId !== null && $memberId !== '') {
            $seen[strtoupper(trim($memberId))] = true;
        }
        $cursor = $sponsorId;

        for ($step = 0; $step < 50 && filled($cursor); $step++) {
            $cursor = strtoupper(trim((string) $cursor));
            if (isset($seen[$cursor])) {
                throw ValidationException::withMessages([
                    'sponsor_id' => 'This sponsor relationship is circular. No sponsor was changed and no commission was reassigned.',
                ]);
            }
            $seen[$cursor] = true;
            if ($this->isRoot($cursor)) {
                return;
            }
            $next = Member::query()->where('member_id', $cursor)->value('sponsor_id');
            if (! filled($next)) {
                return;
            }
            $cursor = (string) $next;
        }
    }
}
