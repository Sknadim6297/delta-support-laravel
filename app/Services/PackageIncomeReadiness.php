<?php

namespace App\Services;

use App\Models\LevelCommissionTransaction;
use App\Models\Member;
use App\Models\Package;
use App\Models\PackageIncomeTerm;
use App\Models\PackageLevelAccrual;
use App\Models\PackagePurchase;
use App\Models\PackageRoiAccrual;
use App\Models\RoiTransaction;
use Illuminate\Console\Scheduling\Schedule;

class PackageIncomeReadiness
{
    public const SAMPLE_MEMBER_IDS = [
        'ST100003',
        'ST910101',
        'ST910102',
        'ST100001',
        'ST100000',
    ];

    public function __construct(
        private readonly ReferralLevelEligibility $eligibility,
        private readonly FinancialScheduleRegistrar $scheduler,
        private readonly PackageIncomePolicy $policy,
    ) {
    }

    /**
     * @param  list<string>  $memberIds
     * @return array<string, mixed>
     */
    public function report(array $memberIds): array
    {
        $schedule = new Schedule;
        $this->scheduler->register($schedule);

        return [
            'calculation_available' => false,
            'scheduled_tasks' => count($schedule->events()),
            'income_terms' => PackageIncomeTerm::query()->count(),
            'missing_rules' => $this->missingRules(),
            'ledger' => $this->ledger(),
            'members' => array_map(fn (string $memberId): array => $this->member($memberId), $memberIds),
        ];
    }

    /**
     * @return list<string>
     */
    public function missingRules(): array
    {
        return [
            'Package income generation is disabled and is not on the scheduler.',
            'package-income:accrue records one business date only. It does not backfill earlier days.',
            'Coastal Weekend and Hill Station Retreat each use a 400-day level commission duration. No wallet is credited while generation is disabled.',
        ];
    }

    /**
     * @return array{roi_records: int, level_records: int, roi_wallet: string, working_wallet: string, activation_wallet: string}
     */
    public function ledger(): array
    {
        return [
            'roi_records' => RoiTransaction::query()->count() + PackageRoiAccrual::query()->count(),
            'level_records' => LevelCommissionTransaction::query()->count() + PackageLevelAccrual::query()->count(),
            'roi_wallet' => $this->moneySum('roi_wallet_amount'),
            'working_wallet' => $this->moneySum('working_wallet_amount'),
            'activation_wallet' => $this->moneySum('activation_wallet_amount'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function member(string $memberId): array
    {
        $member = Member::query()->where('member_id', $memberId)->first();
        if (! $member) {
            return [
                'member_id' => $memberId,
                'found' => false,
                'name' => null,
                'approved_packages' => [],
                'roi_eligible' => false,
                'level_eligible' => false,
                'sponsor_chain' => [],
                'sponsor_gap' => null,
                'unlocked_levels' => 0,
                'qualifying_directs' => 0,
                'roi' => $this->outcome('skipped', 'Member record was not found.'),
                'level' => $this->outcome('skipped', 'Member record was not found.'),
            ];
        }

        $approved = PackagePurchase::query()
            ->where('member_id', $member->member_id)
            ->where('status', PackagePurchase::APPROVED)
            ->orderBy('id')
            ->get();
        $packages = Package::query()
            ->whereIn('id', $approved->pluck('package_id')->filter()->all())
            ->get()
            ->keyBy('id');
        $chain = $this->sponsorChain($member);
        $unlock = $this->eligibility->forMember($member->member_id);
        $roiEligible = false;
        $levelEligible = false;
        $labels = [];

        foreach ($approved as $purchase) {
            $package = $packages->get($purchase->package_id);
            $labels[] = $purchase->package_name.' ('.$purchase->package_code.', '.$purchase->package_type.')';
            if ($package?->roi_eligible) {
                $roiEligible = true;
            }
            if (
                $package?->level_commission_eligible
                && in_array($purchase->package_type, ReferralLevelEligibility::QUALIFYING_PACKAGE_TYPES, true)
            ) {
                $levelEligible = true;
            }
        }

        return [
            'member_id' => $member->member_id,
            'found' => true,
            'name' => $member->member_name,
            'approved_packages' => $labels,
            'roi_eligible' => $roiEligible,
            'level_eligible' => $levelEligible,
            'sponsor_chain' => $chain['ids'],
            'sponsor_gap' => $chain['missing'],
            'unlocked_levels' => $unlock['unlocked_levels'],
            'qualifying_directs' => $unlock['qualifying_direct_count'],
            'roi' => $this->roiOutcome($approved, $packages),
            'level' => $this->levelOutcome($approved, $packages, $chain, $unlock),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, PackagePurchase>  $approved
     * @param  \Illuminate\Support\Collection<int, Package>  $packages
     * @return array{status: string, reason: string}
     */
    private function roiOutcome($approved, $packages): array
    {
        if ($approved->isEmpty()) {
            return $this->outcome('skipped', 'No approved package purchase.');
        }

        $known = $approved->filter(fn (PackagePurchase $purchase): bool => $this->policy->roiEligible($purchase, $packages->get($purchase->package_id)));
        if ($known->isNotEmpty()) {
            $parts = $known->map(function (PackagePurchase $purchase): string {
                $rule = $this->policy->definition($purchase->package_code);
                $first = $this->policy->firstAccrualDate($purchase)?->format('d M Y');

                return $purchase->package_name.' daily ROI '.$rule['roi_daily'].' for '.$rule['roi_days'].' days from '.$first;
            })->implode('; ');

            return $this->outcome('preview', $parts.'. Generation is disabled. No wallet was credited.');
        }

        $flagged = $approved->contains(fn (PackagePurchase $purchase): bool => (bool) $packages->get($purchase->package_id)?->roi_eligible);
        if (! $flagged) {
            return $this->outcome('skipped', 'The approved package is not marked ROI eligible.');
        }

        return $this->outcome('blocked', 'ROI eligibility is stored, but no package ROI percentage, duration, or accrual rule is stored. No amount was calculated.');
    }

    /**
     * @param  \Illuminate\Support\Collection<int, PackagePurchase>  $approved
     * @param  \Illuminate\Support\Collection<int, Package>  $packages
     * @param  array{ids: list<string>, missing: ?string}  $chain
     * @param  array{qualifying_direct_count: int, unlocked_levels: int}  $unlock
     * @return array{status: string, reason: string}
     */
    private function levelOutcome($approved, $packages, array $chain, array $unlock): array
    {
        if ($approved->isEmpty()) {
            return $this->outcome('skipped', 'No approved package purchase.');
        }

        $qualifying = $approved->filter(fn (PackagePurchase $purchase): bool => $this->policy->qualifiesAsDirect($purchase, $packages->get($purchase->package_id)));
        if ($qualifying->isEmpty()) {
            return $this->outcome('skipped', 'The approved purchase is not a level-income eligible package type.');
        }

        $reasons = [];
        if ($chain['missing'] !== null) {
            $reasons[] = 'Sponsor '.$chain['missing'].' has no member record, so the upline chain is incomplete.';
        }
        if ($unlock['unlocked_levels'] < 1) {
            $reasons[] = $unlock['qualifying_direct_count'].' qualifying directs unlock 0 levels.';
        }
        if ($reasons !== []) {
            return $this->outcome('skipped', implode(' ', $reasons));
        }

        $defined = $qualifying->first(fn (PackagePurchase $purchase): bool => $this->policy->definition($purchase->package_code) !== null);
        if ($defined && $this->policy->definition($defined->package_code)['commission_days'] === null) {
            return $this->outcome('blocked', $defined->package_name.' commission duration is not approved. No amount was calculated.');
        }

        $payable = $qualifying->first(fn (PackagePurchase $purchase): bool => $this->policy->levelSourceEligible($purchase, $packages->get($purchase->package_id)));
        if ($payable) {
            $rule = $this->policy->definition($payable->package_code);
            $daily = $this->policy->levelAmount($rule['commission_pool'], 1);

            return $this->outcome('preview', 'Daily pool '.$rule['commission_pool'].' for '.$rule['commission_days'].' days. Level 1 is '.$daily.'. Generation is disabled. No wallet was credited.');
        }

        $depth = count($chain['ids']);

        return $this->outcome(
            'blocked',
            $unlock['unlocked_levels'].' levels are unlocked from '.$unlock['qualifying_direct_count'].' qualifying directs. The stored sponsor chain has '.$depth.' upline member(s). No commission base is stored, so no amount was calculated.'
        );
    }

    /**
     * @return array{ids: list<string>, missing: ?string}
     */
    private function sponsorChain(Member $member): array
    {
        $ids = [];
        $missing = null;
        $current = $member->sponsor_id;
        $seen = [$member->member_id => true];

        for ($step = 0; $step < ReferralLevelEligibility::MAX_LEVELS && filled($current); $step++) {
            if (isset($seen[$current]) || strcasecmp((string) $current, $this->policy->adminRootId()) === 0) {
                break;
            }
            $seen[$current] = true;
            $sponsor = Member::query()->where('member_id', $current)->first(['member_id', 'sponsor_id']);
            if (! $sponsor) {
                $missing = $current;
                break;
            }
            $ids[] = $sponsor->member_id;
            $current = $sponsor->sponsor_id;
        }

        return ['ids' => $ids, 'missing' => $missing];
    }

    /**
     * @return array{status: string, reason: string}
     */
    private function outcome(string $status, string $reason): array
    {
        return ['status' => $status, 'reason' => $reason];
    }

    private function moneySum(string $column): string
    {
        $total = '0.0000';
        foreach (Member::query()->pluck($column) as $amount) {
            $total = bcadd($total, (string) $amount, 4);
        }

        return $total;
    }
}
