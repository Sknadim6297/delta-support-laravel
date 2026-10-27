<?php

namespace Tests\Feature;

use App\Models\Investment;
use App\Models\InvestmentWithdrawal;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvestmentWithdrawalTest extends TestCase
{
    use RefreshDatabase;

    private function signIn(): void
    {
        $this->actingAs(User::factory()->create());
    }

    private function member(string $id = 'ST700001'): Member
    {
        return Member::create([
            'member_id' => $id,
            'sponsor_id' => 'ST666666',
            'sponsor_name' => 'Admin',
            'member_name' => 'Withdrawal Member',
            'mobile_no' => substr('9' . $id . '0000000000', 0, 10),
            'pan_card_no' => 'WDR' . substr($id, -7),
            'email' => strtolower($id) . '@example.test',
            'status' => 'active',
        ]);
    }

    private function investment(Member $member, string $id = 'INV700001', string $status = 'expired'): Investment
    {
        return Investment::create([
            'investment_id' => $id,
            'member_id' => $member->member_id,
            'member_name' => $member->member_name,
            'amount' => '500.0000',
            'status' => $status,
            'closed_at' => $status === 'expired' ? now() : null,
        ]);
    }

    public function test_all_investment_withdrawal_pages_load(): void
    {
        $this->signIn();

        $this->get(route('admin.investments.closed-investments'))->assertOk();
        $this->get(route('admin.investments.investment-withdrawal-entry'))->assertOk();
        $this->get(route('admin.investments.investment-withdrawal-list'))->assertOk();
    }

    public function test_closed_list_shows_only_expired_database_investments(): void
    {
        $this->signIn();
        $member = $this->member();
        $expired = $this->investment($member);
        $active = $this->investment($member, 'INV700002', 'active');

        $this->get(route('admin.investments.closed-investments'))
            ->assertOk()
            ->assertSeeText($expired->investment_id)
            ->assertDontSeeText($active->investment_id);
    }

    public function test_admin_closed_list_displays_stored_closing_amount(): void
    {
        $this->signIn();
        $member = $this->member('ST700003');
        $expired = $this->investment($member, 'INV700005');
        $expired->update(['closing_amount' => '1500.0000']);
        $active = $this->investment($member, 'INV700006', 'active');

        $this->get(route('admin.investments.closed-investments'))
            ->assertOk()
            ->assertSeeText('Investment Amount (USDT)')
            ->assertSeeText('Closing Amount (USDT)')
            ->assertSeeText('USDT 500.0000')
            ->assertSeeText('USDT 1500.0000')
            ->assertSeeText($expired->investment_id)
            ->assertDontSeeText($active->investment_id);
    }

    public function test_withdrawal_entry_lookup_requires_matching_member_and_expired_investment(): void
    {
        $this->signIn();
        $member = $this->member();
        $otherMember = $this->member('ST700002');
        $investment = $this->investment($member);

        $this->getJson(route('admin.investments.investment-withdrawal-lookup', [
            'member_id' => $otherMember->member_id,
            'investment_id' => $investment->investment_id,
        ]))->assertStatus(422);

        $this->getJson(route('admin.investments.investment-withdrawal-lookup', [
            'member_id' => $member->member_id,
            'investment_id' => $investment->investment_id,
        ]))->assertOk()->assertJsonPath('eligible', true);
    }

    public function test_member_and_investment_lookup_endpoints_return_only_required_data(): void
    {
        $this->signIn();
        $member = $this->member();
        $investment = $this->investment($member);

        $this->getJson(route('admin.investments.member-lookup', [
            'member_id' => $member->member_id,
        ]))->assertOk()
            ->assertJson([
                'member_id' => $member->member_id,
                'member_name' => $member->member_name,
            ])
            ->assertJsonMissingPath('password');

        $this->getJson(route('admin.investments.investment-withdrawal-investment-lookup', [
            'investment_id' => $investment->investment_id,
        ]))->assertOk()
            ->assertJson([
                'investment_id' => $investment->investment_id,
                'investment_amount' => '500.0000',
            ])
            ->assertJsonMissingPath('member_id');

        $this->getJson(route('admin.investments.investment-withdrawal-investment-lookup', [
            'investment_id' => 'INV999999',
        ]))->assertStatus(422);
    }

    public function test_active_investment_and_invalid_amounts_are_rejected(): void
    {
        $this->signIn();
        $member = $this->member();
        $active = $this->investment($member, 'INV700003', 'active');

        $this->post(route('admin.investments.investment-withdrawal-store'), [
            'member_id' => $member->member_id,
            'investment_id' => $active->investment_id,
            'amount' => 100,
        ])->assertSessionHasErrors('investment_id');

        $expired = $this->investment($member, 'INV700004');

        foreach (['0', '-1', '501'] as $amount) {
            $this->post(route('admin.investments.investment-withdrawal-store'), [
                'member_id' => $member->member_id,
                'investment_id' => $expired->investment_id,
                'amount' => $amount,
            ])->assertSessionHasErrors('amount');
        }

        $this->assertDatabaseCount('investment_withdrawals', 0);
    }

    public function test_valid_withdrawal_is_saved_as_pending_and_cannot_be_repeated(): void
    {
        $this->signIn();
        $member = $this->member();
        $investment = $this->investment($member);

        $this->post(route('admin.investments.investment-withdrawal-store'), [
            'member_id' => $member->member_id,
            'investment_id' => $investment->investment_id,
            'amount' => '500.0000',
        ])->assertRedirect(route('admin.investments.investment-withdrawal-entry'));

        $this->assertDatabaseHas('investment_withdrawals', [
            'member_id' => $member->member_id,
            'investment_id' => $investment->investment_id,
            'withdrawal_amount' => '500.0000',
            'status' => 'pending',
        ]);

        $this->post(route('admin.investments.investment-withdrawal-store'), [
            'member_id' => $member->member_id,
            'investment_id' => $investment->investment_id,
            'amount' => '1',
        ])->assertSessionHasErrors('investment_id');

        $this->get(route('admin.investments.investment-withdrawal-list', [
            'member_id' => $member->member_id,
        ]))->assertOk()->assertSeeText($investment->investment_id);
    }

    public function test_closed_and_withdrawal_exports_use_real_records(): void
    {
        $this->signIn();
        $member = $this->member();
        $investment = $this->investment($member);
        InvestmentWithdrawal::create([
            'withdrawal_id' => 'IWD700001',
            'member_id' => $member->member_id,
            'member_name' => $member->member_name,
            'investment_id' => $investment->investment_id,
            'investment_amount' => '500.0000',
            'withdrawal_amount' => '500.0000',
            'status' => 'pending',
            'withdrawn_at' => now(),
        ]);

        $this->get(route('admin.investments.closed-investments.export'))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertHeader('content-disposition');
        $this->get(route('admin.investments.investment-withdrawal-list'))
            ->assertOk()
            ->assertSeeText($investment->investment_id);
    }
}
