<?php

namespace App\Services;

use App\Models\LevelCommissionTransaction;
use App\Models\RoiTransaction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class FinancialHistoryQuery
{
    public function roi(?string $memberId, string $search = '', int $perPage = 10): LengthAwarePaginator
    {
        $query = RoiTransaction::query()
            ->select(['id', 'reference', 'member_id', 'member_name', 'income_amount', 'roi_date', 'status', 'created_at']);

        if ($memberId !== null) {
            $query->where('member_id', $memberId);
        }

        $this->applySearch($query, $search, ['reference', 'member_id', 'member_name']);

        return $query->orderByDesc('id')->paginate($perPage)->withQueryString();
    }

    public function levelIncome(?string $memberId, string $search = '', int $perPage = 10): LengthAwarePaginator
    {
        $query = LevelCommissionTransaction::query()
            ->select(['id', 'reference', 'member_id', 'member_name', 'from_member_id', 'level', 'income_amount', 'business_date', 'created_at']);

        if ($memberId !== null) {
            $query->where('member_id', $memberId);
        }

        $this->applySearch($query, $search, ['reference', 'member_id', 'member_name']);

        return $query->orderByDesc('id')->paginate($perPage)->withQueryString();
    }

    /**
     * @param  list<string>  $columns
     */
    private function applySearch(Builder $query, string $search, array $columns): void
    {
        $search = trim($search);
        if ($search === '') {
            return;
        }

        $like = '%'.addcslashes($search, '\\%_').'%';
        $query->where(function (Builder $inner) use ($columns, $like): void {
            foreach ($columns as $column) {
                $inner->orWhere($column, 'like', $like);
            }
        });
    }
}
