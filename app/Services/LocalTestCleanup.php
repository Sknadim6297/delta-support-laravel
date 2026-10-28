<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Member;
use App\Models\PackageIncomeRun;
use App\Models\PackageLevelAccrual;
use App\Models\PackagePurchase;
use App\Models\PackageRoiAccrual;
use App\Models\PaymentDecision;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class LocalTestCleanup
{
    public function __construct(
        private readonly LocalTestMarker $marker,
        private readonly LocalDatabaseGuard $guard,
    ) {
    }

    /**
     * @return array<string, int|string>
     */
    public function inspect(): array
    {
        $this->guard->assertOriginalLocal();
        $members = $this->marker->memberIds();
        $purchases = $this->marker->purchaseIds();
        $blocked = $this->dependencyError($members, $purchases);

        return [
            'database' => LocalDatabaseGuard::DATABASE,
            'members' => count($members),
            'purchases' => count($purchases),
            'roi_rows' => $purchases === [] ? 0 : PackageRoiAccrual::query()->whereIn('package_purchase_id', $purchases)->count(),
            'level_rows' => $purchases === [] ? 0 : PackageLevelAccrual::query()->whereIn('package_purchase_id', $purchases)->count(),
            'ledger_rows' => $members === [] ? 0 : DB::table('wallet_ledger_entries')->whereIn('member_id', $members)->count(),
            'invoices' => $purchases === [] ? 0 : Invoice::query()->whereIn('package_purchase_id', $purchases)->count(),
            'runs' => PackageIncomeRun::query()->where('message', 'like', 'LOCAL TEST%')->count(),
            'blocked' => $blocked ?? '',
        ];
    }

    public function delete(): array
    {
        $report = $this->inspect();
        if ($report['blocked'] !== '') {
            throw new RuntimeException((string) $report['blocked'].' No record was deleted.');
        }
        if ($report['members'] === 0) {
            return $report;
        }

        $members = $this->marker->memberIds();
        $purchases = $this->marker->purchaseIds();
        DB::transaction(function () use ($members, $purchases): void {
            DB::table('wallet_ledger_entries')->whereIn('member_id', $members)->whereIn('package_purchase_id', $purchases)->delete();
            DB::table('package_level_accruals')->whereIn('package_purchase_id', $purchases)->delete();
            DB::table('package_roi_accruals')->whereIn('package_purchase_id', $purchases)->delete();
            PaymentDecision::query()->whereIn('package_purchase_id', $purchases)->delete();
            Invoice::query()->whereIn('package_purchase_id', $purchases)->delete();
            PackagePurchase::query()->whereIn('id', $purchases)->delete();
            PackageIncomeRun::query()->where('message', 'like', 'LOCAL TEST%')->delete();
            Member::query()->whereIn('member_id', $members)->delete();
        });

        return $this->inspect();
    }

    /**
     * @param  list<string>  $members
     * @param  list<int>  $purchases
     */
    private function dependencyError(array $members, array $purchases): ?string
    {
        if ($members === []) {
            return null;
        }

        $realSponsor = Member::query()
            ->whereNotIn('member_id', $members)
            ->whereIn('sponsor_id', $members)
            ->count();
        if ($realSponsor > 0) {
            return 'A member outside the local test set uses a local test sponsor.';
        }

        if ($purchases === []) {
            return null;
        }

        $paidOutside = PackageLevelAccrual::query()
            ->whereIn('package_purchase_id', $purchases)
            ->whereNotIn('member_id', $members)
            ->count()
            + PackageRoiAccrual::query()
                ->whereIn('package_purchase_id', $purchases)
                ->whereNotIn('member_id', $members)
                ->count();
        if ($paidOutside > 0) {
            return 'A local test purchase credited a member outside the local test set.';
        }

        $outsidePurchase = PackageLevelAccrual::query()
            ->whereIn('member_id', $members)
            ->whereNotIn('package_purchase_id', $purchases)
            ->count()
            + PackageRoiAccrual::query()
                ->whereIn('member_id', $members)
                ->whereNotIn('package_purchase_id', $purchases)
                ->count();
        if ($outsidePurchase > 0) {
            return 'A local test member has income from a purchase outside the local test set.';
        }

        $foreignLedger = DB::table('wallet_ledger_entries')
            ->whereIn('member_id', $members)
            ->where(function ($query) use ($purchases): void {
                $query->whereNull('package_purchase_id')->orWhereNotIn('package_purchase_id', $purchases);
            })
            ->count();
        if ($foreignLedger > 0) {
            return 'A local test member has a ledger row outside the local test purchases.';
        }

        return null;
    }
}
