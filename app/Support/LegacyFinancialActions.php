<?php

namespace App\Support;

final class LegacyFinancialActions
{
    public const DISABLED_MESSAGE = 'This financial action is disabled.';

    public static function enabled(): bool
    {
        return (bool) config('deltasupport.legacy_financial_actions');
    }
}
