<?php

namespace Tests\Feature;

use App\Models\FinancialControl;
use App\Models\Member;
use App\Models\User;
use App\Models\WalletLedgerEntry;
use App\Models\WithdrawalRequest;
use App\Models\WithdrawalSetting;
use App\Models\WithdrawalStatusEvent;
use App\Services\WithdrawalWorkflow;
use App\Support\WalletLedgerDirection;
use App\Support\WithdrawalStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class WithdrawalOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_enable_settings_and_they_persist_after_refresh(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $historical = WithdrawalSetting::current();
        $historical->forceFill([
            'daily_limit' => '15.0000',
            'monthly_limit' => '40.0000',
            'address_validation_policy' => 'Historical address note.',
            'member_cancellation_policy' => 'Historical cancellation note.',
        ])->save();

        $this->actingAs($admin)
            ->put(route('admin.withdrawals.settings.update'), $this->settings([
                'submissions_enabled' => '1',
                'charge_type' => 'flat',
                'charge_value' => '1.5000',
            ]))
            ->assertRedirect(route('admin.withdrawals.settings'))
            ->assertSessionHas('success');

        $saved = WithdrawalSetting::current()->fresh();
        $this->assertTrue($saved->submissions_enabled);
        $this->assertTrue($saved->isReady());
        $this->assertSame('20.0000', $saved->minimum_amount);
        $this->assertSame('100.0000', $saved->maximum_amount);
        $this->assertSame('flat', $saved->charge_type);
        $this->assertSame('1.5000', $saved->charge_value);
        $this->assertSame(['roi', 'working'], $saved->eligible_wallets);
        $this->assertSame(['TRC20'], $saved->supported_networks);
        $this->assertSame('15.0000', $saved->daily_limit);
        $this->assertSame('40.0000', $saved->monthly_limit);
        $this->assertSame('Historical address note.', $saved->address_validation_policy);
        $this->assertSame('USDT', $saved->denomination);

        $this->actingAs($admin)
            ->get(route('admin.withdrawals.settings'))
            ->assertOk()
            ->assertSee('name="submissions_enabled"', false)
            ->assertSee('value="20.0000"', false)
            ->assertSee('value="100.0000"', false)
            ->assertSee('value="TRC20"', false)
            ->assertSee('checked', false);

        $this->assertFalse(FinancialControl::current()->withdrawal_requests_enabled);
        $this->assertFalse(FinancialControl::current()->withdrawal_approval_enabled);
        $this->assertFalse(FinancialControl::current()->external_payouts_enabled);
    }

    public function test_incomplete_rules_and_invalid_limits_cannot_enable_submissions(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->from(route('admin.withdrawals.settings'))
            ->put(route('admin.withdrawals.settings.update'), [
                'minimum_amount' => '20.0000',
                'submissions_enabled' => '1',
            ])->assertSessionHasErrors(['submissions_enabled', 'maximum_amount', 'eligible_wallets', 'network']);

        $this->actingAs($admin)->from(route('admin.withdrawals.settings'))
            ->put(route('admin.withdrawals.settings.update'), $this->settings([
                'minimum_amount' => '50.0000',
                'maximum_amount' => '10.0000',
                'submissions_enabled' => '1',
            ]))->assertSessionHasErrors('maximum_amount');

        $this->actingAs($admin)->from(route('admin.withdrawals.settings'))
            ->put(route('admin.withdrawals.settings.update'), $this->settings([
                'charge_type' => 'percent',
                'charge_value' => '140',
                'submissions_enabled' => '1',
            ]))->assertSessionHasErrors('charge_value');

        $this->actingAs($admin)->from(route('admin.withdrawals.settings'))
            ->put(route('admin.withdrawals.settings.update'), $this->settings([
                'eligible_wallets' => ['roi'],
                'network' => 'TRC20',
                'submissions_enabled' => '0',
            ]))->assertRedirect(route('admin.withdrawals.settings'));

        $saved = WithdrawalSetting::current()->fresh();
        $this->assertFalse($saved->submissions_enabled);
        $this->assertFalse($saved->isReady());
        $this->assertSame(['TRC20'], $saved->supported_networks);
    }

    public function test_member_request_reserves_funds_and_rejects_bad_amounts_wallets_and_pins(): void
    {
        $this->enableWithdrawals();
        $member = $this->member('STWD4001', '50.0000');
        $this->givePin($member, '482913');

        $this->withSession(['member_context_id' => $member->member_id])
            ->get(route('member.withdrawals.request'))
            ->assertOk()
            ->assertSeeText('Available USDT 50.0000')
            ->assertSee('name="security_pin_digits[]"', false);

        $this->withSession(['member_context_id' => $member->member_id])
            ->post(route('member.withdrawals.store'), $this->payload('482913', ['amount' => '10.0000']))
            ->assertSessionHasErrors('amount');

        $this->withSession(['member_context_id' => $member->member_id])
            ->post(route('member.withdrawals.store'), $this->payload('482913', ['amount' => '150.0000']))
            ->assertSessionHasErrors('amount');

        $this->withSession(['member_context_id' => $member->member_id])
            ->post(route('member.withdrawals.store'), $this->payload('482913', ['wallet' => 'activation']))
            ->assertSessionHasErrors('wallet');

        $this->withSession(['member_context_id' => $member->member_id])
            ->post(route('member.withdrawals.store'), $this->payload('000000'))
            ->assertSessionHasErrors('security_pin');

        $this->assertSame(0, WithdrawalRequest::query()->count());
        $this->assertSame(0, WalletLedgerEntry::query()->count());
        $this->assertSame('50.0000', $member->fresh()->roi_wallet_amount);

        $key = (string) Str::uuid();
        $this->withSession(['member_context_id' => $member->member_id])
            ->post(route('member.withdrawals.store'), $this->payload('482913', [
                'idempotency_key' => $key,
                'amount' => '20.0000',
            ]))
            ->assertRedirect(route('member.withdrawals.history'));

        $this->withSession(['member_context_id' => $member->member_id])
            ->post(route('member.withdrawals.store'), $this->payload('482913', [
                'idempotency_key' => $key,
                'amount' => '20.0000',
            ]))
            ->assertRedirect(route('member.withdrawals.history'));

        $request = WithdrawalRequest::query()->firstOrFail();
        $this->assertSame(1, WithdrawalRequest::query()->count());
        $this->assertSame(WithdrawalStatus::PENDING_REVIEW, $request->status);
        $this->assertSame('1.5000', $request->charge_amount);
        $this->assertSame('18.5000', $request->net_amount);
        $this->assertSame(1, WalletLedgerEntry::query()->where('direction', WalletLedgerDirection::HOLD)->count());
        $this->assertSame('50.0000', $member->fresh()->roi_wallet_amount);
        $this->assertSame('30.0000', app(WithdrawalWorkflow::class)->availableBalance($member->fresh(), 'roi'));

        $this->withSession(['member_context_id' => $member->member_id])
            ->get(route('member.withdrawals.history'))
            ->assertOk()
            ->assertSeeText($request->reference)
            ->assertSeeText('Pending Review');
    }

    public function test_percent_charge_insufficient_balance_and_a_second_reservation_cannot_overdraw(): void
    {
        $this->enableWithdrawals(['charge_type' => 'percent', 'charge_value' => '10.0000']);
        $member = $this->member('STWD4002', '50.0000');
        $this->givePin($member, '482913');
        $workflow = app(WithdrawalWorkflow::class);

        $first = $workflow->submit($member, $this->workflowInput('30.0000'));
        $this->assertSame('3.0000', $first->charge_amount);
        $this->assertSame('27.0000', $first->net_amount);

        $replay = $workflow->submit($member, $this->workflowInput('30.0000', $first->idempotency_key));
        $this->assertSame($first->id, $replay->id);
        $this->assertSame(1, WalletLedgerEntry::query()->count());

        try {
            $workflow->submit($member, $this->workflowInput('30.0000'));
            $this->fail('A second reservation used funds that were already held.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('amount', $exception->errors());
        }

        $this->assertSame(1, WithdrawalRequest::query()->count());
        $this->assertSame('50.0000', $member->fresh()->roi_wallet_amount);
        $this->assertSame('20.0000', $workflow->availableBalance($member->fresh(), 'roi'));
    }

    public function test_admin_approval_and_rejection_are_idempotent_and_do_not_send_a_payout(): void
    {
        $this->enableWithdrawals();
        $member = $this->member('STWD4003', '80.0000');
        $this->givePin($member, '482913');
        $admin = User::factory()->create(['is_admin' => true]);
        $workflow = app(WithdrawalWorkflow::class);
        $pending = $workflow->submit($member, $this->workflowInput('20.0000'));
        $held = $workflow->submit($member, $this->workflowInput('25.0000'));

        $this->actingAs($admin)
            ->post(route('admin.withdrawals.approve', $pending))
            ->assertRedirect(route('admin.withdrawals.show', $pending));
        $this->actingAs($admin)
            ->post(route('admin.withdrawals.approve', $pending))
            ->assertRedirect(route('admin.withdrawals.show', $pending));

        $this->assertSame(WithdrawalStatus::APPROVED, $pending->fresh()->status);
        $this->assertSame(1, WithdrawalStatusEvent::query()->where('withdrawal_request_id', $pending->id)->where('to_status', WithdrawalStatus::APPROVED)->count());
        $this->assertNull($pending->fresh()->payout_txid);
        $this->assertSame('35.0000', $workflow->availableBalance($member->fresh(), 'roi'));

        $this->actingAs($admin)
            ->post(route('admin.withdrawals.reject', $held), ['reason' => 'The address could not be confirmed.'])
            ->assertRedirect(route('admin.withdrawals.show', $held));
        $this->actingAs($admin)
            ->post(route('admin.withdrawals.reject', $held), ['reason' => 'The address could not be confirmed.'])
            ->assertRedirect(route('admin.withdrawals.show', $held));

        $this->assertSame(WithdrawalStatus::REJECTED, $held->fresh()->status);
        $this->assertSame(1, WalletLedgerEntry::query()->where('direction', WalletLedgerDirection::RELEASE)->count());
        $this->assertSame(1, WithdrawalStatusEvent::query()->where('withdrawal_request_id', $held->id)->where('to_status', WithdrawalStatus::REJECTED)->count());
        $this->assertSame('60.0000', $workflow->availableBalance($member->fresh(), 'roi'));
        $this->assertSame('80.0000', $member->fresh()->roi_wallet_amount);
        $this->assertSame('0.0000', $member->fresh()->working_wallet_amount);

        try {
            $workflow->settle($pending->fresh());
            $this->fail('An external payout was sent.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('External payouts are disabled', $exception->getMessage());
        }
        $this->assertNull($pending->fresh()->payout_txid);
        $this->assertFalse(FinancialControl::current()->external_payouts_enabled);
    }

    public function test_disabled_submissions_and_unauthorized_callers_cannot_move_funds(): void
    {
        $member = $this->member('STWD4004', '50.0000');
        $this->givePin($member, '482913');
        $admin = User::factory()->create(['is_admin' => true]);

        $this->get(route('admin.withdrawals.settings'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->put(route('admin.withdrawals.settings.update'), $this->settings(['submissions_enabled' => '1']))
            ->assertForbidden();
        $this->post(route('member.withdrawals.store'), $this->payload('482913'))->assertForbidden();

        $this->withSession(['member_context_id' => $member->member_id])
            ->post(route('member.withdrawals.store'), $this->payload('482913'))
            ->assertForbidden();

        $member->forceFill(['status' => 'inactive'])->save();
        $this->enableWithdrawals();
        try {
            app(WithdrawalWorkflow::class)->submit($member->fresh(), $this->workflowInput('20.0000'));
            $this->fail('An inactive member reserved funds.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('active member', $exception->getMessage());
        }

        $this->assertTrue(WithdrawalSetting::current()->fresh()->submissions_enabled);
        $this->assertSame(0, WithdrawalRequest::query()->count());
        $this->assertSame(0, WalletLedgerEntry::query()->count());
        $this->assertSame('50.0000', $member->fresh()->roi_wallet_amount);
        $this->assertTrue($admin->is_admin);
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
            'charge_type' => 'none',
            'eligible_wallets' => ['roi', 'working'],
            'network' => 'TRC20',
            'submissions_enabled' => '0',
        ], $override);
    }

    /**
     * @param  array<string, mixed>  $override
     */
    private function enableWithdrawals(array $override = []): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->put(route('admin.withdrawals.settings.update'), $this->settings(array_merge([
                'submissions_enabled' => '1',
                'charge_type' => 'flat',
                'charge_value' => '1.5000',
            ], $override)))
            ->assertRedirect(route('admin.withdrawals.settings'));
    }

    private function member(string $memberId, string $roi): Member
    {
        return Member::query()->create([
            'member_id' => $memberId,
            'sponsor_id' => 'ST666666',
            'sponsor_name' => 'Admin',
            'member_name' => 'Member '.$memberId,
            'mobile_no' => '93000'.substr(preg_replace('/\D/', '', $memberId).'00000', 0, 5),
            'email' => strtolower($memberId).'@example.test',
            'status' => 'active',
            'roi_wallet_amount' => $roi,
            'working_wallet_amount' => '0.0000',
            'activation_wallet_amount' => '0.0000',
        ]);
    }

    private function givePin(Member $member, string $pin): void
    {
        $member->forceFill([
            'security_pin_hash' => Hash::make($pin),
            'security_pin_set_at' => now(),
            'security_pin_failed_attempts' => 0,
            'security_pin_locked_until' => null,
        ])->save();
    }

    /**
     * @param  array<string, string>  $extra
     * @return array<string, string>
     */
    private function payload(string $pin, array $extra = []): array
    {
        return array_merge([
            'wallet' => 'roi',
            'amount' => '20.0000',
            'network' => 'TRC20',
            'address' => 'TPublicAddressOnly123',
            'idempotency_key' => (string) Str::uuid(),
            'security_pin' => $pin,
        ], $extra);
    }

    /**
     * @return array{wallet: string, amount: string, network: string, address: string, idempotency_key: string, security_pin: string}
     */
    private function workflowInput(string $amount, ?string $key = null): array
    {
        return [
            'wallet' => 'roi',
            'amount' => $amount,
            'network' => 'TRC20',
            'address' => 'TPublicAddressOnly123',
            'idempotency_key' => $key ?: (string) Str::uuid(),
            'security_pin' => '482913',
        ];
    }
}
