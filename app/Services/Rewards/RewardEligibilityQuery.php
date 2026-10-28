<?php

namespace App\Services\Rewards;

use App\Models\RewardQualification;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class RewardEligibilityQuery
{
    public function filtered(Request $request): Builder
    {
        $query = RewardQualification::query()->with(['campaign', 'assignment.delivery']);
        $status = (string) $request->query('status', '');

        if ($status === 'all') {
            // Every stored qualification.
        } elseif (in_array($status, RewardQualification::STATUSES, true)) {
            $query->where('status', $status);
        } else {
            $query->whereIn('status', [RewardQualification::QUALIFIED, RewardQualification::SELECTED]);
        }

        $campaignId = $request->query('campaign_id');
        if (is_numeric($campaignId)) {
            $query->where('campaign_id', (int) $campaignId);
        }

        $memberId = trim((string) $request->query('member_id', ''));
        if ($memberId !== '') {
            $query->where('member_id', $memberId);
        }

        $term = trim((string) $request->query('q', ''));
        if ($term !== '') {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $query->where(function (Builder $inner) use ($like): void {
                $inner->where('member_id', 'like', $like)
                    ->orWhere('member_name', 'like', $like);
            });
        }

        $from = $this->bound((string) $request->query('from', ''), true);
        if ($from !== null) {
            $query->where('qualified_at', '>=', $from);
        }

        $to = $this->bound((string) $request->query('to', ''), false);
        if ($to !== null) {
            $query->where('qualified_at', '<=', $to);
        }

        return $query->orderByDesc('qualified_at')->orderByDesc('id');
    }

    private function bound(string $date, bool $start): ?string
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return null;
        }

        $instant = CarbonImmutable::parse($date, 'Asia/Kolkata');

        return ($start ? $instant->startOfDay() : $instant->endOfDay())->utc()->toDateTimeString();
    }
}
