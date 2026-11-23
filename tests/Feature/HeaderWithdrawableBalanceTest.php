<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\User;
use App\Models\WithdrawalSetting;
use App\Services\WithdrawalBalances;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HeaderWithdrawableBalanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_header_shows_platform_total_withdrawable_balance(): void
    {
        Member::query()->create([
            'member_id' => 'STHDR001',
            'sponsor_id' => 'ST666666',
            'sponsor_name' => 'Admin',
            'member_name' => 'Header One',
            'mobile_no' => '1000000001',
            'email' => 'header1@example.test',
            'status' => 'active',
            'roi_wallet_amount' => '10.0000',
            'working_wallet_amount' => '20.0000',
            'activation_wallet_amount' => '5.0000',
        ]);
        Member::query()->create([
            'member_id' => 'STHDR002',
            'sponsor_id' => 'ST666666',
            'sponsor_name' => 'Admin',
            'member_name' => 'Header Two',
            'mobile_no' => '1000000002',
            'email' => 'header2@example.test',
            'status' => 'active',
            'roi_wallet_amount' => '1.2500',
            'working_wallet_amount' => '2.7500',
            'activation_wallet_amount' => '0.0000',
        ]);

        $expected = app(WithdrawalBalances::class)->book()['available'];
        $this->assertSame('39.0000', $expected);

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSeeText('$39.0000')
            ->assertSee('ri-wallet-3-line', false)
            ->assertDontSeeText('Total Balance');
    }

    public function test_member_header_sums_available_withdrawable_wallets_minus_holds(): void
    {
        $member = Member::query()->create([
            'member_id' => 'STHDRMEM',
            'sponsor_id' => 'ST666666',
            'sponsor_name' => 'Admin',
            'member_name' => 'Header Member',
            'mobile_no' => '1000000003',
            'email' => 'headermember@example.test',
            'status' => 'active',
            'roi_wallet_amount' => '12.0000',
            'working_wallet_amount' => '8.0000',
            'activation_wallet_amount' => '3.0000',
        ]);

        WithdrawalSetting::current()->forceFill([
            'eligible_wallets' => ['roi', 'working', 'activation'],
            'submissions_enabled' => false,
        ])->save();

        $total = app(WithdrawalBalances::class)->totalAvailable($member);
        $this->assertSame('23.0000', $total);

        $this->withSession(['member_context_id' => $member->member_id])
            ->get(route('member.dashboard'))
            ->assertOk()
            ->assertSeeText('$23.0000')
            ->assertSee('ri-wallet-3-line', false)
            ->assertDontSeeText('Total Balance');
    }
}
