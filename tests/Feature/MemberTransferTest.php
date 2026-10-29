<?php

namespace Tests\Feature;

use App\Models\FinancialControl;
use App\Models\LevelCommissionTransaction;
use App\Models\Member;
use App\Models\MemberTransfer;
use App\Models\MemberTransferSetting;
use App\Models\User;
use App\Models\WalletLedgerEntry;
use App\Models\WithdrawalRequest;
use App\Services\MemberTransferWorkflow;
use App\Services\WalletBalanceReconciliation;
use App\Support\WalletLedgerDirection;
use App\Support\WithdrawalStatus;
use App\Http\Controllers\Member\MemberTransferController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class MemberTransferTest extends TestCase
{
    use RefreshDatabase;

    private const PIN = '482913';

    public function test_guest_cannot_open_transfer_pages(): void
    {
        $this->get(route('member.transfers.create'))->assertForbidden();
        $this->get(route('member.transfers.history'))->assertForbidden();
        $this->get(route('admin.member-transfers.index'))->assertRedirect(route('login'));
    }

    public function test_member_without_a_pin_is_sent_to_pin_setup_and_returns(): void
    {
        $member = $this->member('ST100100', '100.0000');

        $this->withSession(['member_context_id' => $member->member_id])
            ->get(route('member.transfers.create'))
            ->assertRedirect(route('member.security-pin'));

        $this->withSession(['member_context_id' => $member->member_id])
            ->get(route('member.security-pin'))
            ->assertOk()
            ->assertSeeText('required before Working Wallet funds can be transferred');

        $this->withSession(['member_context_id' => $member->member_id])
            ->post(route('member.security-pin.store'), [
                'pin' => '482913',
                'pin_confirmation' => '482913',
            ])
            ->assertRedirect(route('member.transfers.create'));

        $this->assertNotSame('482913', $member->fresh()->security_pin_hash);
        $this->assertTrue(Hash::check('482913', (string) $member->fresh()->security_pin_hash));
    }

    public function test_successful_transfer_moves_working_balances_and_writes_matching_ledger_rows(): void
    {
        config(['deltasupport.legacy_financial_actions' => false]);
        $sender = $this->member('ST100100', '100.0000', self::PIN);
        $receiver = $this->member('ST100200', '0.0000', self::PIN);
        $this->levelCredit($sender, '100.0000');
        $this->enableTransfers();

        $transfer = $this->submit($sender, $receiver->member_id, '50');

        $transfer->assertRedirect();
        $stored = MemberTransfer::query()->firstOrFail();
        $this->assertSame('50.0000', (string) $stored->amount);
        $this->assertSame('0.0000', (string) $stored->fee_amount);
        $this->assertSame('working', $stored->source_wallet);
        $this->assertSame('working', $stored->destination_wallet);
        $this->assertSame(MemberTransfer::COMPLETED, $stored->status);
        $this->assertSame('50.0000', (string) $sender->fresh()->working_wallet_amount);
        $this->assertSame('50.0000', (string) $receiver->fresh()->working_wallet_amount);
        $this->assertSame('0.0000', (string) $sender->fresh()->roi_wallet_amount);
        $this->assertSame('0.0000', (string) $sender->fresh()->activation_wallet_amount);
        $this->assertSame('0.0000', (string) $receiver->fresh()->roi_wallet_amount);

        $debit = WalletLedgerEntry::query()->where('direction', WalletLedgerDirection::DEBIT)->firstOrFail();
        $credit = WalletLedgerEntry::query()->where('direction', WalletLedgerDirection::CREDIT)->firstOrFail();
        $this->assertSame('50.0000', (string) $debit->amount);
        $this->assertSame('50.0000', (string) $credit->amount);
        $this->assertSame('100.0000', (string) $debit->opening_balance);
        $this->assertSame('50.0000', (string) $debit->closing_balance);
        $this->assertSame('0.0000', (string) $credit->opening_balance);
        $this->assertSame('50.0000', (string) $credit->closing_balance);
        $this->assertSame($debit->id, $credit->counter_entry_id);
        $this->assertSame(0, bccomp(bcadd((string) $debit->closing_balance, (string) $credit->closing_balance, 4), '100.0000', 4));

        $this->withSession(['member_context_id' => $sender->member_id])
            ->get($transfer->headers->get('Location'))
            ->assertOk()
            ->assertSeeText($stored->transfer_reference)
            ->assertDontSee(self::PIN);

        $report = app(WalletBalanceReconciliation::class);
        $this->assertSame('0.0000', $report->inspect($sender->fresh())['working']['variance']);
        $this->assertTrue($report->inspect($sender->fresh())['working']['reconciled']);
        $this->assertSame('0.0000', $report->inspect($receiver->fresh())['working']['variance']);
        $this->assertTrue($report->inspect($receiver->fresh())['working']['reconciled']);
        $this->assertFinancialSwitchesStayOff();
        $this->assertFalse((bool) config('deltasupport.wallet_ledger.enabled'));
        $this->assertFalse((bool) config('deltasupport.package_income.enabled'));
    }

    public function test_limits_balance_pin_and_party_rules_reject_the_transfer(): void
    {
        $sender = $this->member('ST100100', '10.0000', self::PIN);
        $receiver = $this->member('ST100200', '0.0000');
        $this->enableTransfers();

        $this->review($sender, $receiver->member_id, '0.5000')->assertSessionHasErrors('amount');
        $this->review($sender, $receiver->member_id, '1001')->assertSessionHasErrors('amount');
        $this->review($sender, $receiver->member_id, '11')->assertSessionHasErrors('amount');
        $this->review($sender, $sender->member_id, '1')->assertSessionHasErrors('receiver_member_id');
        $this->review($sender, 'STMISSING', '1')->assertSessionHasErrors('receiver_member_id');

        $receiver->forceFill(['status' => 'inactive'])->save();
        $this->review($sender, $receiver->member_id, '1')->assertSessionHasErrors('receiver_member_id');
        $receiver->forceFill(['status' => 'active'])->save();

        $this->assertSame('10.0000', (string) $sender->fresh()->working_wallet_amount);
        $this->assertSame(0, MemberTransfer::query()->count());
    }

    public function test_inactive_sender_cannot_review_a_transfer(): void
    {
        $sender = $this->member('ST100100', '10.0000', self::PIN);
        $receiver = $this->member('ST100200', '0.0000');
        $sender->forceFill(['status' => 'inactive'])->save();
        $this->enableTransfers();

        $this->review($sender, $receiver->member_id, '1')->assertSessionHasErrors('transfer');
        $this->assertSame(0, MemberTransfer::query()->count());
    }

    public function test_wrong_missing_locked_and_password_pins_do_not_move_funds(): void
    {
        $sender = $this->member('ST100100', '40.0000', self::PIN);
        $sender->forceFill(['password' => Hash::make('159263')])->save();
        $receiver = $this->member('ST100200', '0.0000');
        $this->enableTransfers();
        $this->review($sender, $receiver->member_id, '5')->assertRedirect(route('member.transfers.confirm'));

        $this->confirm($sender, '111111')->assertSessionHasErrors('security_pin');
        $this->confirm($sender, '159263')->assertSessionHasErrors('security_pin');
        $sender->forceFill(['security_pin_locked_until' => now()->addMinutes(10)])->save();
        $this->confirm($sender, self::PIN)->assertSessionHasErrors('security_pin');
        $this->assertSame('40.0000', (string) $sender->fresh()->working_wallet_amount);
        $this->assertSame(0, MemberTransfer::query()->count());

        $open = $this->member('ST100300', '40.0000');
        $this->withSession(['member_context_id' => $open->member_id])
            ->post(route('member.transfers.review'), [
                'receiver_member_id' => $receiver->member_id,
                'amount' => '5',
            ])->assertRedirect(route('member.security-pin'));
    }

    public function test_demo_accounts_cannot_send_or_receive(): void
    {
        $sender = $this->member('ST100100', '40.0000', self::PIN);
        $demo = $this->member('ST100900', '40.0000', self::PIN, 'demo@local-test.invalid');
        $receiver = $this->member('ST100200', '0.0000');
        $this->enableTransfers();

        $this->review($demo, $receiver->member_id, '5')->assertSessionHasErrors('transfer');
        $this->review($sender, $demo->member_id, '5')->assertSessionHasErrors('receiver_member_id');
        $this->withSession(['member_context_id' => $sender->member_id])
            ->getJson(route('member.transfers.receiver', ['member_id' => $demo->member_id]))
            ->assertNotFound()
            ->assertJsonMissing(['member_name' => $demo->member_name]);
        $this->assertSame(0, MemberTransfer::query()->count());
    }

    public function test_reserved_withdrawals_and_activation_movement_share_available_funds(): void
    {
        $sender = $this->member('ST100100', '100.0000', self::PIN);
        $receiver = $this->member('ST100200', '0.0000');
        $this->enableTransfers();
        WithdrawalRequest::query()->create([
            'reference' => 'WD-TEST-RESERVE',
            'idempotency_key' => (string) Str::uuid(),
            'member_id' => $sender->member_id,
            'source_wallet' => 'working',
            'amount' => '40.0000',
            'denomination' => 'USDT',
            'status' => WithdrawalStatus::PENDING_REVIEW,
        ]);

        $this->review($sender, $receiver->member_id, '70')->assertSessionHasErrors('amount');
        $this->submit($sender, $receiver->member_id, '60')->assertRedirect();
        $this->assertSame('40.0000', (string) $sender->fresh()->working_wallet_amount);
        $this->assertSame('60.0000', (string) $receiver->fresh()->working_wallet_amount);
        $this->assertSame(WithdrawalStatus::PENDING_REVIEW, WithdrawalRequest::query()->value('status'));

        $this->actingAs(User::factory()->create())
            ->post(route('admin.activation-wallet.store-credit-entry'), [
                'member_id' => $sender->member_id,
                'amount' => '10',
            ])->assertSessionHasErrors('amount');
        $this->assertSame('40.0000', (string) $sender->fresh()->working_wallet_amount);
        $this->assertSame('0.0000', (string) $sender->fresh()->activation_wallet_amount);
    }

    public function test_duplicate_key_returns_the_original_and_a_second_spend_cannot_overdraw(): void
    {
        $sender = $this->member('ST100100', '100.0000', self::PIN);
        $receiver = $this->member('ST100200', '0.0000');
        $other = $this->member('ST100300', '100.0000', self::PIN);
        $this->enableTransfers();
        $key = (string) Str::uuid();
        $workflow = app(MemberTransferWorkflow::class);
        $first = $workflow->execute($sender, $this->payload($receiver->member_id, '60', $key));
        $second = $workflow->execute($sender->fresh(), $this->payload($receiver->member_id, '60', $key));

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, MemberTransfer::query()->count());
        $this->assertSame('40.0000', (string) $sender->fresh()->working_wallet_amount);

        try {
            $workflow->execute($other, $this->payload($receiver->member_id, '10', $key));
            $this->fail('Another member cannot reuse the idempotency key.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('another member', $exception->getMessage());
        }

        try {
            $workflow->execute($sender->fresh(), $this->payload($receiver->member_id, '60', (string) Str::uuid()));
            $this->fail('The second spend should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('amount', $exception->errors());
        }

        $this->assertSame('40.0000', (string) $sender->fresh()->working_wallet_amount);
        $this->assertSame('100.0000', (string) $other->fresh()->working_wallet_amount);
    }

    public function test_ledger_failure_rolls_the_transfer_back(): void
    {
        $sender = $this->member('ST100100', '80.0000', self::PIN);
        $receiver = $this->member('ST100200', '5.0000');
        $this->enableTransfers();
        $armed = true;
        $seen = 0;
        WalletLedgerEntry::creating(function () use (&$armed, &$seen): void {
            if (! $armed) {
                return;
            }
            $seen++;
            if ($seen === 2) {
                throw new RuntimeException('Simulated ledger failure.');
            }
        });

        try {
            app(MemberTransferWorkflow::class)->execute($sender, $this->payload($receiver->member_id, '20', (string) Str::uuid()));
            $this->fail('The ledger failure should stop the transfer.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated ledger failure.', $exception->getMessage());
        } finally {
            $armed = false;
        }

        $this->assertSame(0, MemberTransfer::query()->count());
        $this->assertSame(0, WalletLedgerEntry::query()->count());
        $this->assertSame('80.0000', (string) $sender->fresh()->working_wallet_amount);
        $this->assertSame('5.0000', (string) $receiver->fresh()->working_wallet_amount);
    }

    public function test_daily_limit_uses_the_kolkata_business_date(): void
    {
        $sender = $this->member('ST100100', '6000.0000', self::PIN);
        $receiver = $this->member('ST100200', '0.0000');
        $this->enableTransfers();
        $workflow = app(MemberTransferWorkflow::class);
        foreach (['1000', '1000', '1000', '1000', '1000'] as $amount) {
            $workflow->execute($sender->fresh(), $this->payload($receiver->member_id, $amount, (string) Str::uuid()));
        }

        try {
            $workflow->execute($sender->fresh(), $this->payload($receiver->member_id, '1', (string) Str::uuid()));
            $this->fail('The daily limit should stop the next transfer.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('amount', $exception->errors());
        }

        $this->assertSame('1000.0000', (string) $sender->fresh()->working_wallet_amount);
        $this->assertSame('5000.0000', (string) $receiver->fresh()->working_wallet_amount);
    }

    public function test_history_is_limited_to_the_parties_and_admin_report_counts_each_transfer_once(): void
    {
        $sender = $this->member('ST100100', '80.0000', self::PIN);
        $receiver = $this->member('ST100200', '0.0000', self::PIN);
        $outsider = $this->member('ST100300', '0.0000', self::PIN);
        $this->enableTransfers();
        $this->submit($sender, $receiver->member_id, '15')->assertRedirect();
        $transfer = MemberTransfer::query()->firstOrFail();

        $this->withSession(['member_context_id' => $sender->member_id])
            ->get(route('member.transfers.history'))
            ->assertOk()
            ->assertSeeText($transfer->transfer_reference)
            ->assertSeeText('Sent');
        $this->withSession(['member_context_id' => $receiver->member_id])
            ->get(route('member.transfers.history'))
            ->assertOk()
            ->assertSeeText('Received');
        $this->withSession(['member_context_id' => $outsider->member_id])
            ->get(route('member.transfers.history'))
            ->assertOk()
            ->assertDontSeeText($transfer->transfer_reference);
        $this->withSession(['member_context_id' => $outsider->member_id])
            ->get(route('member.transfers.show', $transfer))
            ->assertNotFound();

        $admin = User::factory()->create();
        $memberUser = User::factory()->create(['is_admin' => false]);
        $this->actingAs($memberUser)->get(route('admin.member-transfers.index'))->assertForbidden();
        $this->actingAs($admin)
            ->get(route('admin.member-transfers.index'))
            ->assertOk()
            ->assertSeeText('USDT 15.0000')
            ->assertSeeText($transfer->transfer_reference);
        $csv = $this->actingAs($admin)->get(route('admin.member-transfers.export'))->assertOk()->streamedContent();
        $this->assertSame(1, substr_count($csv, $transfer->transfer_reference));
        $this->assertStringNotContainsString(self::PIN, $csv);
    }

    public function test_disabled_switch_blocks_movement_and_settings_do_not_enable_other_controls(): void
    {
        $sender = $this->member('ST100100', '80.0000', self::PIN);
        $receiver = $this->member('ST100200', '0.0000');
        $this->review($sender, $receiver->member_id, '5')
            ->assertSessionHasErrors('transfer');
        $this->assertSame(0, MemberTransfer::query()->count());

        $admin = User::factory()->create();
        $this->actingAs($admin)->put(route('admin.member-transfers.settings.update'), [
            'minimum_amount' => '1',
            'maximum_amount' => '1000',
            'daily_limit' => '5000',
            'fee_amount' => '0',
        ])->assertRedirect(route('admin.member-transfers.index'));

        $this->assertFalse((bool) MemberTransferSetting::current()->member_transfers_enabled);
        $this->assertFinancialSwitchesStayOff();
    }

    public function test_completed_amount_cannot_be_edited_and_reversal_restores_both_wallets(): void
    {
        $sender = $this->member('ST100100', '80.0000', self::PIN);
        $receiver = $this->member('ST100200', '0.0000');
        $this->levelCredit($sender, '80.0000');
        $this->enableTransfers();
        $this->submit($sender, $receiver->member_id, '20')->assertRedirect();
        $transfer = MemberTransfer::query()->firstOrFail();

        try {
            $transfer->amount = '1.0000';
            $transfer->save();
            $this->fail('The original amount should stay immutable.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Completed transfer amounts cannot be edited.', $exception->getMessage());
        }

        $admin = User::factory()->create();
        $this->actingAs($admin)->post(route('admin.member-transfers.reverse', $transfer), [
            'reason' => 'Posted to the wrong member.',
        ])->assertRedirect(route('admin.member-transfers.show', $transfer));

        $this->assertSame('20.0000', (string) $transfer->fresh()->amount);
        $this->assertSame(MemberTransfer::REVERSED, $transfer->fresh()->status);
        $this->assertSame('80.0000', (string) $sender->fresh()->working_wallet_amount);
        $this->assertSame('0.0000', (string) $receiver->fresh()->working_wallet_amount);
        $this->assertSame('0.0000', app(WalletBalanceReconciliation::class)->inspect($sender->fresh())['working']['variance']);
        $this->assertSame('0.0000', app(WalletBalanceReconciliation::class)->inspect($receiver->fresh())['working']['variance']);
    }

    public function test_client_supplied_sender_and_balance_are_ignored(): void
    {
        $sender = $this->member('ST100100', '12.0000', self::PIN);
        $rich = $this->member('ST100500', '500.0000', self::PIN);
        $receiver = $this->member('ST100200', '0.0000');
        $this->enableTransfers();

        $this->withSession(['member_context_id' => $sender->member_id])
            ->post(route('member.transfers.review'), [
                'receiver_member_id' => $receiver->member_id,
                'amount' => '20',
                'sender_member_id' => $rich->member_id,
                'working_wallet_amount' => '500.0000',
            ])->assertSessionHasErrors('amount');

        $this->assertSame('12.0000', (string) $sender->fresh()->working_wallet_amount);
        $this->assertSame('500.0000', (string) $rich->fresh()->working_wallet_amount);
        $this->assertSame(0, MemberTransfer::query()->count());
    }

    private function submit(Member $sender, string $receiverId, string $amount)
    {
        $this->review($sender, $receiverId, $amount)->assertRedirect(route('member.transfers.confirm'));

        return $this->confirm($sender, self::PIN);
    }

    private function review(Member $sender, string $receiverId, string $amount)
    {
        return $this->withSession(['member_context_id' => $sender->member_id])
            ->post(route('member.transfers.review'), [
                'receiver_member_id' => $receiverId,
                'amount' => $amount,
            ]);
    }

    private function confirm(Member $sender, string $pin)
    {
        $preview = session(MemberTransferController::PREVIEW, []);

        return $this->withSession(['member_context_id' => $sender->member_id])
            ->post(route('member.transfers.store'), [
                'receiver_member_id' => $preview['receiver_member_id'] ?? '',
                'amount' => $preview['amount'] ?? '',
                'idempotency_key' => $preview['idempotency_key'] ?? (string) Str::uuid(),
                'security_pin' => $pin,
            ]);
    }

    /**
     * @return array{receiver_member_id: string, amount: string, idempotency_key: string, security_pin: string}
     */
    private function payload(string $receiverId, string $amount, string $key): array
    {
        return [
            'receiver_member_id' => $receiverId,
            'amount' => $amount,
            'idempotency_key' => $key,
            'security_pin' => self::PIN,
        ];
    }

    private function enableTransfers(): void
    {
        MemberTransferSetting::current()->forceFill([
            'member_transfers_enabled' => true,
        ])->save();
    }

    private function member(string $memberId, string $working, ?string $pin = null, string $email = ''): Member
    {
        $member = Member::query()->create([
            'member_id' => $memberId,
            'sponsor_id' => 'ST666666',
            'sponsor_name' => 'Admin',
            'member_name' => 'Member '.$memberId,
            'mobile_no' => '9000000000',
            'pan_card_no' => 'ABCDE1234F',
            'email' => $email !== '' ? $email : strtolower($memberId).'@example.test',
            'status' => 'active',
            'working_wallet_amount' => $working,
            'roi_wallet_amount' => '0.0000',
            'activation_wallet_amount' => '0.0000',
        ]);
        if ($pin !== null) {
            $member->forceFill([
                'security_pin_hash' => Hash::make($pin),
                'security_pin_set_at' => now(),
            ])->save();
        }

        return $member;
    }

    private function levelCredit(Member $member, string $amount): void
    {
        LevelCommissionTransaction::query()->create([
            'reference' => 'LC-'.$member->member_id,
            'investment_id' => 'INV-'.$member->member_id,
            'member_id' => $member->member_id,
            'member_name' => $member->member_name,
            'from_member_id' => 'ST000001',
            'from_member_name' => 'Source',
            'level' => 1,
            'business_date' => '2026-10-29',
            'on_amount' => $amount,
            'rate_percentage' => '25.000',
            'income_amount' => $amount,
        ]);
    }

    private function assertFinancialSwitchesStayOff(): void
    {
        $controls = FinancialControl::current();
        foreach ([
            'roi_calculation_enabled',
            'roi_wallet_crediting_enabled',
            'level_calculation_enabled',
            'level_wallet_crediting_enabled',
            'rewards_evaluation_enabled',
            'withdrawal_requests_enabled',
            'withdrawal_approval_enabled',
            'external_payouts_enabled',
            'daily_financial_cycle_enabled',
        ] as $flag) {
            $this->assertFalse((bool) $controls->{$flag}, $flag);
        }
    }
}
