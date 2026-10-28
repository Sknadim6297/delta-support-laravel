<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\PackagePurchase;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class TeamController extends Controller
{
    public function direct(Request $request): View
    {
        $team = $this->buildTeam($request);
        $directMembers = collect($team['members'])->where('level', 1)->values();

        return view('member.team.index', [
            'member' => $team['member'],
            'directMembers' => $directMembers,
            'wholeTeam' => collect($team['members']),
            'filteredMembers' => $this->paginateMembers($this->filterMembers($directMembers, $request), $request),
            'view' => 'direct',
            'memberId' => trim((string) $request->query('member_id', '')),
            'memberName' => trim((string) $request->query('member_name', '')),
            'status' => $this->statusFilter($request),
        ]);
    }

    public function whole(Request $request): View
    {
        $team = $this->buildTeam($request);
        $wholeTeam = collect($team['members']);

        return view('member.team.index', [
            'member' => $team['member'],
            'directMembers' => $wholeTeam->where('level', 1)->values(),
            'wholeTeam' => $wholeTeam,
            'filteredMembers' => $this->paginateMembers($this->filterMembers($wholeTeam, $request), $request),
            'view' => 'whole',
            'memberId' => trim((string) $request->query('member_id', '')),
            'memberName' => trim((string) $request->query('member_name', '')),
            'status' => $this->statusFilter($request),
        ]);
    }

    private function buildTeam(Request $request): array
    {
        $root = Member::where('member_id', $request->session()->get('member_context_id'))->firstOrFail();
        $pending = [$root->member_id];
        $visited = [$root->member_id => true];
        $team = [];
        $level = 1;

        while ($pending !== [] && $level <= 64) {
            $children = Member::query()
                ->select(['member_id', 'member_name', 'sponsor_id', 'email', 'mobile_no', 'status', 'created_at'])
                ->whereIn('sponsor_id', $pending)
                ->orderBy('member_name')
                ->get();
            $next = [];

            foreach ($children as $child) {
                if (isset($visited[$child->member_id])) {
                    continue;
                }

                $visited[$child->member_id] = true;
                $team[] = [
                    'level' => $level,
                    'member_id' => $child->member_id,
                    'member_name' => $child->member_name,
                    'sponsor_id' => $child->sponsor_id,
                    'email' => $child->email,
                    'mobile_no' => $child->mobile_no,
                    'status' => $child->status,
                    'created_at' => $child->created_at,
                ];
                $next[] = $child->member_id;
            }

            $pending = $next;
            $level++;
        }

        return [
            'member' => $root,
            'members' => $team,
        ];
    }

    private function filterMembers(Collection $members, Request $request): Collection
    {
        $memberId = trim((string) $request->query('member_id', ''));
        $memberName = trim((string) $request->query('member_name', ''));
        $status = $this->statusFilter($request);

        return $members->filter(function (array $member) use ($memberId, $memberName, $status) {
            return ($memberId === '' || str_contains(strtolower($member['member_id']), strtolower($memberId)))
                && ($memberName === '' || str_contains(strtolower($member['member_name']), strtolower($memberName)))
                && ($status === '' || strtolower((string) $member['status']) === $status);
        })->values();
    }

    private function statusFilter(Request $request): string
    {
        $status = strtolower(trim((string) $request->query('status', '')));

        return in_array($status, ['active', 'inactive'], true) ? $status : '';
    }

    private function paginateMembers(Collection $members, Request $request): LengthAwarePaginator
    {
        $perPage = 15;
        $page = LengthAwarePaginator::resolveCurrentPage();
        $slice = $members->slice(($page - 1) * $perPage, $perPage)->values();
        $activated = $slice->isEmpty()
            ? []
            : PackagePurchase::query()
                ->where('status', PackagePurchase::APPROVED)
                ->whereIn('member_id', $slice->pluck('member_id'))
                ->distinct()
                ->pluck('member_id')
                ->all();
        $slice = $slice->map(function (array $member) use ($activated) {
            $member['package_active'] = in_array($member['member_id'], $activated, true);

            return $member;
        });

        return new LengthAwarePaginator($slice, $members->count(), $perPage, $page, [
            'path' => $request->url(),
            'query' => $request->query(),
        ]);
    }
}
