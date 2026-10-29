<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\MemberTransfer;
use App\Services\LocalTestMarker;
use App\Services\MemberSecurityPin;
use App\Services\MemberTransferWorkflow;
use App\Services\WorkingWalletFunds;
use App\Support\SecurityPinInput;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

class MemberTransferController extends Controller
{
    public const PREVIEW = 'member_transfer_preview';

    public const PIN_RETURN = 'member_transfer_pin_return';

    public function create(Request $request, MemberTransferWorkflow $workflow, WorkingWalletFunds $funds, MemberSecurityPin $pins, LocalTestMarker $marker): View|RedirectResponse
    {
        $member = $this->member($request);
        if (! $pins->hasPin($member)) {
            $request->session()->put(self::PIN_RETURN, true);

            return redirect()
                ->route('member.security-pin')
                ->with('status', 'Set a security PIN before you can transfer Working Wallet funds.');
        }

        $request->session()->forget(self::PIN_RETURN);

        return view('member.transfers.create', [
            'member' => $member,
            'enabled' => $workflow->enabled(),
            'available' => $funds->available($member),
            'settings' => $workflow->settings(),
            'canSend' => $member->status === 'active' && ! $marker->isEmail($member->email),
        ]);
    }

    public function receiver(Request $request, MemberTransferWorkflow $workflow): JsonResponse
    {
        $sender = $this->member($request);
        if (! $workflow->enabled()) {
            return response()->json(['message' => 'Member transfers are disabled.'], 403);
        }

        $memberId = trim((string) $request->query('member_id', ''));
        $receiver = Member::query()->where('member_id', $memberId)->first();
        if (! $receiver || $receiver->status !== 'active' || app(LocalTestMarker::class)->isEmail($receiver->email) || $receiver->member_id === $sender->member_id) {
            return response()->json(['message' => 'No active member matches that ID.'], 404);
        }

        return response()->json([
            'member_id' => $receiver->member_id,
            'member_name' => $receiver->member_name,
        ]);
    }

    public function review(Request $request, MemberTransferWorkflow $workflow, MemberSecurityPin $pins): RedirectResponse
    {
        $member = $this->member($request);
        if (! $pins->hasPin($member)) {
            $request->session()->put(self::PIN_RETURN, true);

            return redirect()
                ->route('member.security-pin')
                ->with('status', 'Set a security PIN before you can transfer Working Wallet funds.');
        }

        $validated = $request->validate([
            'receiver_member_id' => ['required', 'string', 'max:32'],
            'amount' => ['required', 'string', 'max:24'],
        ]);

        try {
            $quote = $workflow->quote($member, $validated['receiver_member_id'], $validated['amount']);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['transfer' => $exception->getMessage()])->withInput();
        }

        $request->session()->put(self::PREVIEW, [
            'receiver_member_id' => $quote['receiver']->member_id,
            'receiver_name' => $quote['receiver']->member_name,
            'amount' => $quote['amount'],
            'fee' => $quote['fee'],
            'total' => $quote['total'],
            'idempotency_key' => (string) Str::uuid(),
        ]);

        return redirect()->route('member.transfers.confirm');
    }

    public function confirm(Request $request): View|RedirectResponse
    {
        $member = $this->member($request);
        $preview = $this->preview($request);
        if ($preview === null) {
            return redirect()->route('member.transfers.create');
        }

        return view('member.transfers.confirm', [
            'member' => $member,
            'preview' => $preview,
        ]);
    }

    public function store(Request $request, MemberTransferWorkflow $workflow): RedirectResponse
    {
        $member = $this->member($request);
        $preview = $this->preview($request);
        if ($preview === null) {
            return redirect()->route('member.transfers.create');
        }

        SecurityPinInput::merge($request, 'security_pin');
        $validated = $request->validate([
            'receiver_member_id' => ['required', 'string', 'max:32'],
            'amount' => ['required', 'string', 'max:24'],
            'idempotency_key' => ['required', 'uuid'],
            'security_pin' => ['required', 'digits:6'],
        ]);
        if ($validated['receiver_member_id'] !== $preview['receiver_member_id']
            || bccomp($this->postedAmount($validated['amount']), $preview['amount'], 4) !== 0
            || $validated['idempotency_key'] !== $preview['idempotency_key']) {
            throw ValidationException::withMessages([
                'transfer' => 'Review the transfer again before confirming it.',
            ]);
        }

        try {
            $transfer = $workflow->execute($member, [
                'receiver_member_id' => $preview['receiver_member_id'],
                'amount' => $preview['amount'],
                'idempotency_key' => $preview['idempotency_key'],
                'security_pin' => $validated['security_pin'],
            ]);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['transfer' => $exception->getMessage()]);
        }

        $request->session()->forget(self::PREVIEW);

        return redirect()
            ->route('member.transfers.show', $transfer)
            ->with('success', 'Transfer '.$transfer->transfer_reference.' is complete.');
    }

    public function history(Request $request): View
    {
        $member = $this->member($request);
        $transfers = MemberTransfer::query()
            ->where(function ($query) use ($member): void {
                $query->where('sender_member_id', $member->member_id)
                    ->orWhere('receiver_member_id', $member->member_id);
            })
            ->latest('id')
            ->paginate(15);

        return view('member.transfers.history', [
            'member' => $member,
            'transfers' => $transfers,
        ]);
    }

    public function show(Request $request, MemberTransfer $memberTransfer): View
    {
        $member = $this->member($request);
        abort_unless($memberTransfer->involves($member->member_id), 404);

        return view('member.transfers.show', [
            'member' => $member,
            'transfer' => $memberTransfer,
        ]);
    }

    private function member(Request $request): Member
    {
        return Member::query()
            ->where('member_id', $request->session()->get('member_context_id'))
            ->firstOrFail();
    }

    /**
     * @return array{receiver_member_id: string, receiver_name: string, amount: string, fee: string, total: string, idempotency_key: string}|null
     */
    private function preview(Request $request): ?array
    {
        $preview = $request->session()->get(self::PREVIEW);
        if (! is_array($preview) || ! isset($preview['receiver_member_id'], $preview['amount'], $preview['fee'], $preview['total'], $preview['idempotency_key'], $preview['receiver_name'])) {
            return null;
        }

        return [
            'receiver_member_id' => (string) $preview['receiver_member_id'],
            'receiver_name' => (string) $preview['receiver_name'],
            'amount' => (string) $preview['amount'],
            'fee' => (string) $preview['fee'],
            'total' => (string) $preview['total'],
            'idempotency_key' => (string) $preview['idempotency_key'],
        ];
    }

    private function postedAmount(string $amount): string
    {
        if (! preg_match('/^\d+(\.\d{1,4})?$/', trim($amount))) {
            return '-1';
        }

        return bcadd(trim($amount), '0', 4);
    }
}
