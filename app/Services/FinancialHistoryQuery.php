<?php

namespace App\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class FinancialHistoryQuery
{
    public function roi(?string $memberId, string $search = '', int $perPage = 10): LengthAwarePaginator
    {
        $legacy = DB::table('roi_transactions')->select([
            'id', 'reference', 'member_id', 'member_name', 'income_amount', 'roi_date', 'status', 'created_at',
        ])->selectRaw("'Not linked to a package' as package_label");
        $package = DB::table('package_roi_accruals')->select([
            'id', 'reference', 'member_id', 'member_name', 'amount as income_amount', 'business_date as roi_date', 'status', 'created_at', 'package_name as package_label',
        ]);
        $this->restrict($legacy, $memberId, $search);
        $this->restrict($package, $memberId, $search);

        return DB::query()->fromSub($legacy->unionAll($package), 'history')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function levelIncome(?string $memberId, string $search = '', int $perPage = 10): LengthAwarePaginator
    {
        $legacy = DB::table('level_commission_transactions')->select([
            'id', 'reference', 'member_id', 'member_name', 'from_member_id', 'level', 'income_amount', 'business_date', 'created_at',
        ])->selectRaw("'Not linked to a package' as package_label");
        $package = DB::table('package_level_accruals')->select([
            'id', 'reference', 'member_id', 'member_name', 'from_member_id', 'level', 'amount as income_amount', 'business_date', 'created_at', 'package_name as package_label',
        ]);
        $this->restrict($legacy, $memberId, $search);
        $this->restrict($package, $memberId, $search);

        return DB::query()->fromSub($legacy->unionAll($package), 'history')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    private function restrict(Builder $query, ?string $memberId, string $search): void
    {
        if ($memberId !== null) {
            $query->where('member_id', $memberId);
        }
        $search = trim($search);
        if ($search === '') {
            return;
        }
        $like = '%'.addcslashes($search, '\\%_').'%';
        $query->where(function (Builder $inner) use ($like): void {
            $inner->where('reference', 'like', $like)
                ->orWhere('member_id', 'like', $like)
                ->orWhere('member_name', 'like', $like);
        });
    }
}
