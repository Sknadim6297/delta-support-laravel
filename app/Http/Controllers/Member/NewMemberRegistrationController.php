<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Services\MemberRegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class NewMemberRegistrationController extends Controller
{
    public function create(Request $request): View
    {
        $member = $this->currentMember($request);

        return view('member.create-member.new-member-registration', [
            'member' => $member,
            'generated_member_id' => app(MemberRegistrationService::class)->previewMemberId(),
            'default_sponsor_id' => $member->member_id,
            'default_sponsor_name' => $member->member_name,
        ]);
    }

    public function checkSponsor(Request $request): JsonResponse
    {
        $currentMember = $this->currentMember($request);
        $sponsorId = strtoupper(trim((string) $request->query('sponsor_id', '')));

        if ($sponsorId === '') {
            return response()->json([
                'exists' => false,
                'message' => 'Sponsor ID is required.',
            ]);
        }

        $sponsor = $sponsorId === $currentMember->member_id ? $currentMember : null;

        return response()->json([
            'exists' => $sponsor !== null,
            'sponsor_name' => $sponsor?->member_name,
            'message' => $sponsor ? 'Sponsor ID is valid.' : 'Unauthorized sponsor ID.',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'sponsor_id' => strtoupper(trim((string) $request->input('sponsor_id'))),
            'member_name' => trim((string) $request->input('member_name')),
            'email' => trim((string) $request->input('email')),
            'mobile_no' => trim((string) $request->input('mobile_no')),
        ]);

        $currentMember = $this->currentMember($request);

        $validator = Validator::make($request->all(), [
            'member_name' => ['required', 'string', 'min:3', 'regex:/^[A-Za-z ]+$/'],
            'sponsor_id' => ['required', 'string', Rule::in([$currentMember->member_id])],
            'email' => ['required', 'email', 'unique:members,email'],
            'mobile_no' => ['required', 'string', 'regex:/^[0-9+\-\s]+$/', 'min:10', 'max:15'],
        ], [
            'sponsor_id.in' => 'Unauthorized sponsor ID.',
        ]);

        if ($validator->fails()) {
            return redirect()->route('member.registration')->withErrors($validator)->withInput();
        }

        $result = app(MemberRegistrationService::class)->register($validator->validated(), [
            'locked_sponsor' => $currentMember,
        ]);

        return redirect()
            ->route('member.registration')
            ->with('success', 'Member registered successfully. Member ID: ' . $result['member']->member_id . '. No password was set. The member can create one from Forgot Password.');
    }

    private function currentMember(Request $request): Member
    {
        return Member::where('member_id', $request->session()->get('member_context_id'))->firstOrFail();
    }
}
