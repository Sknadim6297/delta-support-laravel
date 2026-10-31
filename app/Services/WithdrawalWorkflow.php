<?php

namespace App\Services;

use App\Models\ActivationWalletTransaction;
use App\Models\Member;
use App\Models\PaymentSetting;
use App\Models\User;
use App\Models\WalletLedgerEntry;
use App\Models\WithdrawalRequest;
use App\Models\WithdrawalSetting;
use App\Models\WithdrawalStatusEvent;
use App\Support\WalletLedgerDirection;
use App\Support\WithdrawalStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class WithdrawalWorkflow
{
    public function __construct(
        private readonly FinancialControls $controls,
        private readonly WalletLedgerPoster $ledger,
        private readonly LocalTestMarker $marker,
        private readonly MemberSecurityPin $pins,
        private readonly WithdrawalBalances $balances,
    ) {
    }

    public function requestsOpen(?WithdrawalSetting $settings = null): bool
    {
        $settings ??= WithdrawalSetting::current();

        return (bool) $settings->submissions_enabled
            || $this->controls->enabled('withdrawal_requests_enabled');
    }

    public function reviewsOpen(?WithdrawalSetting $settings = null): bool
    {
        $settings ??= WithdrawalSetting::current();

        return (bool) $settings->submissions_enabled
            || $this->controls->enabled('withdrawal_approval_enabled');
    }

    public function availableBalance(Member $member, string $wallet): string
    {
        $available = $this->available($member, $wallet);

        return bccomp($available, '0', 4) === -1 ? '0.0000' : $available;
    }

    /**
     * @param  array{wallet: string, amount: string, network: string, address: string, idempotency_key: string, security_pin?: string}  $input
     */
    public function submit(Member $member, array $input): WithdrawalRequest
    {
        $settings = WithdrawalSetting::current();
        if (! $this->requestsOpen($settings)) {
            throw new RuntimeException('Withdrawal requests are disabled. No funds were reserved.');
        }
        if (strtolower((string) $member->status) !== 'active') {
            throw new RuntimeException('Only an active member can request a withdrawal. No funds were reserved.');
        }
        if ($this->marker->isEmail($member->email)) {
            throw new RuntimeException('Demo members cannot request a withdrawal. No funds were reserved.');
        }

        $wallet = (string) ($input['wallet'] ?? '');
        $amount = $this->amount((string) ($input['amount'] ?? ''));
        $network = trim((string) ($input['network'] ?? ''));
        $address = trim((string) ($input['address'] ?? ''));
        $key = trim((string) ($input['idempotency_key'] ?? ''));
        $this->assertDestination($settings, $wallet, $network, $address, $amount);
        if (! Str::isUuid($key)) {
            throw ValidationException::withMessages(['idempotency_key' => 'Submit the request once with a valid idempotency key.']);
        }

        $pin = (string) ($input['security_pin'] ?? '');
        $this->pins->authorizeTransaction($member, $pin);

        return DB::transaction(function () use ($member, $settings, $wallet, $amount, $network, $address, $key, $pin): WithdrawalRequest {
            $locked = Member::query()->where('member_id', $member->member_id)->lockForUpdate()->firstOrFail();
            $this->pins->assertCurrent($locked, $pin);
            $existing = WithdrawalRequest::query()->where('idempotency_key', $key)->lockForUpdate()->first();
            if ($existing) {
                if ($existing->member_id !== $locked->member_id) {
                    throw new RuntimeException('This idempotency key belongs to another member. No funds were reserved.');
                }
                $same = $existing->source_wallet === $wallet
                    && $existing->network === $network
                    && $existing->receiving_address === $address
                    && bccomp((string) $existing->amount, $amount, 4) === 0;
                if (! $same) {
                    throw ValidationException::withMessages([
                        'idempotency_key' => 'This request was already submitted with different details. No further funds were reserved.',
                    ]);
                }

                return $existing;
            }

            $available = $this->available($locked, $wallet);
            if (bccomp($available, $amount, 4) < 0) {
                throw ValidationException::withMessages(['amount' => 'The available balance is '.$available.' USDT.']);
            }

            $charge = $this->charge($settings, $amount);
            $net = bcsub($amount, $charge, 4);
            if (bccomp($net, '0', 4) !== 1) {
                throw ValidationException::withMessages(['amount' => 'The amount does not cover the withdrawal charge.']);
            }

            $request = WithdrawalRequest::query()->create([
                'reference' => $this->reference(),
                'idempotency_key' => $key,
                'member_id' => $locked->member_id,
                'source_wallet' => $wallet,
                'amount' => $amount,
                'charge_amount' => $charge,
                'net_amount' => $net,
                'denomination' => 'USDT',
                'network' => $network,
                'receiving_address' => $address,
                'status' => WithdrawalStatus::PENDING_REVIEW,
            ]);
            $balance = $this->balance($locked, $wallet);
            $this->ledger->append(
                WalletLedgerDirection::HOLD,
                $locked->member_id,
                $wallet,
                $amount,
                'HOLD-'.$request->reference,
                (string) Str::uuid(),
                'Withdrawal reservation. The wallet balance was not reduced.',
                null,
                $balance,
                $balance,
                $request->id
            );
            $this->event($request, null, WithdrawalStatus::PENDING_REVIEW, 'member', null);

            return $request;
        });
    }

    public function approve(WithdrawalRequest $request, User $admin): WithdrawalRequest
    {
        if (! $this->reviewsOpen()) {
            throw new RuntimeException('Withdrawal approval is disabled. No payout was sent.');
        }

        return DB::transaction(function () use ($request, $admin): WithdrawalRequest {
            $locked = WithdrawalRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === WithdrawalStatus::APPROVED) {
                return $locked;
            }

            return $this->transitionLocked($locked, WithdrawalStatus::APPROVED, 'admin:'.$admin->id, null);
        });
    }

    public function reject(WithdrawalRequest $request, User $admin, string $reason): WithdrawalRequest
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'Enter the reason for the rejection.']);
        }

        return DB::transaction(function () use ($request, $admin, $reason): WithdrawalRequest {
            $locked = WithdrawalRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === WithdrawalStatus::REJECTED) {
                return $locked;
            }
            if ($locked->status !== WithdrawalStatus::PENDING_REVIEW) {
                throw new RuntimeException('Only a pending request can be rejected. No balance was changed.');
            }
            $member = Member::query()->where('member_id', $locked->member_id)->lockForUpdate()->firstOrFail();
            $balance = $this->balance($member, $locked->source_wallet);
            $locked->forceFill([
                'status' => WithdrawalStatus::REJECTED,
                'rejection_reason' => $reason,
            ])->save();
            $releaseReference = 'RELEASE-'.$locked->reference;
            if (! WalletLedgerEntry::query()->where('reference', $releaseReference)->exists()) {
                $this->ledger->append(
                    WalletLedgerDirection::RELEASE,
                    $member->member_id,
                    $locked->source_wallet,
                    (string) $locked->amount,
                    $releaseReference,
                    (string) Str::uuid(),
                    'Withdrawal reservation released. The stored wallet balance was not changed.',
                    null,
                    $balance,
                    $balance,
                    $locked->id
                );
            }
            $this->event($locked, WithdrawalStatus::PENDING_REVIEW, WithdrawalStatus::REJECTED, 'admin:'.$admin->id, $reason);

            return $locked->fresh();
        });
    }

    /**
     * Record a USDT payout an administrator already sent. This does not broadcast a transfer.
     *
     * @param  array{network: string, txid: string, amount: string, paid_at: string}  $payout
     */
    public function recordManualPayout(WithdrawalRequest $request, User $admin, array $payout): WithdrawalRequest
    {
        $network = trim((string) ($payout['network'] ?? ''));
        $txid = trim((string) ($payout['txid'] ?? ''));
        $amount = $this->amount((string) ($payout['amount'] ?? ''));
        $paidAt = $this->paidAt((string) ($payout['paid_at'] ?? ''));

        return DB::transaction(function () use ($request, $admin, $network, $txid, $amount, $paidAt): WithdrawalRequest {
            $locked = WithdrawalRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === WithdrawalStatus::COMPLETED) {
                return $this->replaySettlement($locked, $network, $txid, $amount);
            }
            if ($locked->status !== WithdrawalStatus::APPROVED) {
                throw new RuntimeException('Only an approved withdrawal can be marked paid. No balance was changed.');
            }

            $this->assertPayoutMatches($locked, $network, $txid, $amount, $paidAt);
            $member = Member::query()->where('member_id', $locked->member_id)->lockForUpdate()->firstOrFail();
            $column = $this->balances->column($locked->source_wallet);
            $opening = $this->balances->stored($member, $locked->source_wallet);
            $gross = bcadd((string) $locked->amount, '0', 4);
            if (bccomp($opening, $gross, 4) < 0) {
                throw new RuntimeException('The stored balance does not cover the reserved amount. No balance was changed.');
            }

            $availableBefore = $this->balances->available($member, $locked->source_wallet);
            $closing = bcsub($opening, $gross, 4);
            $member->{$column} = $closing;
            $member->save();

            $locked->forceFill([
                'status' => WithdrawalStatus::COMPLETED,
                'payout_network' => $locked->network,
                'payout_txid' => $txid,
                'paid_at' => $paidAt,
            ])->save();

            $reference = 'SETTLE-'.$locked->reference;
            if (! WalletLedgerEntry::query()->where('reference', $reference)->exists()) {
                $this->ledger->append(
                    WalletLedgerDirection::DEBIT,
                    $member->member_id,
                    $locked->source_wallet,
                    $gross,
                    $reference,
                    (string) Str::uuid(),
                    'Manual USDT payout settled. Gross '.$gross.' reserved; net '.$locked->net_amount.' paid. The reservation was closed in the same step.',
                    null,
                    $opening,
                    $closing,
                    $locked->id,
                    'admin:'.$admin->id
                );
            }
            $this->recordActivationDebit($member, $locked, $gross, $reference);
            $this->event($locked, WithdrawalStatus::APPROVED, WithdrawalStatus::COMPLETED, 'admin:'.$admin->id, $txid);

            $member->refresh();
            $availableAfter = $this->balances->available($member, $locked->source_wallet);
            if (bccomp($availableBefore, $availableAfter, 4) !== 0) {
                throw new RuntimeException('Settlement changed the available balance. The payout was not stored.');
            }

            return $locked->fresh();
        });
    }

    public function settle(WithdrawalRequest $request): never
    {
        unset($request);
        if (! $this->controls->enabled('external_payouts_enabled')) {
            throw new RuntimeException('External payouts are disabled. No transfer was sent.');
        }

        throw new RuntimeException('No payout provider is configured. No transfer was sent.');
    }

    private function transitionLocked(WithdrawalRequest $locked, string $status, string $actor, ?string $reason): WithdrawalRequest
    {
        if (! WithdrawalStatus::canTransition($locked->status, $status)) {
            throw new RuntimeException('This request cannot move from '.$locked->status.' to '.$status.'. No balance was changed.');
        }
        $from = $locked->status;
        $locked->forceFill(['status' => $status])->save();
        $this->event($locked, $from, $status, $actor, $reason);

        return $locked->fresh();
    }

    private function available(Member $member, string $wallet): string
    {
        return $this->balances->available($member, $wallet);
    }

    private function replaySettlement(WithdrawalRequest $locked, string $network, string $txid, string $amount): WithdrawalRequest
    {
        $same = strcasecmp($network, (string) $locked->payout_network) === 0
            && (string) $locked->payout_txid === $txid
            && bccomp($amount, (string) $locked->net_amount, 4) === 0;
        if (! $same) {
            throw new RuntimeException('This withdrawal is already paid. No further balance change was made.');
        }

        return $locked;
    }

    private function assertPayoutMatches(WithdrawalRequest $locked, string $network, string $txid, string $amount, Carbon $paidAt): void
    {
        $errors = [];
        if ($network === '' || strcasecmp($network, (string) $locked->network) !== 0 || PaymentSetting::containsSecret($network)) {
            $errors['network'] = 'The payout network must match the approved withdrawal network.';
        }
        if (preg_match('/^(0x)?[A-Za-z0-9]{16,120}$/', $txid) !== 1 || PaymentSetting::containsSecret($txid) || PaymentSetting::isPlaceholder($txid)) {
            $errors['txid'] = 'Enter the verified payout transaction hash.';
        }
        $charge = bcadd((string) ($locked->charge_amount ?? '0'), '0', 4);
        $expectedNet = bcsub(bcadd((string) $locked->amount, '0', 4), $charge, 4);
        if ($locked->net_amount === null || bccomp((string) $locked->net_amount, $expectedNet, 4) !== 0 || bccomp($expectedNet, '0', 4) !== 1) {
            throw new RuntimeException('The stored charge does not match the net payout. No balance was changed.');
        }
        if (bccomp($amount, $expectedNet, 4) !== 0) {
            $errors['amount'] = 'The payout amount must equal the net withdrawal of '.$expectedNet.' USDT.';
        }
        if ($locked->created_at !== null && $paidAt->lt($locked->created_at)) {
            $errors['paid_at'] = 'The completion time cannot be earlier than the request.';
        }
        if ($txid !== '' && WithdrawalRequest::query()
            ->where('payout_network', $locked->network)
            ->where('payout_txid', $txid)
            ->whereKeyNot($locked->id)
            ->exists()) {
            $errors['txid'] = 'This payout transaction hash is already recorded.';
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function paidAt(string $value): Carbon
    {
        $value = trim($value);
        if ($value === '') {
            throw ValidationException::withMessages(['paid_at' => 'Enter the completion time.']);
        }
        try {
            $paidAt = Carbon::parse($value);
        } catch (\Throwable) {
            throw ValidationException::withMessages(['paid_at' => 'Enter a valid completion time.']);
        }
        if ($paidAt->greaterThan(now()->addMinutes(5))) {
            throw ValidationException::withMessages(['paid_at' => 'The completion time cannot be in the future.']);
        }

        return $paidAt;
    }

    private function recordActivationDebit(Member $member, WithdrawalRequest $locked, string $gross, string $reference): void
    {
        if ($locked->source_wallet !== 'activation' || ! Schema::hasTable('activation_wallet_transactions')) {
            return;
        }
        if (ActivationWalletTransaction::query()->where('reference', $reference)->exists()) {
            return;
        }

        ActivationWalletTransaction::query()->create([
            'member_id' => $member->member_id,
            'member_name' => (string) $member->member_name,
            'amount' => $gross,
            'type' => 'debit',
            'remarks' => 'Manual USDT withdrawal payout '.$locked->reference,
            'reference' => $reference,
        ]);
    }

    private function balance(Member $member, string $wallet): string
    {
        $column = match ($wallet) {
            'roi' => 'roi_wallet_amount',
            'working' => 'working_wallet_amount',
            'activation' => 'activation_wallet_amount',
            default => throw ValidationException::withMessages(['wallet' => 'Choose an eligible wallet.']),
        };

        return bcadd((string) ($member->{$column} ?? '0'), '0', 4);
    }

    private function assertDestination(WithdrawalSetting $settings, string $wallet, string $network, string $address, string $amount): void
    {
        $errors = [];
        if (! in_array($wallet, $settings->configuredWallets(), true)) {
            $errors['wallet'] = 'Choose a wallet that withdrawal settings allow.';
        }
        if ($network === '' || ! in_array($network, $settings->configuredNetworks(), true) || PaymentSetting::containsSecret($network)) {
            $errors['network'] = 'Choose a configured network.';
        }
        if ($address === '' || strlen($address) > 128 || PaymentSetting::containsSecret($address) || preg_match('/[\x00-\x1F\x7F<>]/', $address) === 1) {
            $errors['address'] = 'Enter a public receiving address.';
        }
        if ($settings->minimum_amount !== null && bccomp($amount, (string) $settings->minimum_amount, 4) < 0) {
            $errors['amount'] = 'The minimum withdrawal is '.(string) $settings->minimum_amount.' USDT.';
        }
        if ($settings->maximum_amount !== null && bccomp($amount, (string) $settings->maximum_amount, 4) > 0) {
            $errors['amount'] = 'The maximum withdrawal is '.(string) $settings->maximum_amount.' USDT.';
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function charge(WithdrawalSetting $settings, string $amount): string
    {
        return match ($settings->charge_type) {
            'flat' => bcadd((string) $settings->charge_value, '0', 4),
            'percent' => bcdiv(bcmul($amount, (string) $settings->charge_value, 4), '100', 4),
            default => '0.0000',
        };
    }

    private function amount(string $amount): string
    {
        if (! preg_match('/^\d+(\.\d{1,4})?$/', $amount)) {
            throw ValidationException::withMessages(['amount' => 'Enter a positive USDT amount with at most four decimal places.']);
        }
        $normalized = bcadd($amount, '0', 4);
        if (bccomp($normalized, '0', 4) !== 1) {
            throw ValidationException::withMessages(['amount' => 'Enter a positive USDT amount with at most four decimal places.']);
        }

        return $normalized;
    }

    private function reference(): string
    {
        do {
            $reference = 'WD-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));
        } while (WithdrawalRequest::query()->where('reference', $reference)->exists());

        return $reference;
    }

    private function event(WithdrawalRequest $request, ?string $from, string $to, string $actor, ?string $reason): void
    {
        WithdrawalStatusEvent::query()->create([
            'withdrawal_request_id' => $request->id,
            'from_status' => $from,
            'to_status' => $to,
            'actor' => $actor,
            'reason' => $reason,
        ]);
    }
}
