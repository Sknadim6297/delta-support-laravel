<?php

namespace Tests\Feature;

use App\Models\ActivationWalletTransaction;
use App\Models\FinancialControl;
use App\Models\Member;
use App\Models\User;
use App\Models\WalletLedgerEntry;
use App\Models\WithdrawalRequest;
use App\Services\WithdrawalBalances;
use App\Services\WithdrawalWorkflow;
use App\Support\WalletLedgerDirection;
use App\Support\WithdrawalStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class WithdrawalSettlementTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_settlement_debits_the_reservation_once_and_keeps_available_balance(): void
    {
        $this->enableWithdrawals();
        $member = $this->member('STWD5001', '80.0000');
        $this->givePin($member, '482913');
        $admin = User::factory()->create(['is_admin' => true]);
        $workflow = app(WithdrawalWorkflow::class);
        $balances = app(WithdrawalBalances::class);
        $first = $workflow->submit($member, $this->input('20.0000'));
        $second = $workflow->submit($member, $this->input('25.0000'));
        $workflow->approve($first, $admin);
        $workflow->approve($second, $admin);

        $before = $balances->available($member->fresh(), 'roi');
        $this->assertSame('35.0000', $before);

        $paidAt = now()->toDateTimeString();
        $txid = 'abcdef0123456789abcdef0123456789';
        $this->actingAs($admin)
            ->post(route('admin.withdrawals.complete', $first), [
                'network' => 'TRC20',
                'txid' => $txid,
                'amount' => '18.5000',
                'paid_at' => $paidAt,
            ])->assertRedirect(route('admin.withdrawals.show', $first));

        $this->actingAs($admin)
            ->post(route('admin.withdrawals.complete', $first), [
                'network' => 'TRC20',
                'txid' => $txid,
                'amount' => '18.5000',
                'paid_at' => $paidAt,
            ])->assertRedirect(route('admin.withdrawals.show', $first));

        $paid = $first->fresh();
        $member->refresh();
        $this->assertSame(WithdrawalStatus::COMPLETED, $paid->status);
        $this->assertSame('TRC20', $paid->payout_network);
        $this->assertSame($txid, $paid->payout_txid);
        $this->assertNotNull($paid->paid_at);
        $this->assertSame('60.0000', $member->roi_wallet_amount);
        $this->assertSame('25.0000', $balances->reserved($member, 'roi'));
        $this->assertSame('35.0000', $balances->available($member, 'roi'));
        $this->assertSame('20.0000', $balances->paid($member, 'roi'));
        $this->assertSame(1, WalletLedgerEntry::query()->where('direction', WalletLedgerDirection::DEBIT)->count());
        $debit = WalletLedgerEntry::query()->where('direction', WalletLedgerDirection::DEBIT)->firstOrFail();
        $this->assertSame('20.0000', $debit->amount);
        $this->assertSame('80.0000', $debit->opening_balance);
        $this->assertSame('60.0000', $debit->closing_balance);
        $this->assertSame('SETTLE-'.$paid->reference, $debit->reference);

        try {
            $workflow->recordManualPayout($paid, $admin, [
                'network' => 'TRC20',
                'txid' => 'bbbbbb0123456789abcdef0123456789',
                'amount' => '18.5000',
                'paid_at' => $paidAt,
            ]);
            $this->fail('A second payout was recorded.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('already paid', $exception->getMessage());
        }

        $this->actingAs($admin)
            ->post(route('admin.withdrawals.reject', $paid), ['reason' => 'Return the funds.'])
            ->assertForbidden();

        $member->refresh();
        $this->assertSame('60.0000', $member->roi_wallet_amount);
        $this->assertSame('35.0000', $balances->available($member, 'roi'));
        $this->assertSame(1, WalletLedgerEntry::query()->where('direction', WalletLedgerDirection::DEBIT)->count());
        $this->assertSame(0, WalletLedgerEntry::query()->where('reference', 'RELEASE-'.$paid->reference)->count());

        $this->actingAs($admin)
            ->get(route('admin.wallets.overview'))
            ->assertOk()
            ->assertSeeText('Total balance')
            ->assertSeeText('Reserved balance')
            ->assertSeeText('Available balance')
            ->assertSeeText('Paid withdrawals')
            ->assertSeeText('USDT 20.0000')
            ->assertSeeText('USDT 25.0000')
            ->assertSeeText('USDT 35.0000');

        $this->withSession(['member_context_id' => $member->member_id])
            ->get(route('member.wallets.overview'))
            ->assertOk()
            ->assertSeeText('Total balance USDT 60.0000')
            ->assertSeeText('Reserved balance USDT 25.0000')
            ->assertSeeText('Available balance USDT 35.0000')
            ->assertSeeText('Paid withdrawals USDT 20.0000');

        $this->assertFalse(FinancialControl::current()->external_payouts_enabled);
        try {
            $workflow->settle($paid);
            $this->fail('An automatic payout was sent.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('External payouts are disabled', $exception->getMessage());
        }
    }

    public function test_settlement_rejects_unverified_payout_details_and_pending_requests(): void
    {
        $this->enableWithdrawals(['charge_type' => 'percent', 'charge_value' => '10.0000']);
        $member = $this->member('STWD5002', '50.0000', '0.0000', '40.0000');
        $this->givePin($member, '482913');
        $admin = User::factory()->create(['is_admin' => true]);
        $workflow = app(WithdrawalWorkflow::class);
        $pending = $workflow->submit($member, $this->input('20.0000'));
        $stored = $member->fresh()->roi_wallet_amount;

        $this->actingAs($admin)
            ->from(route('admin.withdrawals.show', $pending))
            ->post(route('admin.withdrawals.complete', $pending), $this->payout('18.0000'))
            ->assertForbidden();
        $this->assertSame($stored, $member->fresh()->roi_wallet_amount);
        $this->assertSame(0, WalletLedgerEntry::query()->where('direction', WalletLedgerDirection::DEBIT)->count());

        $approved = $workflow->approve($pending->fresh(), $admin);
        $this->actingAs($admin)->from(route('admin.withdrawals.show', $approved))
            ->post(route('admin.withdrawals.complete', $approved), $this->payout('20.0000'))
            ->assertSessionHasErrors('amount');
        $this->actingAs($admin)->from(route('admin.withdrawals.show', $approved))
            ->post(route('admin.withdrawals.complete', $approved), $this->payout('18.0000', ['network' => 'ERC20']))
            ->assertSessionHasErrors('network');
        $this->actingAs($admin)->from(route('admin.withdrawals.show', $approved))
            ->post(route('admin.withdrawals.complete', $approved), $this->payout('18.0000', ['txid' => 'short']))
            ->assertSessionHasErrors('txid');
        $this->actingAs($admin)->from(route('admin.withdrawals.show', $approved))
            ->post(route('admin.withdrawals.complete', $approved), $this->payout('18.0000', ['txid' => '']))
            ->assertSessionHasErrors('txid');
        $this->actingAs($admin)->from(route('admin.withdrawals.show', $approved))
            ->post(route('admin.withdrawals.complete', $approved), $this->payout('18.0000', [
                'paid_at' => now()->addHour()->toDateTimeString(),
            ]))->assertSessionHasErrors('paid_at');

        $this->assertSame(WithdrawalStatus::APPROVED, $approved->fresh()->status);
        $this->assertSame('50.0000', $member->fresh()->roi_wallet_amount);
        $this->assertSame(0, WalletLedgerEntry::query()->where('direction', WalletLedgerDirection::DEBIT)->count());

        auth()->logout();
        $this->post(route('admin.withdrawals.complete', $approved), $this->payout('18.0000'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->post(route('admin.withdrawals.complete', $approved), $this->payout('18.0000'))
            ->assertForbidden();

        $activation = $workflow->submit($member->fresh(), $this->input('20.0000', 'activation'));
        $workflow->approve($activation, $admin);
        $workflow->recordManualPayout($activation->fresh(), $admin, [
            'network' => 'TRC20',
            'txid' => 'cccccc0123456789abcdef0123456789',
            'amount' => '18.0000',
            'paid_at' => now()->toDateTimeString(),
        ]);

        $this->assertSame('20.0000', $member->fresh()->activation_wallet_amount);
        $this->assertSame('50.0000', $member->fresh()->roi_wallet_amount);
        $this->assertSame(1, ActivationWalletTransaction::query()->where('type', 'debit')->count());
        $this->assertSame('20.0000', ActivationWalletTransaction::query()->where('type', 'debit')->value('amount'));
        $this->assertSame('20.0000', app(WithdrawalBalances::class)->available($member->fresh(), 'activation'));
    }

    /**
     * @param  array<string, mixed>  $override
     * @return array<string, mixed>
     */
    private function settings(array $override = []): array
    {
        return array_merge([
            'minimum_amount' => '20.0000',
            'maximum_amount' => '100.0000',
            'charge_type' => 'flat',
            'charge_value' => '1.5000',
            'eligible_wallets' => ['roi', 'working', 'activation'],
            'network' => 'TRC20',
            'submissions_enabled' => '1',
        ], $override);
    }

    /**
     * @param  array<string, mixed>  $override
     */
    private function enableWithdrawals(array $override = []): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->put(route('admin.withdrawals.settings.update'), $this->settings($override))
            ->assertRedirect(route('admin.withdrawals.settings'));
    }

    private function member(string $memberId, string $roi, string $working = '0.0000', string $activation = '0.0000'): Member
    {
        return Member::query()->create([
            'member_id' => $memberId,
            'sponsor_id' => 'ST666666',
            'sponsor_name' => 'Admin',
            'member_name' => 'Member '.$memberId,
            'mobile_no' => '94000'.substr(preg_replace('/\D/', '', $memberId).'00000', 0, 5),
            'email' => strtolower($memberId).'@example.test',
            'status' => 'active',
            'roi_wallet_amount' => $roi,
            'working_wallet_amount' => $working,
            'activation_wallet_amount' => $activation,
        ]);
    }

    private function givePin(Member $member, string $pin): void
    {
        $member->forceFill([
            'security_pin_hash' => Hash::make($pin),
            'security_pin_set_at' => now(),
        ])->save();
    }

    /**
     * @return array{wallet: string, amount: string, network: string, address: string, idempotency_key: string, security_pin: string}
     */
    private function input(string $amount, string $wallet = 'roi'): array
    {
        return [
            'wallet' => $wallet,
            'amount' => $amount,
            'network' => 'TRC20',
            'address' => 'TPublicAddressOnly123',
            'idempotency_key' => (string) Str::uuid(),
            'security_pin' => '482913',
        ];
    }

    /**
     * @param  array<string, string>  $extra
     * @return array<string, string>
     */
    private function payout(string $amount, array $extra = []): array
    {
        return array_merge([
            'network' => 'TRC20',
            'txid' => 'abcdef0123456789abcdef0123456789',
            'amount' => $amount,
            'paid_at' => now()->toDateTimeString(),
        ], $extra);
    }
}
