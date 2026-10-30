<?php

namespace App\Services;

use App\Models\FinancialControl;
use Illuminate\Support\Facades\Schema;

class FinancialControls
{
    /**
     * @var list<string>
     */
    public const FLAGS = [
        'roi_calculation_enabled',
        'roi_wallet_crediting_enabled',
        'level_calculation_enabled',
        'level_wallet_crediting_enabled',
        'rewards_evaluation_enabled',
        'withdrawal_requests_enabled',
        'withdrawal_approval_enabled',
        'external_payouts_enabled',
        'daily_financial_cycle_enabled',
    ];

    /**
     * These follow the single automation arm. Withdrawals and external payouts do not.
     *
     * @var list<string>
     */
    public const AUTOMATIC = [
        'roi_calculation_enabled',
        'roi_wallet_crediting_enabled',
        'level_calculation_enabled',
        'level_wallet_crediting_enabled',
        'rewards_evaluation_enabled',
        'daily_financial_cycle_enabled',
    ];

    public function enabled(string $flag): bool
    {
        if (! in_array($flag, self::FLAGS, true)) {
            return false;
        }

        if (in_array($flag, self::AUTOMATIC, true) && $this->emergencyStopped()) {
            return false;
        }

        if (config('deltasupport.controls.'.$flag) === true) {
            return true;
        }

        if (in_array($flag, self::AUTOMATIC, true) && $this->automationArmed()) {
            return true;
        }
        if (! Schema::hasTable('financial_controls')) {
            return false;
        }

        return (bool) FinancialControl::current()->{$flag};
    }

    /**
     * @param  array<string, bool>  $flags
     */
    public function update(array $flags): FinancialControl
    {
        $row = FinancialControl::current();
        foreach (self::FLAGS as $flag) {
            $row->{$flag} = (bool) ($flags[$flag] ?? false);
        }
        $row->save();

        return $row->fresh();
    }

    public function automationArmed(): bool
    {
        if (config('deltasupport.controls.financial_automation_armed') === true) {
            return true;
        }
        if (! Schema::hasTable('financial_controls') || ! Schema::hasColumn('financial_controls', 'financial_automation_armed')) {
            return false;
        }

        return (bool) FinancialControl::current()->financial_automation_armed;
    }

    public function emergencyStopped(): bool
    {
        if (config('deltasupport.controls.financial_emergency_stop') === true) {
            return true;
        }
        if (! Schema::hasTable('financial_controls') || ! Schema::hasColumn('financial_controls', 'financial_emergency_stop')) {
            return false;
        }

        return (bool) FinancialControl::current()->financial_emergency_stop;
    }

    public function automaticPostingAllowed(): bool
    {
        return $this->automationArmed() && ! $this->emergencyStopped();
    }
};
