<?php

namespace App\Support;

class WalletLedgerDirection
{
    public const CREDIT = 'credit';

    public const DEBIT = 'debit';

    public const TRANSFER = 'transfer';

    public const HOLD = 'hold';

    public const RELEASE = 'release';

    public const REVERSAL = 'reversal';

    public const SETTLEMENT = 'settlement';

    public const ADJUSTMENT = 'adjustment';

    /**
     * @var list<string>
     */
    public const VALUES = [
        self::CREDIT,
        self::DEBIT,
        self::TRANSFER,
        self::HOLD,
        self::RELEASE,
        self::REVERSAL,
        self::SETTLEMENT,
        self::ADJUSTMENT,
    ];
}
