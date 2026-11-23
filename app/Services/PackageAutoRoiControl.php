<?php

namespace App\Services;

use App\Models\Package;
use App\Models\PackagePlanAudit;
use App\Models\User;
use App\Models\WalletLedgerEntry;
use Illuminate\Support\Facades\DB;
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
     * @return array{
     *   code: string,
     *   label: string,
     *   enabled: bool,
     *   locked: bool,
     *   can_enable: bool,
     *   message: string|null
     * }
     */
    public function effectiveStatus(Package $package): array
    {
        if ($package->package_type === Package::TYPE_TOUR || ! $package->reservesFutureIncome()) {
            return [
                'code' => self::STATUS_DISABLED,
                'label' => 'OFF',
                'enabled' => false,
                'locked' => true,
                'can_enable' => false,
                'message' => 'Tour-only packages cannot enable Auto ROI.',
            ];
        }

        $enabled = (bool) $package->auto_roi_enabled;
        if (! $enabled) {
            $blockers = $this->enableBlockers($package);

            return [
                'code' => self::STATUS_OFF,
                'label' => 'OFF',
                'enabled' => false,
                'locked' => false,
                'can_enable' => $blockers === [],
                'message' => $blockers[0] ?? null,
            ];
        }

        $blockers = $this->enableBlockers($package);
        if ($blockers !== []) {
            return [
                'code' => self::STATUS_ON_INCOMPLETE,
                'label' => 'ON — Configuration Incomplete',
                'enabled' => true,
                'locked' => false,
                'can_enable' => false,
                'message' => $blockers[0],
            ];
        }

        if ($this->globalRoiBlocked()) {
            // Package toggle is ON. Global posting stays separately off by design (not a package error).
            return [
                'code' => self::STATUS_ON_BLOCKED_GLOBAL,
                'label' => 'ON',
                'enabled' => true,
                'locked' => false,
                'can_enable' => true,
                'message' => 'Auto ROI is ON for this package. Daily wallet posting is still off under global financial controls — that is normal and separate from this switch. No wallets are credited by turning Auto ROI ON.',
            ];
        }

        return [
            'code' => self::STATUS_ON_SCHEDULED,
            'label' => 'ON — Scheduled',
            'enabled' => true,
            'locked' => false,
            'can_enable' => true,
            'message' => 'Eligible accruals run on the next authorized scheduled cycle. No immediate wallet credit.',
        ];
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
            $blockers[] = 'Set Daily ROI and duration, then save the package before turning Auto ROI ON.';
        }
        $definition = $this->policy->definition((string) $package->code);
        if ($definition === null || ! isset($definition['roi_daily'], $definition['roi_days'])) {
            $blockers[] = 'Save a valid Daily ROI and duration first. Auto ROI stays blocked until income terms are configured.';
        }

        return $blockers;
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

    public function allowsScheduledRoi(?Package $package): bool
    {
        if ($package === null) {
            return false;
        }
        if ($package->package_type === Package::TYPE_TOUR) {
            return false;
        }
        if (! (bool) $package->auto_roi_enabled) {
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

            if ((bool) $locked->auto_roi_enabled === $enabled) {
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
