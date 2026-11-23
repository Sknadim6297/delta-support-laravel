<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ComingSoonController extends Controller
{
    public function show(Request $request, string $feature): View|\Illuminate\Http\RedirectResponse
    {
        if ($feature === 'rank-achievement-report') {
            return redirect()->route('member.reports.rank-achievement');
        }

        return view('member.coming-soon', [
            'member' => Member::where('member_id', \App\Support\MemberContext::id($request))->firstOrFail(),
            'feature' => Str::headline($feature),
        ]);
    }
}
