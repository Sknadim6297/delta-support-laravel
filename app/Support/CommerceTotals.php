<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;

class CommerceTotals
{
    /**
     * Sum stored amounts separately for each denomination.
     * USD and USDT are never added together.
     *
     * @return array{USDT: string, USD: string}
     */
    public static function sums(string $model, ?string $status = null, ?string $memberId = null, ?callable $constrain = null): array
    {
        /** @var class-string<Model> $model */
        $query = $model::query()
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->when($memberId !== null, fn ($query) => $query->where('member_id', $memberId));
        if ($constrain !== null) {
            $constrain($query);
        }
        $rows = $query
            ->selectRaw('denomination, SUM(price_usd) as total')
            ->groupBy('denomination')
            ->pluck('total', 'denomination');

        return [
            MoneyDisplay::USDT => MoneyDisplay::amount($rows[MoneyDisplay::USDT] ?? '0'),
            MoneyDisplay::USD => MoneyDisplay::amount($rows[MoneyDisplay::USD] ?? '0'),
        ];
    }
}
