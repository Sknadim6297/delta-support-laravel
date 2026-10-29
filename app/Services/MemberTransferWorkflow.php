<?php

namespace App\Services;

use App\Models\Member;
use App\Models\MemberTransfer;
use App\Models\MemberTransferSetting;
use App\Models\User;
use App\Support\MoneyDisplay;
use App\Support\WalletLedgerDirection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class MemberTransferWorkflow
{
    public function __construct(
        private readonly WorkingWalletFunds $funds,
        private readonly WalletLedgerPoster $ledger,
        private readonly MemberSecurityPin $pins,
        private readonly LocalTestMarker $marker,
    ) {
    }

    public function settings(): MemberTransferSetting
    {
        return MemberTransferSetting::current();
    }

    public function enabled(): bool
    {
        return $this->settings()->transfersEnabled();
    }

    /**
     * @return array{receiver: Member, amount: string, fee: string, total: string, available: string}
     */
    public function quote(Member $sender, string $receiverId, string $amount): array
    {
        $this->assertEnabled();
        $amount = $this->amount($amount);
        $receiver = $this->receiver($receiverId);
        $this->assertParty($sender, 'sender');
        $this->assertParty($receiver, 'receiver');
        $this->assertDistinct($sender, $receiver);
        $settings = $this->settings();
        $this->assertLimits($settings, $amount);
        $fee = bcadd((string) $settings->fee_amount, '0', 4);
        $total = bcadd($amount, $fee, 4);
        $available = $this->funds->available($sender->fresh() ?? $sender);
        if (bccomp($available, $total, 4) === -1) {
            throw ValidationException::withMessages([
                'amount' => 'The available Working Wallet balance is '.$available.' USDT.',
            ]);
        }
        $this->assertDaily($sender, $total, (string) $settings->daily_limit);

        return [
            'receiver' => $receiver,
            'amount' => $amount,
            'fee' => $fee,
            'total' => $total,
            'available' => $available,
        ];
    }

    /**
     * @param  array{receiver_member_id: string, amount: string, idempotency_key: string, security_pin: string}  $input
     */
    public function execute(Member $sender, array $input): MemberTransfer
    {
        $this->assertEnabled();
        $amount = $this->amount((string) ($input['amount'] ?? ''));
        $receiverId = trim((string) ($input['receiver_member_id'] ?? ''));
        $key = trim((string) ($input['idempotency_key'] ?? ''));
        $pin = (string) ($input['security_pin'] ?? '');
        if (! Str::isUuid($key)) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'Submit the transfer once with a valid idempotency key.',
            ]);
        }

        $receiver = $this->receiver($receiverId);
        $this->assertDistinct($sender, $receiver);
        $this->assertParty($sender, 'sender');
        $this->assertParty($receiver, 'receiver');
        $this->pins->authorizeTransaction($sender, $pin);

        try {
            return DB::transaction(function () use ($sender, $receiver, $amount, $key, $pin): MemberTransfer {
                [$lockedSender, $lockedReceiver] = $this->lockPair($sender, $receiver);
                $this->assertEnabled();
                $this->pins->assertCurrent($lockedSender, $pin);

                $existing = MemberTransfer::query()->where('idempotency_key', $key)->first();
                if ($existing) {
                    if ($existing->sender_member_id !== $lockedSender->member_id) {
                        throw new RuntimeException('This idempotency key belongs to another member. No funds were moved.');
                    }

                    return $existing;
                }

                $this->assertParty($lockedSender, 'sender');
                $this->assertParty($lockedReceiver, 'receiver');
                $settings = MemberTransferSetting::current();
                $this->assertLimits($settings, $amount);
                $fee = bcadd((string) $settings->fee_amount, '0', 4);
                $total = bcadd($amount, $fee, 4);

                return $this->post($lockedSender, $lockedReceiver, $amount, $fee, $total, $key, (string) $settings->daily_limit);
            });
        } catch (QueryException $exception) {
            $existing = MemberTransfer::query()->where('idempotency_key', $key)->first();
            if ($existing && $existing->sender_member_id === $sender->member_id && $this->isUniqueConflict($exception)) {
                return $existing;
            }

            throw $exception;
        }
    }

    public function reverse(MemberTransfer $transfer, User $admin, string $reason): MemberTransfer
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'Enter the reason for the reversal.']);
        }

        return DB::transaction(function () use ($transfer, $admin, $reason): MemberTransfer {
            $locked = MemberTransfer::query()->whereKey($transfer->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== MemberTransfer::COMPLETED) {
                throw new RuntimeException('Only a completed transfer can be reversed. No funds were moved.');
            }

            $sender = Member::query()->where('member_id', $locked->sender_member_id)->firstOrFail();
            $receiver = Member::query()->where('member_id', $locked->receiver_member_id)->firstOrFail();
            [$lockedSender, $lockedReceiver] = $this->lockPair($sender, $receiver);
            $amount = bcadd((string) $locked->amount, '0', 4);
            $fee = bcadd((string) $locked->fee_amount, '0', 4);
            $total = bcadd($amount, $fee, 4);
            $receiverAvailable = $this->funds->available($lockedReceiver);
            if (bccomp($receiverAvailable, $amount, 4) === -1) {
                throw ValidationException::withMessages([
                    'reason' => 'The receiver no longer has '.$amount.' USDT available in the Working Wallet. No funds were moved.',
                ]);
            }

            $senderOpening = $this->funds->balance($lockedSender);
            $receiverOpening = $this->funds->balance($lockedReceiver);
            $senderClosing = bcadd($senderOpening, $total, 4);
            $receiverClosing = bcsub($receiverOpening, $amount, 4);
            if (bccomp($receiverClosing, '0', 4) === -1) {
                throw ValidationException::withMessages([
                    'reason' => 'The reversal would take the Working Wallet below 0.0000. No funds were moved.',
                ]);
            }

            $lockedSender->working_wallet_amount = $senderClosing;
            $lockedReceiver->working_wallet_amount = $receiverClosing;
            $lockedSender->save();
            $lockedReceiver->save();

            $credit = $this->ledger->append(
                WalletLedgerDirection::CREDIT,
                $lockedSender->member_id,
                MemberTransfer::WALLET,
                $total,
                $locked->transfer_reference.'-REV-CR',
                (string) Str::uuid(),
                'Reversal of member transfer '.$locked->transfer_reference,
                null,
                $senderOpening,
                $senderClosing,
                null,
                'admin:'.$admin->id
            );
            $this->ledger->append(
                WalletLedgerDirection::DEBIT,
                $lockedReceiver->member_id,
                MemberTransfer::WALLET,
                $amount,
                $locked->transfer_reference.'-REV-DR',
                (string) Str::uuid(),
                'Reversal of member transfer '.$locked->transfer_reference,
                null,
                $receiverOpening,
                $receiverClosing,
                null,
                'admin:'.$admin->id,
                $credit->id
            );

            $locked->forceFill([
                'status' => MemberTransfer::REVERSED,
                'reversed_at' => now(),
                'reversal_actor' => 'admin:'.$admin->id,
                'reversal_reason' => $reason,
            ])->save();

            return $locked->fresh();
        });
    }

    private function post(Member $sender, Member $receiver, string $amount, string $fee, string $total, string $key, string $dailyLimit): MemberTransfer
    {
        $senderAvailable = $this->funds->available($sender);
        if (bccomp($senderAvailable, $total, 4) === -1) {
            throw ValidationException::withMessages([
                'amount' => 'The available Working Wallet balance is '.$senderAvailable.' USDT.',
            ]);
        }
        $this->assertDaily($sender, $total, $dailyLimit);

        $senderOpening = $this->funds->balance($sender);
        $receiverOpening = $this->funds->balance($receiver);
        $senderClosing = bcsub($senderOpening, $total, 4);
        $receiverClosing = bcadd($receiverOpening, $amount, 4);
        if (bccomp($senderClosing, '0', 4) === -1) {
            throw ValidationException::withMessages([
                'amount' => 'The transfer would take the Working Wallet below 0.0000.',
            ]);
        }

        $sender->working_wallet_amount = $senderClosing;
        $receiver->working_wallet_amount = $receiverClosing;
        $sender->save();
        $receiver->save();

        $reference = $this->reference();
        $businessDate = now()->timezone((string) config('deltasupport.report_timezone', 'Asia/Kolkata'))->toDateString();
        $transfer = MemberTransfer::query()->create([
            'transfer_reference' => $reference,
            'sender_member_id' => $sender->member_id,
            'receiver_member_id' => $receiver->member_id,
            'source_wallet' => MemberTransfer::WALLET,
            'destination_wallet' => MemberTransfer::WALLET,
            'amount' => $amount,
            'fee_amount' => $fee,
            'currency' => MoneyDisplay::USDT,
            'status' => MemberTransfer::COMPLETED,
            'idempotency_key' => $key,
            'business_date' => $businessDate,
            'completed_at' => now(),
        ]);

        $debit = $this->ledger->append(
            WalletLedgerDirection::DEBIT,
            $sender->member_id,
            MemberTransfer::WALLET,
            $total,
            $reference.'-DR',
            (string) Str::uuid(),
            'Member transfer to '.$receiver->member_id,
            null,
            $senderOpening,
            $senderClosing,
            null,
            'member:'.$sender->member_id
        );
        $this->ledger->append(
            WalletLedgerDirection::CREDIT,
            $receiver->member_id,
            MemberTransfer::WALLET,
            $amount,
            $reference.'-CR',
            (string) Str::uuid(),
            'Member transfer from '.$sender->member_id,
            null,
            $receiverOpening,
            $receiverClosing,
            null,
            'member:'.$sender->member_id,
            $debit->id
        );

        return $transfer;
    }

    /**
     * @return array{0: Member, 1: Member}
     */
    private function lockPair(Member $sender, Member $receiver): array
    {
        $firstId = min((int) $sender->id, (int) $receiver->id);
        $secondId = max((int) $sender->id, (int) $receiver->id);
        $first = Member::query()->whereKey($firstId)->lockForUpdate()->firstOrFail();
        $second = Member::query()->whereKey($secondId)->lockForUpdate()->firstOrFail();
        $lockedSender = $first->member_id === $sender->member_id ? $first : $second;
        $lockedReceiver = $first->member_id === $receiver->member_id ? $first : $second;

        return [$lockedSender, $lockedReceiver];
    }

    private function assertEnabled(): void
    {
        if (! $this->enabled()) {
            throw new RuntimeException('Member transfers are disabled. No funds were moved.');
        }
    }

    private function receiver(string $memberId): Member
    {
        $memberId = trim($memberId);
        $receiver = Member::query()->where('member_id', $memberId)->first();
        if (! $receiver) {
            throw ValidationException::withMessages([
                'receiver_member_id' => 'No active member matches that ID.',
            ]);
        }

        return $receiver;
    }

    private function assertParty(Member $member, string $role): void
    {
        if ($this->marker->isEmail($member->email)) {
            $message = $role === 'sender'
                ? 'This account cannot send a transfer.'
                : 'This member cannot receive a transfer.';
            throw ValidationException::withMessages([
                $role === 'sender' ? 'transfer' : 'receiver_member_id' => $message,
            ]);
        }
        if ($member->status !== 'active') {
            $message = $role === 'sender'
                ? 'Your account must be active before you can transfer funds.'
                : 'The receiver is not an active member.';
            throw ValidationException::withMessages([
                $role === 'sender' ? 'transfer' : 'receiver_member_id' => $message,
            ]);
        }
    }

    private function assertDistinct(Member $sender, Member $receiver): void
    {
        if ($sender->member_id === $receiver->member_id) {
            throw ValidationException::withMessages([
                'receiver_member_id' => 'You cannot transfer to your own member ID.',
            ]);
        }
    }

    private function assertLimits(MemberTransferSetting $settings, string $amount): void
    {
        $minimum = bcadd((string) $settings->minimum_amount, '0', 4);
        $maximum = bcadd((string) $settings->maximum_amount, '0', 4);
        if (bccomp($amount, $minimum, 4) === -1) {
            throw ValidationException::withMessages([
                'amount' => 'The minimum transfer is '.$minimum.' USDT.',
            ]);
        }
        if (bccomp($amount, $maximum, 4) === 1) {
            throw ValidationException::withMessages([
                'amount' => 'The maximum transfer is '.$maximum.' USDT.',
            ]);
        }
    }

    private function assertDaily(Member $sender, string $total, string $dailyLimit): void
    {
        $limit = bcadd($dailyLimit, '0', 4);
        $date = now()->timezone((string) config('deltasupport.report_timezone', 'Asia/Kolkata'))->toDateString();
        $used = '0.0000';
        foreach (MemberTransfer::query()
            ->where('sender_member_id', $sender->member_id)
            ->whereDate('business_date', $date)
            ->whereIn('status', [MemberTransfer::COMPLETED, MemberTransfer::REVERSED])
            ->get(['amount', 'fee_amount']) as $row) {
            $used = bcadd($used, bcadd((string) $row->amount, (string) $row->fee_amount, 4), 4);
        }
        if (bccomp(bcadd($used, $total, 4), $limit, 4) === 1) {
            throw ValidationException::withMessages([
                'amount' => 'The daily transfer limit is '.$limit.' USDT.',
            ]);
        }
    }

    private function amount(string $amount): string
    {
        $amount = trim($amount);
        if (! preg_match('/^\d+(\.\d{1,4})?$/', $amount)) {
            throw ValidationException::withMessages([
                'amount' => 'Enter a positive USDT amount with at most four decimal places.',
            ]);
        }
        $normalized = bcadd($amount, '0', 4);
        if (bccomp($normalized, '0', 4) !== 1) {
            throw ValidationException::withMessages([
                'amount' => 'Enter a positive USDT amount with at most four decimal places.',
            ]);
        }

        return $normalized;
    }

    private function reference(): string
    {
        do {
            $reference = 'MT-'.now()->timezone((string) config('deltasupport.report_timezone', 'Asia/Kolkata'))->format('Ymd').'-'.strtoupper(Str::random(6));
        } while (MemberTransfer::query()->where('transfer_reference', $reference)->exists());

        return $reference;
    }

    private function isUniqueConflict(QueryException $exception): bool
    {
        $state = (string) $exception->getCode();

        return $state === '23000' || str_contains(strtolower($exception->getMessage()), 'unique');
    }
}
