<?php

namespace App\Services;

use App\Models\Package;
use App\Models\PackagePlanAudit;
use App\Models\User;
use App\Models\WalletLedgerEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Package-wise Auto ROI generation control.
 * Does not credit wallets. Does not bypass global financial safety controls.
 * Does not change Level Income or Associate Commission posting.
 */
class PackageAutoRoiControl
{
    public const STATUS_OFF = 'off';

    public const STATUS_ON_SCHEDULED = 'on_scheduled';

    public const STATUS_ON_BLOCKED_GLOBAL = 'on_blocked_global';

    public const STATUS_ON_INCOMPLETE = 'on_incomplete';

    public const STATUS_DISABLED = 'disabled';

    public function __construct(
        private readonly PackageIncomePolicy $policy,
        private readonly FinancialControls $controls,
    ) {
    }

    /**
     * Single source of truth for Admin list, edit form, show page, and toggle API.
     *
     * @return array{
     *   code: string,
     *   label: string,
     *   enabled: bool,
     *   locked: bool,
     *   can_enable: bool,
     *   message: string|null,
     *   toggle: bool
     * }
     */
    public function effectiveStatus(Package $package): array
    {
        if ($package->package_type === Package::TYPE_TOUR || ! $package->reservesFutureIncome()) {
            return $this->statusPayload(
                self::STATUS_DISABLED,
                'OFF',
                false,
                true,
                false,
                'Tour-only packages cannot enable Auto ROI.',
                false
            );
        }

        // Always trust the persisted toggle — never a stale in-memory attribute.
        $toggle = $this->persistedToggleEnabled($package);
        $blockers = $this->enableBlockers($package);

        if (! $toggle) {
            return $this->statusPayload(
                self::STATUS_OFF,
                'OFF',
                false,
                false,
                $blockers === [],
                $blockers === []
                    ? 'Auto ROI is OFF. Existing member entitlements are unchanged.'
                    : 'Auto ROI is OFF. '.$blockers[0],
                false
            );
        }

        // Toggle is ON: label must never say OFF.
        if ($blockers !== []) {
            return $this->statusPayload(
                self::STATUS_ON_INCOMPLETE,
                'ON — Configuration Incomplete',
                true,
                false,
                false,
                $blockers[0],
                true
            );
        }

        if ($this->globalRoiBlocked()) {
            return $this->statusPayload(
                self::STATUS_ON_BLOCKED_GLOBAL,
                'ON — Globally Blocked',
                true,
                false,
                true,
                'Auto ROI is ON for this package. Daily wallet posting is still off under global financial controls — that is normal and separate from this switch. No wallets are credited by turning Auto ROI ON.',
                true
            );
        }

        return $this->statusPayload(
            self::STATUS_ON_SCHEDULED,
            'ON — Scheduled',
            true,
            false,
            true,
            'Eligible accruals run on the next authorized scheduled cycle. No immediate wallet credit.',
            true
        );
    }

    /**
     * @return list<string>
     */
    public function enableBlockers(Package $package): array
    {
        $blockers = [];
        if ($package->package_type === Package::TYPE_TOUR || ! $package->reservesFutureIncome()) {
            $blockers[] = 'Tour-only packages cannot enable Auto ROI.';

            return $blockers;
        }
        if (! $package->roi_eligible) {
            $blockers[] = 'Set Daily ROI and duration, then save the package before Auto ROI can schedule accruals.';
        }
        $definition = $this->policy->definition((string) $package->code);
        if ($definition === null || ! isset($definition['roi_daily'], $definition['roi_days'])) {
            $blockers[] = 'Authorize a published Daily ROI plan for future purchases (or keep a valid legacy config). Saving ROI alone does not authorize accrual. Existing purchases are not rewritten.';
        }

        return $blockers;
    }

    /**
     * Persisted packages.auto_roi_enabled. Fresh DB read when the model exists.
     */
    public function persistedToggleEnabled(Package $package): bool
    {
        if (! $package->exists || $package->getKey() === null) {
            return (bool) $package->auto_roi_enabled;
        }
        if (! Schema::hasColumn('packages', 'auto_roi_enabled')) {
            return false;
        }

        $raw = Package::query()->whereKey($package->getKey())->value('auto_roi_enabled');

        return filter_var($raw, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @return array{
     *   code: string,
     *   label: string,
     *   enabled: bool,
     *   locked: bool,
     *   can_enable: bool,
     *   message: string|null,
     *   toggle: bool
     * }
     */
    private function statusPayload(
        string $code,
        string $label,
        bool $enabled,
        bool $locked,
        bool $canEnable,
        ?string $message,
        bool $toggle,
    ): array {
        return [
            'code' => $code,
            'label' => $label,
            'enabled' => $enabled,
            'locked' => $locked,
            'can_enable' => $canEnable,
            'message' => $message,
            'toggle' => $toggle,
        ];
    }

    public function globalRoiBlocked(): bool
    {
        if ($this->controls->emergencyStopped()) {
            return true;
        }
        if (! $this->controls->enabled('daily_financial_cycle_enabled')) {
            return true;
        }
        if (! $this->controls->enabled('roi_calculation_enabled')) {
            return true;
        }

        return false;
    }

    /**
     * Idempotent production-safe defaults for packages.auto_roi_enabled.
     * Preserves currently earning legacy GOLD/PREMIUM (and other package_income ROI codes).
     * Forces SILVER/VIP/DIAMOND/DELTA SHARE OFF. Never rewrites wallets or snapshots.
     *
     * @return array{enabled_codes: list<string>, disabled_codes: list<string>}
     */
    public function applySafeProductionDefaults(): array
    {
        if (! Schema::hasTable('packages') || ! Schema::hasColumn('packages', 'auto_roi_enabled')) {
            return ['enabled_codes' => [], 'disabled_codes' => []];
        }

        $forceOffCodes = [
            'DST-TOUR-001',
            'DST-TOUR-004',
            'DST-TOUR-005',
            'DST-RY-006',
        ];

        DB::table('packages')->update(['auto_roi_enabled' => false]);

        $legacyRoiCodes = [];
        foreach (array_keys((array) config('deltasupport.package_income.packages', [])) as $code) {
            $row = config('deltasupport.package_income.packages.'.$code);
            if (is_array($row) && isset($row['roi_daily'], $row['roi_days'])) {
                $legacyRoiCodes[] = (string) $code;
            }
        }
        if ($legacyRoiCodes !== []) {
            DB::table('packages')
                ->whereIn('code', $legacyRoiCodes)
                ->where('roi_eligible', true)
                ->where('package_type', '!=', Package::TYPE_TOUR)
                ->update(['auto_roi_enabled' => true]);
        }

        if (Schema::hasTable('package_plan_versions')) {
            $authoritativeIds = DB::table('packages as p')
                ->join('package_plan_versions as v', 'v.id', '=', 'p.active_plan_version_id')
                ->where('p.income_source', Package::INCOME_SOURCE_DATABASE)
                ->where('p.roi_eligible', true)
                ->where('p.package_type', '!=', Package::TYPE_TOUR)
                ->where('v.status', 'published')
                ->where('v.accrual_authority', true)
                ->where('v.has_daily_roi', true)
                ->whereNotNull('v.roi_daily')
                ->whereNotNull('v.roi_days')
                ->whereNotIn('p.code', $forceOffCodes)
                ->pluck('p.id')
                ->all();
            if ($authoritativeIds !== []) {
                DB::table('packages')->whereIn('id', $authoritativeIds)->update(['auto_roi_enabled' => true]);
            }
        }

        DB::table('packages')
            ->where(function ($query) use ($forceOffCodes): void {
                $query->whereIn('code', $forceOffCodes)
                    ->orWhere('package_type', Package::TYPE_TOUR)
                    ->orWhere('roi_eligible', false);
            })
            ->update(['auto_roi_enabled' => false]);

        return [
            'enabled_codes' => Package::query()->where('auto_roi_enabled', true)->orderBy('code')->pluck('code')->all(),
            'disabled_codes' => Package::query()->where('auto_roi_enabled', false)->orderBy('code')->pluck('code')->all(),
        ];
    }

    public function allowsScheduledRoi(?Package $package): bool
    {
        if ($package === null) {
            return false;
        }
        if ($package->package_type === Package::TYPE_TOUR) {
            return false;
        }
        if (! $this->persistedToggleEnabled($package)) {
            return false;
        }
        if (! (bool) $package->roi_eligible) {
            return false;
        }

        return $this->enableBlockers($package) === [];
    }

    public function setEnabled(Package $package, bool $enabled, User $admin, bool $confirmed, ?string $ip = null): Package
    {
        $ledgerBefore = WalletLedgerEntry::query()->count();

        return DB::transaction(function () use ($package, $enabled, $admin, $confirmed, $ip, $ledgerBefore): Package {
            $locked = Package::query()->whereKey($package->id)->lockForUpdate()->firstOrFail();
            $before = [
                'auto_roi_enabled' => (bool) $locked->auto_roi_enabled,
                'roi_eligible' => (bool) $locked->roi_eligible,
                'income_source' => $locked->income_source,
            ];

            if ($locked->package_type === Package::TYPE_TOUR || ! $locked->reservesFutureIncome()) {
                throw ValidationException::withMessages([
                    'auto_roi_enabled' => 'Tour-only packages cannot enable Auto ROI.',
                ]);
            }

            if ($this->persistedToggleEnabled($locked) === $enabled) {
                return $locked;
            }

            if ($enabled) {
                if (! $confirmed) {
                    throw ValidationException::withMessages([
                        'confirm_auto_roi' => 'Confirm turning Auto ROI ON. This does not credit wallets immediately.',
                    ]);
                }
                $blockers = $this->enableBlockers($locked);
                if ($blockers !== []) {
                    throw ValidationException::withMessages([
                        'auto_roi_enabled' => $blockers[0],
                    ]);
                }
            }

            $locked->auto_roi_enabled = $enabled;
            $locked->save();

            PackagePlanAudit::query()->create([
                'package_id' => $locked->id,
                'package_plan_version_id' => $locked->active_plan_version_id,
                'user_id' => $admin->id,
                'action' => $enabled ? 'auto_roi_enabled' : 'auto_roi_disabled',
                'before_json' => $before,
                'after_json' => [
                    'auto_roi_enabled' => (bool) $locked->auto_roi_enabled,
                    'roi_eligible' => (bool) $locked->roi_eligible,
                    'income_source' => $locked->income_source,
                    'effective_status' => $this->effectiveStatus($locked->fresh())['code'],
                ],
                'ip_address' => $ip,
            ]);

            abort_unless(WalletLedgerEntry::query()->count() === $ledgerBefore, 500);

            return $locked->fresh();
        });
    }
}
