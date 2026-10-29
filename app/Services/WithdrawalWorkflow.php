<?php

namespace App\Services;

use App\Models\Member;
use App\Models\PaymentSetting;
use App\Models\User;
use App\Models\WithdrawalRequest;
use App\Models\WithdrawalSetting;
use App\Models\WithdrawalStatusEvent;
use App\Support\WalletLedgerDirection;
use App\Support\WithdrawalStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class WithdrawalWorkflow
{
    /** @var list<string> */
    private const RESERVED = [
        WithdrawalStatus::PENDING_REVIEW,
        WithdrawalStatus::APPROVED,
        WithdrawalStatus::PROCESSING,
    ];

    public function __construct(
        private readonly FinancialControls $controls,
        private readonly WalletLedgerPoster $ledger,
    ) {
    }

    /**
     * @param  array{wallet: string, amount: string, network: string, address: string, idempotency_key: string}  $input
     */
    public function submit(Member $member, array $input): WithdrawalRequest
    {
        if (! $this->controls->enabled('withdrawal_requests_enabled')) {
            throw new RuntimeException('Withdrawal requests are disabled. No funds were reserved.');
        }

        $settings = WithdrawalSetting::current();
        $wallet = (string) ($input['wallet'] ?? '');
        $amount = $this->amount((string) ($input['amount'] ?? ''));
        $network = trim((string) ($input['network'] ?? ''));
        $address = trim((string) ($input['address'] ?? ''));
        $key = trim((string) ($input['idempotency_key'] ?? ''));
        $this->assertDestination($settings, $wallet, $network, $address, $amount);
        if (! Str::isUuid($key)) {
            throw ValidationException::withMessages(['idempotency_key' => 'Submit the request once with a valid idempotency key.']);
        }

        return DB::transaction(function () use ($member, $settings, $wallet, $amount, $network, $address, $key): WithdrawalRequest {
            $locked = Member::query()->where('member_id', $member->member_id)->lockForUpdate()->firstOrFail();
            $existing = WithdrawalRequest::query()->where('idempotency_key', $key)->first();
            if ($existing) {
                if ($existing->member_id !== $locked->member_id) {
                    throw new RuntimeException('This idempotency key belongs to another member. No funds were reserved.');
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
        if (! $this->controls->enabled('withdrawal_approval_enabled')) {
            throw new RuntimeException('Withdrawal approval is disabled. No payout was sent.');
        }

        return $this->transition($request, WithdrawalStatus::APPROVED, 'admin:'.$admin->id, null);
    }

    public function reject(WithdrawalRequest $request, User $admin, string $reason): WithdrawalRequest
    {
        if (! $this->controls->enabled('withdrawal_approval_enabled')) {
            throw new RuntimeException('Withdrawal approval is disabled. No balance was changed.');
        }
        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'Enter the reason for the rejection.']);
        }

        return DB::transaction(function () use ($request, $admin, $reason): WithdrawalRequest {
            $locked = WithdrawalRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== WithdrawalStatus::PENDING_REVIEW) {
                throw new RuntimeException('Only a pending request can be rejected. No balance was changed.');
            }
            $member = Member::query()->where('member_id', $locked->member_id)->lockForUpdate()->firstOrFail();
            $balance = $this->balance($member, $locked->source_wallet);
            $locked->forceFill([
                'status' => WithdrawalStatus::REJECTED,
                'rejection_reason' => $reason,
            ])->save();
            $this->ledger->append(
                WalletLedgerDirection::RELEASE,
                $member->member_id,
                $locked->source_wallet,
                (string) $locked->amount,
                'RELEASE-'.$locked->reference,
                (string) Str::uuid(),
                'Withdrawal reservation released. The wallet balance was not changed.',
                null,
                $balance,
                $balance,
                $locked->id
            );
            $this->event($locked, WithdrawalStatus::PENDING_REVIEW, WithdrawalStatus::REJECTED, 'admin:'.$admin->id, $reason);

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

    private function transition(WithdrawalRequest $request, string $status, string $actor, ?string $reason): WithdrawalRequest
    {
        return DB::transaction(function () use ($request, $status, $actor, $reason): WithdrawalRequest {
            $locked = WithdrawalRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();
            if (! WithdrawalStatus::canTransition($locked->status, $status)) {
                throw new RuntimeException('This request cannot move from '.$locked->status.' to '.$status.'. No balance was changed.');
            }
            $from = $locked->status;
            $locked->forceFill(['status' => $status])->save();
            $this->event($locked, $from, $status, $actor, $reason);

            return $locked->fresh();
        });
    }

    private function available(Member $member, string $wallet): string
    {
        $reserved = WithdrawalRequest::query()
            ->where('member_id', $member->member_id)
            ->where('source_wallet', $wallet)
            ->whereIn('status', self::RESERVED)
            ->sum('amount');

        return bcsub($this->balance($member, $wallet), bcadd((string) $reserved, '0', 4), 4);
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
