<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Member;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class GenealogyController extends Controller
{
    public function treeView(Request $request): View
    {
        $rootMember = $this->currentMember($request);
        $selectedMemberId = strtoupper(trim((string) $request->query('member_id', '')));

        if ($selectedMemberId !== '') {
            $authorizedIds = Member::authorizedMemberIds($rootMember->member_id);
            $selectedMember = Member::query()
                ->whereIn('member_id', $authorizedIds)
                ->where('member_id', $selectedMemberId)
                ->first();

            if ($selectedMember === null) {
                $selectedMember = $rootMember;
            }
        } else {
            $selectedMember = $rootMember;
        }

        return view('member.genealogy.tree-view', [
            'member' => $rootMember,
            'tree' => [$this->buildTreeNode($selectedMember)],
            'selectedMemberId' => $selectedMember->member_id,
            'memberNotFound' => false,
        ]);
    }

    public function treeChildren(Request $request): JsonResponse
    {
        $root = $this->currentMember($request);
        $parentId = strtoupper(trim((string) $request->query('member_id', '')));
        abort_unless($this->isInDownline($root, $parentId), 404);

        $children = Member::query()
            ->where('sponsor_id', $parentId)
            ->orderBy('member_name')
            ->get(['member_id', 'member_name', 'status']);
        $counts = $children->isEmpty()
            ? collect()
            : Member::query()
                ->select('sponsor_id', DB::raw('count(*) as aggregate'))
                ->whereIn('sponsor_id', $children->pluck('member_id'))
                ->groupBy('sponsor_id')
                ->pluck('aggregate', 'sponsor_id');

        $html = $children->map(function (Member $child) use ($counts, $root) {
            return view('shared.genealogy-node', [
                'node' => [
                    'id' => $child->member_id,
                    'name' => $child->member_name,
                    'status' => $child->status,
                    'children' => [],
                    'child_count' => (int) ($counts[$child->member_id] ?? 0),
                ],
                'ownProfileUrl' => route('member.profile'),
                'profileFor' => $root->member_id,
            ])->render();
        })->implode('');

        return response()->json(['html' => $html]);
    }

    public function levelView(Request $request): View
    {
        return view('member.genealogy.level-view', [
            'member' => $this->currentMember($request),
        ]);
    }

    public function levelMembers(Request $request): JsonResponse
    {
        $member = $this->currentMember($request);
        $levelFilter = trim((string) $request->query('level', ''));

        if ($levelFilter !== '' && (! ctype_digit($levelFilter) || (int) $levelFilter < 1 || (int) $levelFilter > 32)) {
            return response()->json(['message' => 'Level must be a number between 1 and 32.'], 422);
        }

        $results = [];
        $visited = [$member->member_id => true];
        $currentLevelMembers = [$member];
        $level = 1;

        while ($currentLevelMembers !== [] && $level <= 32) {
            $nextLevelMembers = [];

            foreach ($currentLevelMembers as $parent) {
                $children = Member::where('sponsor_id', $parent->member_id)
                    ->orderBy('member_name')
                    ->get();

                foreach ($children as $child) {
                    if (isset($visited[$child->member_id])) {
                        continue;
                    }

                    $visited[$child->member_id] = true;
                    $nextLevelMembers[] = $child;

                    if ($levelFilter === '' || (int) $levelFilter === $level) {
                        $results[] = [
                            'level' => $level,
                            'member_id' => $child->member_id,
                            'member_name' => $child->member_name,
                            'sponsor_id' => $child->sponsor_id,
                            'status' => $child->status,
                        ];
                    }
                }
            }

            $currentLevelMembers = $nextLevelMembers;
            $level++;
        }

        return response()->json(['data' => $results]);
    }

    private function currentMember(Request $request): Member
    {
        return Member::where('member_id', $request->session()->get('member_context_id'))->firstOrFail();
    }

    private function isInDownline(Member $root, string $memberId): bool
    {
        if ($memberId === '' || $memberId === $root->member_id) {
            return $memberId === $root->member_id;
        }

        $current = $memberId;
        $seen = [];

        for ($depth = 0; $depth < 64; $depth++) {
            if (isset($seen[$current])) {
                return false;
            }

            $seen[$current] = true;
            $row = Member::query()->where('member_id', $current)->first(['member_id', 'sponsor_id']);
            if ($row === null) {
                return false;
            }

            if ($row->sponsor_id === $root->member_id) {
                return true;
            }

            $current = (string) $row->sponsor_id;
        }

        return false;
    }

    private function buildTreeNode(Member $member, array &$visited = [], int $depth = 0): array
    {
        if (isset($visited[$member->member_id])) {
            return [
                'id' => $member->member_id,
                'name' => $member->member_name,
                'status' => $member->status,
                'children' => [],
                'child_count' => 0,
            ];
        }

        $visited[$member->member_id] = true;
        $children = Member::query()
            ->where('sponsor_id', $member->member_id)
            ->orderBy('member_name')
            ->get(['member_id', 'member_name', 'status']);

        if ($depth >= 1) {
            return [
                'id' => $member->member_id,
                'name' => $member->member_name,
                'status' => $member->status,
                'children' => [],
                'child_count' => $children->count(),
            ];
        }

        return [
            'id' => $member->member_id,
            'name' => $member->member_name,
            'status' => $member->status,
            'child_count' => $children->count(),
            'children' => $children->map(fn (Member $child) => $this->buildTreeNode($child, $visited, $depth + 1))->values()->all(),
        ];
    }
}
