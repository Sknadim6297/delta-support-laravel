<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Member;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MemberDirectoryController extends Controller
{
    public function index(Request $request): View
    {
        $members = Member::query()
            ->when($request->filled('q'), function ($query) use ($request): void {
                $term = '%' . trim((string) $request->query('q')) . '%';
                $query->where(function ($inner) use ($term): void {
                    $inner->where('member_id', 'like', $term)
                        ->orWhere('member_name', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhere('mobile_no', 'like', $term)
                        ->orWhere('sponsor_id', 'like', $term)
                        ->orWhere('sponsor_name', 'like', $term);
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->query('status')))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.members.directory', compact('members'));
    }

    public function updateStatus(Request $request, string $memberId): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:active,inactive'],
        ]);

        $member = Member::query()->where('member_id', $memberId)->firstOrFail();
        $member->update(['status' => $data['status']]);

        return back()->with('success', $member->member_id . ' is now ' . $data['status'] . '. Wallet balances were not changed.');
    }
}
