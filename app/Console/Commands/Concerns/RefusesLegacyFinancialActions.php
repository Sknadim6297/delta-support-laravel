<?php

namespace App\Console\Commands\Concerns;

use App\Support\LegacyFinancialActions;

trait RefusesLegacyFinancialActions
{
    protected function legacyFinancialActionsAreBlocked(): bool
    {
        if (LegacyFinancialActions::enabled()) {
            return false;
        }

        $this->error(LegacyFinancialActions::DISABLED_MESSAGE);

        return true;
    }
}
