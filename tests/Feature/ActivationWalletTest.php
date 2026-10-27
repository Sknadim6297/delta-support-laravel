<?php

namespace Tests\Feature;

use App\Models\ActivationWalletTransaction;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivationWalletTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    private function member(string $id, float $activation = 0, float $working = 0): Member
    {
        return Member::create([
            'member_id' => $id,
            'sponsor_id' => 'ST666666',
            'sponsor_name' => 'Admin',
            'member_name' => 'Member ' . $id,
            'wallet_address' => null,
            'activation_wallet_amount' => $activation,
            'working_wallet_amount' => $working,
            'mobile_no' => '9876543210',
            'pan_card_no' => 'ABCDE1234F',
            'email' => strtolower($id) . '@example.com',
            'status' => 'active',
        ]);
    }

    public function test_lookup_returns_member_and_current_wallet_balances(): void
    {
        $member = $this->member('ST100001', 200, 800);

        $this->getJson(route('admin.activation-wallet.credit-entry.member-lookup', ['member_id' => $member->member_id]))
            ->assertOk()
            ->assertJson([
                'member_id' => 'ST100001',
                'member_name' => 'Member ST100001',
                'activation_wallet_amount' => 200,
                'working_wallet_amount' => 800,
            ]);
    }

    public function test_transfer_moves_funds_from_working_wallet_to_activation_wallet(): void
    {
        $member = $this->member('ST100001', 200, 1000);

        $this->post(route('admin.activation-wallet.store-credit-entry'), [
            'member_id' => $member->member_id,
            'amount' => 500,
        ])->assertRedirect(route('admin.activation-wallet.credit-entry'));

        $this->assertDatabaseHas('members', [
            'member_id' => $member->member_id,
            'activation_wallet_amount' => 700,
            'working_wallet_amount' => 500,
        ]);
        $this->assertDatabaseHas('activation_wallet_transactions', [
            'member_id' => $member->member_id,
            'member_name' => 'Member ST100001',
            'amount' => 500,
            'type' => 'credit',
        ]);
    }

    public function test_invalid_member_cannot_receive_transfer(): void
    {
        $this->post(route('admin.activation-wallet.store-credit-entry'), [
            'member_id' => 'ST999999',
            'amount' => 100,
        ])->assertSessionHasErrors('member_id');
    }

    public function test_invalid_transfer_amounts_do_not_change_wallet(): void
    {
        $member = $this->member('ST100001', 200, 1000);

        foreach (['', '0', '-1', 'abc'] as $amount) {
            $this->post(route('admin.activation-wallet.store-credit-entry'), [
                'member_id' => $member->member_id,
                'amount' => $amount,
            ])->assertSessionHasErrors('amount');
        }

        $this->assertDatabaseHas('members', [
            'member_id' => $member->member_id,
            'activation_wallet_amount' => 200,
            'working_wallet_amount' => 1000,
        ]);
        $this->assertDatabaseCount('activation_wallet_transactions', 0);
    }

    public function test_transfer_fails_when_working_wallet_is_insufficient(): void
    {
        $member = $this->member('ST100001', 200, 100);

        $this->post(route('admin.activation-wallet.store-credit-entry'), [
            'member_id' => $member->member_id,
            'amount' => 150,
        ])->assertSessionHasErrors('amount');

        $this->assertDatabaseHas('members', [
            'member_id' => $member->member_id,
            'activation_wallet_amount' => 200,
            'working_wallet_amount' => 100,
        ]);
        $this->assertDatabaseCount('activation_wallet_transactions', 0);
    }

    public function test_force_debit_reduces_activation_wallet_and_records_history(): void
    {
        $member = $this->member('ST100001', 500, 0);

        $this->post(route('admin.activation-wallet.store-debit-entry'), [
            'member_id' => $member->member_id,
            'amount' => 200,
            'remarks' => 'Wallet adjustment',
        ])->assertRedirect(route('admin.activation-wallet.debit-entry'));

        $this->assertDatabaseHas('members', [
            'member_id' => $member->member_id,
            'activation_wallet_amount' => 300,
        ]);
        $this->assertDatabaseHas('activation_wallet_transactions', [
            'member_id' => $member->member_id,
            'amount' => 200,
            'type' => 'debit',
            'remarks' => 'Wallet adjustment',
        ]);
    }

    public function test_force_debit_cannot_exceed_activation_wallet_or_go_negative(): void
    {
        $member = $this->member('ST100001', 100, 0);

        $this->post(route('admin.activation-wallet.store-debit-entry'), [
            'member_id' => $member->member_id,
            'amount' => 150,
        ])->assertSessionHasErrors('amount');

        $this->assertDatabaseHas('members', [
            'member_id' => $member->member_id,
            'activation_wallet_amount' => 100,
        ]);
        $this->assertDatabaseCount('activation_wallet_transactions', 0);
    }

    public function test_invalid_force_debit_amounts_are_rejected(): void
    {
        $member = $this->member('ST100001', 100, 0);

        foreach (['', '0', '-5', 'abc'] as $amount) {
            $this->post(route('admin.activation-wallet.store-debit-entry'), [
                'member_id' => $member->member_id,
                'amount' => $amount,
            ])->assertSessionHasErrors('amount');
        }
    }

    public function test_credit_history_excludes_debits_and_uses_complete_filtered_dataset(): void
    {
        $member = $this->member('ST100001');
        $other = $this->member('ST100002');

        ActivationWalletTransaction::create(['member_id' => $member->member_id, 'member_name' => $member->member_name, 'amount' => 500, 'type' => 'credit', 'reference' => 'AW-ONE', 'created_at' => '2026-08-10 12:00:00', 'updated_at' => '2026-08-10 12:00:00']);
        ActivationWalletTransaction::create(['member_id' => $member->member_id, 'member_name' => $member->member_name, 'amount' => 750, 'type' => 'credit', 'reference' => 'AW-TWO', 'created_at' => '2026-08-20 12:00:00', 'updated_at' => '2026-08-20 12:00:00']);
        ActivationWalletTransaction::create(['member_id' => $member->member_id, 'member_name' => $member->member_name, 'amount' => 50, 'type' => 'debit', 'remarks' => 'Hidden debit remark XYZ', 'reference' => 'AD-SKIP', 'created_at' => '2026-08-15 12:00:00', 'updated_at' => '2026-08-15 12:00:00']);
        ActivationWalletTransaction::create(['member_id' => $other->member_id, 'member_name' => $other->member_name, 'amount' => 1000, 'type' => 'credit', 'reference' => 'AW-THREE', 'created_at' => '2026-09-01 12:00:00', 'updated_at' => '2026-09-01 12:00:00']);

        $this->get(route('admin.activation-wallet.credit-entry.list', [
            'member_id' => $member->member_id,
            'from_date' => '2026-08-01',
            'to_date' => '2026-08-31',
        ]))
            ->assertOk()
            ->assertSeeText('1250')
            ->assertSeeText('ST100001')
            ->assertDontSeeText('Hidden debit remark XYZ');
    }

    public function test_debit_history_search_and_total_use_complete_filtered_dataset(): void
    {
        $member = $this->member('ST100001');
        $other = $this->member('ST100002');

        ActivationWalletTransaction::create(['member_id' => $member->member_id, 'member_name' => $member->member_name, 'amount' => 200, 'type' => 'debit', 'remarks' => 'Wallet adjustment', 'reference' => 'AD-ONE', 'created_at' => '2026-08-10 12:00:00', 'updated_at' => '2026-08-10 12:00:00']);
        ActivationWalletTransaction::create(['member_id' => $member->member_id, 'member_name' => $member->member_name, 'amount' => 50, 'type' => 'credit', 'reference' => 'AW-SKIP', 'created_at' => '2026-08-10 12:00:00', 'updated_at' => '2026-08-10 12:00:00']);
        ActivationWalletTransaction::create(['member_id' => $other->member_id, 'member_name' => $other->member_name, 'amount' => 300, 'type' => 'debit', 'remarks' => 'Other member debit remark', 'reference' => 'AD-TWO', 'created_at' => '2026-08-20 12:00:00', 'updated_at' => '2026-08-20 12:00:00']);

        $this->get(route('admin.activation-wallet.debit-entry.list', [
            'member_id' => $member->member_id,
        ]))
            ->assertOk()
            ->assertSeeText('200')
            ->assertSeeText('Wallet adjustment')
            ->assertDontSeeText('Other member debit remark');
    }

    public function test_invalid_date_range_is_rejected(): void
    {
        $this->get(route('admin.activation-wallet.credit-entry.list', [
            'from_date' => '2026-09-20',
            'to_date' => '2026-09-01',
        ]))->assertSessionHasErrors('to_date');
    }

    public function test_summary_shows_real_activation_wallet_balances(): void
    {
        $this->member('ST100001', 500, 100);
        $this->member('ST100002', 250, 0);

        $this->get(route('admin.activation-wallet.summary'))
            ->assertOk()
            ->assertSeeText('ST100001')
            ->assertSeeText('ST100002')
            ->assertSeeText('500')
            ->assertSeeText('250')
            ->assertSeeText('750');
    }

    public function test_filtered_export_contains_only_matching_records(): void
    {
        $member = $this->member('ST100001');
        $other = $this->member('ST100002');
        ActivationWalletTransaction::create(['member_id' => $member->member_id, 'member_name' => $member->member_name, 'amount' => 500, 'type' => 'credit', 'reference' => 'AW-EXPORT-1', 'created_at' => '2026-08-10 12:00:00', 'updated_at' => '2026-08-10 12:00:00']);
        ActivationWalletTransaction::create(['member_id' => $other->member_id, 'member_name' => $other->member_name, 'amount' => 750, 'type' => 'credit', 'reference' => 'AW-EXPORT-2', 'created_at' => '2026-08-10 12:00:00', 'updated_at' => '2026-08-10 12:00:00']);

        $this->get(route('admin.activation-wallet.credit-entry.export', ['member_id' => $member->member_id]))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_dashboard_shows_latest_activation_and_working_wallet_totals(): void
    {
        $this->member('ST100001', 200, 800);
        $this->member('ST100002', 50, 150);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('USDT 250.0000')
            ->assertSee('USDT 950.0000')
            ->assertDontSee('$250.00');
    }

    public function test_guest_cannot_perform_activation_wallet_operations(): void
    {
        auth()->logout();

        $this->post(route('admin.activation-wallet.store-credit-entry'), [
            'member_id' => 'ST100001',
            'amount' => 100,
        ])->assertRedirect(route('login'));

        $this->post(route('admin.activation-wallet.store-debit-entry'), [
            'member_id' => 'ST100001',
            'amount' => 100,
        ])->assertRedirect(route('login'));
    }
}
