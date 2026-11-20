<?php

namespace App\Services\Rewards;

use App\Models\PackagePurchase;
use App\Services\LocalTestMarker;
use App\Services\ReferralLevelEligibility;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Approved USDT package business for reward campaigns and member summaries.
 *
 * The included people follow RankService::calculateForMembers: the member plus
 * every sponsor-downline member, not direct referrals only. RankService sums
 * legacy investments. This service does not. It sums stored package purchase
 * prices, because reward campaigns and member business summaries use package business.
 *
 * A purchase counts only when all of the following are true:
 * - status is approved and reviewed_at is set (the approval instant)
 * - denomination is USDT (historical USD is left out, not converted)
 * - the package type is one ReferralLevelEligibility already treats as qualifying,
 *   or the package code is one of the configured income packages and that package
 *   is level-commission eligible
 * - package code is not VERIFY-1
 * - the payment reference is not a local-test reference
 * - the buyer email is not a local-test email
 * - for campaign calculations, reviewed_at falls inside the campaign window in Asia/Kolkata
 *
 * Each purchase row is summed once from its stored price_usd. Repeat purchases
 * each add their own snapshot. There is no package refund or reversal table,
 * so approved amounts are not reduced. Catalog prices are not read.
 */
class TeamBusinessCalculationService
{
    private const SCALE = 4;

    /** @var array<string, list<string>>|null */
    private ?array $children = null;

    /** @var list<string> */
    private array $memberIds = [];

    /** @var array<string, string> */
    private array $sponsors = [];

    /** @var array<string, string> */
    private array $own = [];

    /** @var array<string, string> */
    private array $memo = [];

    /**
     * @param  list<string>  $memberIds
     * @return array<string, string>
     */
    public function usdtForMembers(array $memberIds, string $fromDate, string $toDate): array
    {
        $this->children = null;
        $this->memberIds = [];
        $this->sponsors = [];
        $this->loadChildren();
        $this->own = $this->purchaseSums($fromDate, $toDate);
        $this->memo = [];
        $totals = [];

        foreach ($memberIds as $memberId) {
            $seen = [];
            $totals[$memberId] = $this->subtree($memberId, $seen);
        }

        return $totals;
    }

    /**
     * Lifetime eligible approved USDT package business for each requested member.
     *
     * @param  list<string>  $memberIds
     * @return array<string, array{first_level_business: string, total_team_business: string}>
     */
    public function businessForMembers(array $memberIds): array
    {
        $requested = array_values(array_unique(array_map('strval', $memberIds)));
        if ($requested === []) {
            return [];
        }

        $this->children = null;
        $this->memberIds = [];
        $this->sponsors = [];
        $this->loadChildrenForRoots($requested);
        $this->own = $this->purchaseSums(null, null, $this->memberIds);

        $direct = array_fill_keys($requested, '0.0000');
        foreach ($requested as $memberId) {
            foreach ($this->children[$memberId] ?? [] as $childId) {
                if ($childId !== $memberId) {
                    $direct[$memberId] = bcadd($direct[$memberId], $this->own[$childId] ?? '0.0000', self::SCALE);
                }
            }
        }

        $totals = [];
        $remainingChildren = [];
        $ready = [];
        foreach ($this->memberIds as $memberId) {
            $totals[$memberId] = $this->own[$memberId] ?? '0.0000';
            $remainingChildren[$memberId] = count($this->children[$memberId] ?? []);
            if ($remainingChildren[$memberId] === 0) {
                $ready[] = $memberId;
            }
        }

        for ($cursor = 0; $cursor < count($ready); $cursor++) {
            $memberId = $ready[$cursor];
            $sponsorId = $this->sponsors[$memberId] ?? '';

            if (! isset($totals[$sponsorId]) || $sponsorId === $memberId) {
                continue;
            }

            $totals[$sponsorId] = bcadd($totals[$sponsorId], $totals[$memberId], self::SCALE);
            $remainingChildren[$sponsorId]--;
            if ($remainingChildren[$sponsorId] === 0) {
                $ready[] = $sponsorId;
            }
        }

        $summaries = [];
        foreach ($requested as $memberId) {
            $summaries[$memberId] = [
                'first_level_business' => $direct[$memberId],
                'total_team_business' => $totals[$memberId] ?? ($this->own[$memberId] ?? '0.0000'),
            ];
        }

        return $summaries;
    }

    /**
     * Load only the requested members and their descendants, in batches by tree level.
     *
     * @param  list<string>  $roots
     */
    private function loadChildrenForRoots(array $roots): void
    {
        $this->children = [];
        $this->memberIds = $roots;
        $this->sponsors = [];
        $scheduled = array_fill_keys($roots, true);
        $expanded = [];
        $frontier = $roots;

        while ($frontier !== []) {
            $next = [];
            foreach (array_chunk($frontier, 500) as $sponsorIds) {
                $rows = DB::table('members')
                    ->select(['member_id', 'sponsor_id'])
                    ->whereIn('sponsor_id', $sponsorIds)
                    ->get();

                foreach ($rows as $row) {
                    $memberId = (string) $row->member_id;
                    $sponsorId = (string) $row->sponsor_id;
                    if ($memberId === $sponsorId) {
                        continue;
                    }

                    $this->children[$sponsorId][] = $memberId;
                    $this->sponsors[$memberId] = $sponsorId;
                    if (! isset($scheduled[$memberId])) {
                        $scheduled[$memberId] = true;
                        $this->memberIds[] = $memberId;
                        $next[] = $memberId;
                    }
                }
            }

            foreach ($frontier as $memberId) {
                $expanded[$memberId] = true;
            }
            $frontier = array_values(array_filter(
                array_unique($next),
                fn (string $memberId): bool => ! isset($expanded[$memberId])
            ));
        }
    }

    private function loadChildren(): void
    {
        if ($this->children !== null) {
            return;
        }

        $this->children = [];
        $rows = DB::table('members')->select(['member_id', 'sponsor_id'])->get();
        foreach ($rows as $row) {
            $memberId = (string) $row->member_id;
            $sponsorId = (string) $row->sponsor_id;
            $this->memberIds[] = $memberId;
            $this->sponsors[$memberId] = $sponsorId;
            $this->children[$sponsorId][] = $memberId;
        }
    }

    /**
     * @param  array<string, true>  $seen
     */
    private function subtree(string $memberId, array &$seen): string
    {
        if (isset($this->memo[$memberId])) {
            return $this->memo[$memberId];
        }

        if (isset($seen[$memberId])) {
            return '0.0000';
        }

        $seen[$memberId] = true;
        $total = $this->own[$memberId] ?? '0.0000';
        foreach ($this->children[$memberId] ?? [] as $childId) {
            if ($childId === $memberId) {
                continue;
            }
            $total = bcadd($total, $this->subtree($childId, $seen), self::SCALE);
        }

        return $this->memo[$memberId] = $total;
    }

    /**
     * @return array<string, string>
     */
    private function purchaseSums(?string $fromDate = null, ?string $toDate = null, ?array $memberIds = null): array
    {
        if ($memberIds === []) {
            return [];
        }

        $codes = array_keys(config('deltasupport.package_income.packages', []));
        $memberChunks = $memberIds === null ? [null] : array_chunk(array_values(array_unique($memberIds)), 500);
        $sums = [];

        foreach ($memberChunks as $memberChunk) {
            $query = DB::table('package_purchases')
                ->where('status', PackagePurchase::APPROVED)
                ->whereNotNull('reviewed_at')
                ->where('denomination', 'USDT')
                ->where('package_code', '!=', 'VERIFY-1')
                ->where(function ($reference): void {
                    $reference->whereNull('payment_reference')
                        ->orWhere('payment_reference', 'not like', LocalTestMarker::PAYMENT_PREFIX.'%');
                })
                ->whereNotExists(function ($members): void {
                    $members->selectRaw('1')
                        ->from('members')
                        ->whereColumn('members.member_id', 'package_purchases.member_id')
                        ->where('members.email', 'like', '%'.LocalTestMarker::EMAIL_DOMAIN);
                })
                ->where(function ($purchase) use ($codes): void {
                    $purchase->whereIn('package_type', ReferralLevelEligibility::QUALIFYING_PACKAGE_TYPES);
                    if ($codes !== []) {
                        $purchase->orWhere(function ($named) use ($codes): void {
                            $named->whereIn('package_code', $codes)
                                ->whereExists(function ($package): void {
                                    $package->selectRaw('1')
                                        ->from('packages')
                                        ->whereColumn('packages.id', 'package_purchases.package_id')
                                        ->where('packages.level_commission_eligible', true);
                                });
                        });
                    }
                })
                ->groupBy('member_id')
                ->selectRaw('member_id, SUM(price_usd) as business');
            if ($memberChunk !== null) {
                $query->whereIn('member_id', $memberChunk);
            }

            if ($fromDate !== null && $toDate !== null) {
                $from = CarbonImmutable::parse($fromDate, 'Asia/Kolkata')->startOfDay()->utc();
                $to = CarbonImmutable::parse($toDate, 'Asia/Kolkata')->endOfDay()->utc();
                $query->where('reviewed_at', '>=', $from->toDateTimeString())
                    ->where('reviewed_at', '<=', $to->toDateTimeString());
            }

            foreach ($query->get() as $row) {
                $sums[(string) $row->member_id] = bcadd((string) $row->business, '0', self::SCALE);
            }
        }

        return $sums;
    }
}
