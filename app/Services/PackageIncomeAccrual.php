<?php

namespace App\Services;

use App\Models\Member;
use App\Models\Package;
use App\Models\PackageLevelAccrual;
use App\Models\PackagePurchase;
use App\Models\PackageRoiAccrual;
use App\Support\WalletLedgerDirection;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class PackageIncomeAccrual
{
    public function __construct(
        private readonly PackageIncomePolicy $policy,
        private readonly ReferralLevelEligibility $eligibility,
        private readonly CommissionPlan $plan,
        private readonly WalletLedgerPoster $ledger,
    ) {
    }

    /**
     * Preview one business date. This never writes.
     *
     * @return array{date: string, enabled: bool, roi: list<array<string, string>>, commissions: list<array<string, string>>, skipped: list<string>}
     */
    public function preview(string $businessDate): array
    {
        return $this->previewPlan($businessDate, null);
    }

    /**
     * The same read-only plan financial:daily uses. Demonstration purchases are excluded.
     *
     * @return array{date: string, enabled: bool, roi: list<array<string, string>>, commissions: list<array<string, string>>, skipped: list<string>}
     */
    public function previewReal(string $businessDate): array
    {
        return $this->previewPlan($businessDate, function ($query): void {
            $this->excludeDemonstrationPurchases($query);
        });
    }

    /**
     * @return array{date: string, enabled: bool, roi: list<array<string, string>>, commissions: list<array<string, string>>, skipped: list<string>}
     */
    public function previewDemonstration(string $businessDate): array
    {
        return $this->previewPlan($businessDate, function ($query): void {
            $query->where('payment_reference', 'like', LocalTestMarker::PAYMENT_PREFIX.'%')
                ->whereExists(function ($members): void {
                    $members->selectRaw('1')
                        ->from('members')
                        ->whereColumn('members.member_id', 'package_purchases.member_id')
                        ->where('members.email', 'like', '%'.LocalTestMarker::EMAIL_DOMAIN);
                });
        });
    }

    /**
     * @return array{date: string, enabled: bool, roi: list<array<string, string>>, commissions: list<array<string, string>>, skipped: list<string>}
     */
    private function previewPlan(string $businessDate, ?callable $constrain): array
    {
        $date = $this->date($businessDate);
        $plan = $this->plan($date, $constrain);

        return [
            'date' => $date->toDateString(),
            'enabled' => $this->policy->enabled(),
            'roi' => $plan['roi'],
            'commissions' => $plan['commissions'],
            'skipped' => $plan['skipped'],
        ];
    }

    private function excludeDemonstrationPurchases(object $query): void
    {
        $query->where(function ($reference): void {
            $reference->whereNull('payment_reference')
                ->orWhere('payment_reference', 'not like', LocalTestMarker::PAYMENT_PREFIX.'%');
        })->whereNotExists(function ($members): void {
            $members->selectRaw('1')
                ->from('members')
                ->whereColumn('members.member_id', 'package_purchases.member_id')
                ->where('members.email', 'like', '%'.LocalTestMarker::EMAIL_DOMAIN);
        });
    }

    /**
     * One business date for the controlled daily cycle. Demo purchases are excluded.
     * A dry run writes nothing. Wallet columns move only when that crediting switch is on.
     *
     * @return array{dry_run: bool, roi_records: int, commission_records: int, skipped: list<string>}
     */
    public function runControlled(string $businessDate, bool $dryRun = false, bool $recoverYesterday = false): array
    {
        $controls = app(FinancialControls::class);
        $date = $this->date($businessDate);
        $timezone = (string) config('deltasupport.package_income.timezone', 'Asia/Kolkata');
        $today = CarbonImmutable::now($timezone)->toDateString();
        if (! $dryRun && ! $controls->enabled('daily_financial_cycle_enabled')) {
            throw new RuntimeException('The daily financial cycle is disabled. No wallet was credited.');
        }
        if (! $dryRun && $date->toDateString() !== $today) {
            $yesterday = CarbonImmutable::now($timezone)->subDay()->toDateString();
            if (! $recoverYesterday || $date->toDateString() !== $yesterday) {
                throw new RuntimeException('The daily cycle processes only the current business date. No earlier date was credited.');
            }
        }

        $calculateRoi = $dryRun || $controls->enabled('roi_calculation_enabled');
        $calculateLevel = $dryRun || $controls->enabled('level_calculation_enabled');
        if (! $dryRun && ! $calculateRoi && ! $calculateLevel) {
            throw new RuntimeException('ROI and level calculation are disabled. No wallet was credited.');
        }

        $plan = $this->plan($date, function ($query): void {
            $this->excludeDemonstrationPurchases($query);
        });

        if ($dryRun) {
            return [
                'dry_run' => true,
                'roi_records' => $calculateRoi ? count($plan['roi']) : 0,
                'commission_records' => $calculateLevel ? count($plan['commissions']) : 0,
                'skipped' => $plan['skipped'],
            ];
        }

        $lock = Cache::lock('package-income-'.$date->toDateString(), 120);
        if (! $lock->get()) {
            throw new RuntimeException('Package income for '.$date->toDateString().' is already running.');
        }

        try {
            $creditRoi = $controls->enabled('roi_wallet_crediting_enabled');
            $creditLevel = $controls->enabled('level_wallet_crediting_enabled');
            $roi = 0;
            $commissions = 0;
            if ($calculateRoi) {
                foreach ($plan['roi'] as $row) {
                    if ($this->creditRoi($row, 'Package ROI accrual', $creditRoi, false)) {
                        $roi++;
                    }
                }
            }
            if ($calculateLevel) {
                foreach ($plan['commissions'] as $row) {
                    if ($this->creditCommission($row, 'Package level commission accrual', $creditLevel, false)) {
                        $commissions++;
                    }
                }
            }

            return [
                'dry_run' => false,
                'roi_records' => $roi,
                'commission_records' => $commissions,
                'skipped' => $plan['skipped'],
            ];
        } finally {
            $lock->release();
        }
    }

    /**
     * Credit one business date. Refuses while package income is disabled.
     * It does not walk earlier dates.
     *
     * @return array{credited_roi: int, credited_commissions: int, skipped: list<string>}
     */
    public function accrue(string $businessDate): array
    {
        if (! $this->policy->enabled()) {
            throw new RuntimeException('Package income generation is disabled. No wallet was credited.');
        }

        $lock = Cache::lock('package-income-'.$businessDate, 120);
        if (! $lock->get()) {
            throw new RuntimeException('Package income for '.$businessDate.' is already running.');
        }

        try {
            $plan = $this->plan($this->date($businessDate));
            $roi = 0;
            $commissions = 0;
            $chunk = max(1, (int) config('deltasupport.package_income.chunk_size', 100));
            foreach (array_chunk($plan['roi'], $chunk) as $rows) {
                foreach ($rows as $row) {
                    if ($this->creditRoi($row)) {
                        $roi++;
                    }
                }
            }
            foreach (array_chunk($plan['commissions'], $chunk) as $rows) {
                foreach ($rows as $row) {
                    if ($this->creditCommission($row)) {
                        $commissions++;
                    }
                }
            }

        return [
            'credited_roi' => $roi,
            'credited_commissions' => $commissions,
            'skipped' => $plan['skipped'],
        ];
        } finally {
            $lock->release();
        }
    }

    /**
     * Credit one date for local-test purchases while production generation stays disabled.
     * An unmarked purchase or recipient refuses the whole run before any wallet write.
     *
     * @param  list<int>  $purchaseIds
     * @return array{credited_roi: int, credited_commissions: int, skipped: list<string>}
     */
    public function accruePurchases(string $businessDate, array $purchaseIds): array
    {
        $purchaseIds = $this->assertLocalPurchases($purchaseIds);
        $lock = Cache::lock('package-income-'.$businessDate, 120);
        if (! $lock->get()) {
            throw new RuntimeException('Package income for '.$businessDate.' is already running.');
        }

        try {
            $plan = $this->plan($this->date($businessDate), function ($query) use ($purchaseIds): void {
                $query->whereIn('id', $purchaseIds);
            });
            $this->assertPlannedRows($plan, $purchaseIds);
            $roi = 0;
            $commissions = 0;
            foreach ($plan['roi'] as $row) {
                if ($this->creditRoi($row, 'Local test package ROI accrual')) {
                    $roi++;
                }
            }
            foreach ($plan['commissions'] as $row) {
                if ($this->creditCommission($row, 'Local test package level commission accrual')) {
                    $commissions++;
                }
            }

            return [
                'credited_roi' => $roi,
                'credited_commissions' => $commissions,
                'skipped' => $plan['skipped'],
            ];
        } finally {
            $lock->release();
        }
    }

    /**
     * @param  list<int>  $purchaseIds
     * @return array{date: string, roi: list<array<string, string>>, commissions: list<array<string, string>>, skipped: list<string>}
     */
    public function previewPurchases(string $businessDate, array $purchaseIds): array
    {
        $purchaseIds = $this->assertLocalPurchases($purchaseIds);
        $date = $this->date($businessDate);
        $plan = $this->plan($date, function ($query) use ($purchaseIds): void {
            $query->whereIn('id', $purchaseIds);
        });
        $this->assertPlannedRows($plan, $purchaseIds);

        return [
            'date' => $date->toDateString(),
            'roi' => $plan['roi'],
            'commissions' => $plan['commissions'],
            'skipped' => $plan['skipped'],
        ];
    }

    /**
     * @param  list<int>  $purchaseIds
     * @return list<int>
     */
    private function assertLocalPurchases(array $purchaseIds): array
    {
        $purchaseIds = array_values(array_unique(array_map(intval(...), $purchaseIds)));
        if ($purchaseIds === []) {
            throw new RuntimeException('No local test purchase was selected. No wallet was credited.');
        }

        $marker = app(LocalTestMarker::class);
        $found = PackagePurchase::query()->whereIn('id', $purchaseIds)->get();
        if ($found->count() !== count($purchaseIds)) {
            throw new RuntimeException('A selected purchase does not exist. No wallet was credited.');
        }
        foreach ($found as $purchase) {
            if (! $marker->isPurchase($purchase)) {
                throw new RuntimeException('Refused. '.$purchase->member_id.' is not local test data. No wallet was credited.');
            }
        }

        return $purchaseIds;
    }

    /**
     * @param  array{roi: list<array<string, string>>, commissions: list<array<string, string>>, skipped: list<string>}  $plan
     * @param  list<int>  $purchaseIds
     */
    private function assertPlannedRows(array $plan, array $purchaseIds): void
    {
        $marker = app(LocalTestMarker::class);
        $allowed = array_flip($marker->memberIds());
        foreach ([...$plan['roi'], ...$plan['commissions']] as $row) {
            if (! in_array((int) $row['purchase_id'], $purchaseIds, true)) {
                throw new RuntimeException('Refused. A purchase outside the local test set was planned. No wallet was credited.');
            }
            $email = Member::query()->where('member_id', $row['member_id'])->value('email');
            if (! isset($allowed[$row['member_id']]) || ! $marker->isEmail(is_string($email) ? $email : null)) {
                throw new RuntimeException('Refused. '.$row['member_id'].' is not a local test member. No wallet was credited.');
            }
            if (isset($row['from_member_id']) && ! isset($allowed[$row['from_member_id']])) {
                throw new RuntimeException('Refused. Source member '.$row['from_member_id'].' is not local test data. No wallet was credited.');
            }
        }
    }

    /**
     * @return array{roi: list<array<string, string>>, commissions: list<array<string, string>>, skipped: list<string>}
     */
    private function plan(CarbonImmutable $date, ?callable $constrain = null): array
    {
        $roi = [];
        $commissions = [];
        $skipped = [];
        if ($this->plan->integrityErrors() !== []) {
            $skipped[] = 'The 20-level rate chart failed its integrity check, so no commission was planned.';
        }
        $ratesReady = $this->plan->integrityErrors() === [];

        $recipients = [];
        $chunk = max(1, (int) config('deltasupport.package_income.chunk_size', 100));

        $query = PackagePurchase::query()
            ->where('status', PackagePurchase::APPROVED)
            ->whereNotNull('reviewed_at');
        if ($constrain !== null) {
            $constrain($query);
        }
        $query->chunkById($chunk, function ($purchases) use (&$roi, &$commissions, &$skipped, &$recipients, $date, $ratesReady): void {
                $packages = Package::query()->whereIn('id', $purchases->pluck('package_id')->filter()->all())->get()->keyBy('id');
                foreach ($purchases as $purchase) {
                    $this->planPurchase($purchase, $packages->get($purchase->package_id), $date, $ratesReady, $roi, $commissions, $skipped, $recipients);
                }
            });

        return ['roi' => $roi, 'commissions' => $commissions, 'skipped' => array_values(array_unique($skipped))];
    }

    /**
     * @param  list<array<string, string>>  $roi
     * @param  list<array<string, string>>  $commissions
     * @param  list<string>  $skipped
     * @param  array<string, bool>  $recipients
     */
    private function planPurchase(PackagePurchase $purchase, ?Package $package, CarbonImmutable $date, bool $ratesReady, array &$roi, array &$commissions, array &$skipped, array &$recipients): void
    {
        $definition = $this->policy->definition($purchase->package_code);
        $first = $this->policy->firstAccrualDate($purchase);
        if ($definition === null || $first === null) {
            return;
        }

        if ($this->policy->roiEligible($purchase, $package) && $this->policy->withinWindow($date, $first, $definition['roi_days'])) {
            $count = PackageRoiAccrual::query()->where('package_purchase_id', $purchase->id)->count();
            if ($count >= $definition['roi_days']) {
                $skipped[] = $purchase->reference.' has already reached its ROI duration.';
            } else {
                $roi[] = [
                    'purchase_id' => (string) $purchase->id,
                    'reference' => 'PROI-'.$purchase->id.'-'.$date->format('Ymd'),
                    'member_id' => $purchase->member_id,
                    'package_code' => $purchase->package_code,
                    'package_name' => $purchase->package_name,
                    'business_date' => $date->toDateString(),
                    'amount' => $definition['roi_daily'],
                ];
            }
        }

        if (! $ratesReady || ! $this->policy->levelSourceEligible($purchase, $package)) {
            if ($definition['commission_days'] === null && $package?->level_commission_eligible) {
                $skipped[] = $purchase->package_name.' commission duration is not approved.';
            }

            return;
        }
        if (! $this->policy->withinWindow($date, $first, (int) $definition['commission_days'])) {
            return;
        }

        $source = Member::query()->where('member_id', $purchase->member_id)->first();
        if (! $source) {
            $skipped[] = $purchase->reference.' has no source member record.';

            return;
        }
        $this->commissionPlan($source, $purchase, $definition['commission_pool'], $date, $commissions, $skipped, $recipients);
    }

    /**
     * @param  list<array<string, string>>  $commissions
     * @param  list<string>  $skipped
     * @param  array<string, bool>  $recipients
     */
    private function commissionPlan(Member $source, PackagePurchase $purchase, string $pool, CarbonImmutable $date, array &$commissions, array &$skipped, array &$recipients): void
    {
        $cursor = $source->sponsor_id;
        $seen = [$source->member_id => true];

        for ($level = 1; $level <= ReferralLevelEligibility::MAX_LEVELS; $level++) {
            if (! filled($cursor) || isset($seen[$cursor])) {
                $skipped[] = 'Sponsor chain stopped at '.$source->member_id.' before level '.$level.'.';
                break;
            }
            $seen[$cursor] = true;
            if ($cursor === $this->policy->adminRootId()) {
                $skipped[] = 'Admin root receives no commission.';
                break;
            }
            $sponsor = Member::query()->where('member_id', $cursor)->first();
            if (! $sponsor) {
                $skipped[] = 'Sponsor '.$cursor.' has no member record, so no further level was paid.';
                break;
            }
            $next = $sponsor->sponsor_id;
            $unlocked = $this->eligibility->forMember($sponsor->member_id)['unlocked_levels'];
            $eligible = $recipients[$sponsor->member_id] ??= $this->recipientEligible($sponsor->member_id);
            if ($eligible && $unlocked >= $level && $sponsor->member_id !== $this->policy->adminRootId()) {
                $amount = $this->policy->levelAmount($pool, $level);
                if ($amount !== null && bccomp($amount, '0', 4) === 1) {
                    $commissions[] = [
                        'purchase_id' => (string) $purchase->id,
                        'reference' => 'PLVL-'.$purchase->id.'-'.$sponsor->member_id.'-'.$date->format('Ymd'),
                        'member_id' => $sponsor->member_id,
                        'from_member_id' => $source->member_id,
                        'from_member_name' => $source->member_name,
                        'level' => (string) $level,
                        'package_code' => $purchase->package_code,
                        'package_name' => $purchase->package_name,
                        'business_date' => $date->toDateString(),
                        'pool' => $pool,
                        'rate' => CommissionPlan::RATE_BY_LEVEL[$level],
                        'amount' => $amount,
                    ];
                }
            } else {
                $skipped[] = $sponsor->member_id.' was not paid at level '.$level.'.';
            }
            $cursor = $next;
        }
    }

    private function recipientEligible(string $memberId): bool
    {
        if ($memberId === $this->policy->adminRootId()) {
            return false;
        }

        return Member::query()
            ->where('member_id', $memberId)
            ->whereExists(function ($query): void {
                $this->eligibility->qualifyingPurchaseConstraint($query);
            })
            ->exists();
    }

    /**
     * @param  array<string, string>  $row
     */
    /**
     * @param  array<string, string>  $row
     */
    private function creditRoi(array $row, string $reason = 'Package ROI accrual', bool $creditWallet = true, bool $allowDemo = true): bool
    {
        return DB::transaction(function () use ($row, $reason, $creditWallet, $allowDemo): bool {
            $member = Member::query()->where('member_id', $row['member_id'])->lockForUpdate()->first();
            if (! $member || (! $allowDemo && app(LocalTestMarker::class)->isEmail($member->email))) {
                return false;
            }

            $key = $row['purchase_id'].'|'.$row['business_date'];
            $existing = DB::table('package_roi_accruals')->where('idempotency_key', $key)->lockForUpdate()->first();
            if ($existing) {
                return $creditWallet && $existing->status === 'calculated'
                    ? $this->postRoiWallet($member, $row, $reason, (int) $existing->id)
                    : false;
            }

            $inserted = DB::table('package_roi_accruals')->insertOrIgnore([
                'reference' => $row['reference'],
                'idempotency_key' => $key,
                'package_purchase_id' => $row['purchase_id'],
                'member_id' => $member->member_id,
                'member_name' => $member->member_name,
                'package_code' => $row['package_code'],
                'package_name' => $row['package_name'],
                'business_date' => $row['business_date'],
                'amount' => $row['amount'],
                'status' => 'calculated',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            if ($inserted !== 1 || ! $creditWallet) {
                return $inserted === 1;
            }

            $id = (int) DB::table('package_roi_accruals')->where('idempotency_key', $key)->value('id');

            return $this->postRoiWallet($member, $row, $reason, $id);
        });
    }

    /**
     * @param  array<string, string>  $row
     */
    private function postRoiWallet(Member $member, array $row, string $reason, int $accrualId): bool
    {
        $locked = DB::table('package_roi_accruals')->where('id', $accrualId)->lockForUpdate()->first();
        if (! $locked || $locked->status === 'accrued') {
            return false;
        }

        $opening = bcadd((string) ($member->roi_wallet_amount ?? '0'), '0', 4);
        $closing = bcadd($opening, $row['amount'], 4);
        $member->roi_wallet_amount = $closing;
        $member->save();
        DB::table('package_roi_accruals')->where('id', $accrualId)->update([
            'status' => 'accrued',
            'updated_at' => now(),
        ]);
        $this->ledger->append(
            WalletLedgerDirection::CREDIT,
            $member->member_id,
            'roi',
            $row['amount'],
            $row['reference'],
            (string) Str::uuid(),
            $reason,
            (int) $row['purchase_id'],
            $opening,
            $closing
        );

        return true;
    }

    /**
     * @param  array<string, string>  $row
     */
    /**
     * @param  array<string, string>  $row
     */
    private function creditCommission(array $row, string $reason = 'Package level commission accrual', bool $creditWallet = true, bool $allowDemo = true): bool
    {
        return DB::transaction(function () use ($row, $reason, $creditWallet, $allowDemo): bool {
            $member = Member::query()->where('member_id', $row['member_id'])->lockForUpdate()->first();
            if (! $member || $member->member_id === $this->policy->adminRootId() || (! $allowDemo && app(LocalTestMarker::class)->isEmail($member->email))) {
                return false;
            }

            $key = $row['purchase_id'].'|'.$row['member_id'].'|'.$row['business_date'];
            $existing = DB::table('package_level_accruals')->where('idempotency_key', $key)->lockForUpdate()->first();
            if ($existing) {
                return $creditWallet && $existing->status === 'calculated'
                    ? $this->postCommissionWallet($member, $row, $reason, (int) $existing->id)
                    : false;
            }

            $inserted = DB::table('package_level_accruals')->insertOrIgnore([
                'reference' => $row['reference'],
                'idempotency_key' => $key,
                'package_purchase_id' => $row['purchase_id'],
                'member_id' => $member->member_id,
                'member_name' => $member->member_name,
                'from_member_id' => $row['from_member_id'],
                'from_member_name' => $row['from_member_name'],
                'level' => $row['level'],
                'package_code' => $row['package_code'],
                'package_name' => $row['package_name'],
                'business_date' => $row['business_date'],
                'pool_amount' => $row['pool'],
                'rate_percentage' => $row['rate'],
                'amount' => $row['amount'],
                'status' => 'calculated',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            if ($inserted !== 1 || ! $creditWallet) {
                return $inserted === 1;
            }

            $id = (int) DB::table('package_level_accruals')->where('idempotency_key', $key)->value('id');

            return $this->postCommissionWallet($member, $row, $reason, $id);
        });
    }

    /**
     * @param  array<string, string>  $row
     */
    private function postCommissionWallet(Member $member, array $row, string $reason, int $accrualId): bool
    {
        $locked = DB::table('package_level_accruals')->where('id', $accrualId)->lockForUpdate()->first();
        if (! $locked || $locked->status === 'accrued') {
            return false;
        }

        $opening = bcadd((string) ($member->working_wallet_amount ?? '0'), '0', 4);
        $closing = bcadd($opening, $row['amount'], 4);
        $member->working_wallet_amount = $closing;
        $member->save();
        DB::table('package_level_accruals')->where('id', $accrualId)->update([
            'status' => 'accrued',
            'updated_at' => now(),
        ]);
        $this->ledger->append(
            WalletLedgerDirection::CREDIT,
            $member->member_id,
            'working',
            $row['amount'],
            $row['reference'],
            (string) Str::uuid(),
            $reason,
            (int) $row['purchase_id'],
            $opening,
            $closing
        );

        return true;
    }

    private function date(string $businessDate): CarbonImmutable
    {
        return CarbonImmutable::parse($businessDate, config('deltasupport.package_income.timezone'))->startOfDay();
    }
}
