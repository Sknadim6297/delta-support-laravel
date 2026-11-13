<?php

namespace App\Services;

use App\Models\Member;
use App\Models\Package;
use App\Models\PackagePurchase;
use App\Models\PackageRoiAccrual;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PackageIncomeEligibilityAudit
{
    public function __construct(
        private readonly PackageIncomePolicy $policy,
        private readonly PackageIncomeActivationGate $activation,
    ) {
    }

    /**
     * @return array{
     *   date: string,
     *   timezone: string,
     *   wait_days: int,
     *   configured_packages: list<string>,
     *   package_rows: int,
     *   purchase_total: int,
     *   purchase_approved: int,
     *   purchase_pending: int,
     *   purchase_other: int,
     *   investment_total: int,
     *   eligible: list<array<string, string>>,
     *   ineligible: list<array<string, string>>,
     *   summary: array<string, int>
     * }
     */
    public function forDate(string $businessDate): array
    {
        $timezone = (string) config('deltasupport.package_income.timezone', 'Asia/Kolkata');
        $date = CarbonImmutable::parse($businessDate, $timezone)->startOfDay();
        $waitDays = (int) config('deltasupport.package_income.wait_days', 10);
        $configured = array_keys((array) config('deltasupport.package_income.packages', []));
        $packages = Package::query()->get()->keyBy('id');
        $eligible = [];
        $ineligible = [];
        $reasons = [];

        $purchases = PackagePurchase::query()->orderBy('id')->get();
        foreach ($purchases as $purchase) {
            $row = $this->explainPurchase($purchase, $packages->get($purchase->package_id), $date, $waitDays);
            if ($row['eligible'] === 'yes') {
                $eligible[] = $row;
            } else {
                $ineligible[] = $row;
            }
            $reason = $row['reason'];
            $reasons[$reason] = ($reasons[$reason] ?? 0) + 1;
        }

        return [
            'date' => $date->toDateString(),
            'timezone' => $timezone,
            'wait_days' => $waitDays,
            'configured_packages' => array_values($configured),
            'package_rows' => Package::query()->count(),
            'purchase_total' => $purchases->count(),
            'purchase_approved' => $purchases->filter(fn (PackagePurchase $purchase): bool => $purchase->status === PackagePurchase::APPROVED && $purchase->reviewed_at !== null)->count(),
            'purchase_pending' => $purchases->where('status', 'pending')->count(),
            'purchase_other' => $purchases->reject(fn (PackagePurchase $purchase): bool => ($purchase->status === PackagePurchase::APPROVED && $purchase->reviewed_at !== null) || $purchase->status === 'pending')->count(),
            'investment_total' => Schema::hasTable('investments') ? (int) DB::table('investments')->count() : 0,
            'eligible' => $eligible,
            'ineligible' => $ineligible,
            'summary' => $reasons,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function explainPurchase(PackagePurchase $purchase, ?Package $package, CarbonImmutable $date, int $waitDays): array
    {
        $email = (string) (Member::query()->where('member_id', $purchase->member_id)->value('email') ?? '');
        $base = [
            'purchase_id' => (string) $purchase->id,
            'member_id' => (string) $purchase->member_id,
            'package_code' => (string) $purchase->package_code,
            'status' => (string) $purchase->status,
            'reviewed_at' => $purchase->reviewed_at?->timezone((string) config('deltasupport.package_income.timezone', 'Asia/Kolkata'))->toDateString() ?? '',
            'payment_reference' => (string) ($purchase->payment_reference ?? ''),
            'amount' => '',
            'first_accrual' => '',
            'eligible' => 'no',
            'reason' => '',
        ];

        if ($purchase->status !== PackagePurchase::APPROVED || $purchase->reviewed_at === null) {
            $base['reason'] = 'Purchase is not approved with a reviewed_at date.';

            return $base;
        }

        if (str_starts_with((string) $purchase->payment_reference, LocalTestMarker::PAYMENT_PREFIX)
            || str_ends_with(strtolower($email), LocalTestMarker::EMAIL_DOMAIN)) {
            $base['reason'] = 'Demonstration is local-test / demonstration data excluded from financial:daily.';

            return $base;
        }

        if ($package === null) {
            $base['reason'] = 'Linked package row is missing.';

            return $base;
        }

        if (! $package->roi_eligible) {
            $base['reason'] = 'Package roi_eligible is false.';

            return $base;
        }

        $definition = $this->policy->definition($purchase->package_code);
        if ($definition === null) {
            $base['reason'] = 'Package code has no income definition in config (production income uses DST-ROI-002 and DST-TOUR-003).';

            return $base;
        }

        $base['amount'] = $definition['roi_daily'];
        $originalFirst = $this->activation->originalFirstAccrualDate($purchase, $this->policy);
        $base['first_accrual'] = $originalFirst?->toDateString() ?? '';
        if ($originalFirst === null) {
            $base['reason'] = 'First accrual date could not be calculated.';

            return $base;
        }

        if ($this->activation->requiresActivation($purchase->package_code)
            && $this->activation->activationBusinessDate($purchase->package_code) === null) {
            $base['reason'] = 'Package income activation date is missing. Forward-only posting is closed.';

            return $base;
        }

        $allowedFirst = $this->activation->firstAllowedIncomeDate($purchase, $this->policy);
        $base['first_allowed_income'] = $allowedFirst?->toDateString() ?? '';
        if ($allowedFirst === null) {
            $base['reason'] = 'First allowed income date could not be calculated.';

            return $base;
        }

        if ($date->lessThan($allowedFirst)) {
            if ($date->lessThan($originalFirst)) {
                $base['reason'] = 'Still inside the '.$waitDays.'-day wait. First ROI date is '.$originalFirst->toDateString().'.';
            } else {
                $activation = $this->activation->activationBusinessDate($purchase->package_code);
                $base['reason'] = 'Before package income activation. First allowed income date is '.$allowedFirst->toDateString()
                    .' (activation '.($activation?->toDateString() ?? 'none').').';
            }

            return $base;
        }

        $last = $this->policy->lastAccrualDate($originalFirst, $definition['roi_days']);
        if ($last === null || $date->greaterThan($last)) {
            $base['reason'] = 'Outside ROI window. Last ROI date is '.($last?->toDateString() ?? 'none').'.';

            return $base;
        }

        $existing = PackageRoiAccrual::query()
            ->where('package_purchase_id', $purchase->id)
            ->whereDate('business_date', $date->toDateString())
            ->first();
        if ($existing?->status === 'accrued') {
            $base['reason'] = 'ROI already accrued for this business date.';

            return $base;
        }

        $count = PackageRoiAccrual::query()->where('package_purchase_id', $purchase->id)->count();
        if ($count >= $definition['roi_days'] && $existing === null) {
            $base['reason'] = 'Purchase already reached its ROI duration count.';

            return $base;
        }

        $base['eligible'] = 'yes';
        $base['reason'] = $existing?->status === 'calculated'
            ? 'Eligible. Accrual exists as calculated and still needs wallet credit when posting is armed.'
            : 'Eligible for ROI on this business date.';

        return $base;
    }
}
