<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Services\MailDeliveryNotice;
use App\Services\MemberPasswordSetupMailer;
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
        $wallet = trim((string) $request->input('wallet_address'));
        $request->merge([
            'sponsor_id' => strtoupper(trim((string) $request->input('sponsor_id'))),
            'member_name' => trim((string) $request->input('member_name')),
            'email' => trim((string) $request->input('email')),
            'mobile_no' => trim((string) $request->input('mobile_no')),
            'pan_card_no' => strtoupper(trim((string) $request->input('pan_card_no'))),
            'wallet_address' => $wallet === '' ? null : $wallet,
        ]);

        $currentMember = $this->currentMember($request);

        $validator = Validator::make($request->all(), [
            'member_name' => Member::nameRules(),
            'sponsor_id' => ['required', 'string', Rule::in([$currentMember->member_id])],
            'email' => ['required', 'email', 'unique:members,email'],
            'mobile_no' => ['required', 'string', 'regex:/^[0-9+\-\s]+$/', 'min:10', 'max:15'],
            'pan_card_no' => ['required', 'string', 'max:10'],
            'wallet_address' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'min:6', 'confirmed'],
        ], [
            'sponsor_id.in' => 'Unauthorized sponsor ID.',
        ]);

        if ($validator->fails()) {
            return redirect()->route('member.registration')->withErrors($validator)->withInput();
        }

        $validated = $validator->validated();
        $result = app(MemberRegistrationService::class)->register($validated, [
            'require_pan' => true,
            'locked_sponsor' => $currentMember,
        ]);
        $member = $result['member'];
        $notice = app(MemberPasswordSetupMailer::class)->send($member);

        return $this->withPasswordSetupNotice(
            redirect()->route('member.registration'),
            $member,
            $notice,
            'Registration successful. The new account is inactive. Member ID: '.$member->member_id.'. Package benefits stay locked until activation is verified.',
        )
            ->with('registration_receipt', [
                'member_id' => $member->member_id,
                'member_name' => $member->member_name,
                'sponsor_id' => $member->sponsor_id,
                'status' => 'Inactive',
            ]);
    }

    public function resendPasswordSetup(Request $request, MemberPasswordSetupMailer $mailer): RedirectResponse
    {
        $currentMember = $this->currentMember($request);
        $memberId = strtoupper(trim((string) $request->input('member_id')));
        $member = Member::query()->where('member_id', $memberId)->where('sponsor_id', $currentMember->member_id)->first();

        if ($member === null) {
            return back()->withErrors(['member_id' => 'That member is not in your direct registrations. No account was created.']);
        }

        return $this->withPasswordSetupNotice(
            back(),
            $member,
            $mailer->send($member),
            'No new member was created.',
        );
    }

    private function withPasswordSetupNotice(RedirectResponse $redirect, Member $member, MailDeliveryNotice $notice, string $success): RedirectResponse
    {
        return $redirect
            ->with('success', $success)
            ->with('mail_notice', $notice->sentence($member->email))
            ->with('mail_accepted', $notice->accepted)
            ->with('password_setup_member_id', $member->member_id);
    }

    private function currentMember(Request $request): Member
    {
        return Member::where('member_id', $request->session()->get('member_context_id'))->firstOrFail();
    }
}
