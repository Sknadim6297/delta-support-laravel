<?php

namespace Tests\Feature;

use App\Models\LevelCommissionTransaction;
use App\Models\Member;
use App\Models\Package;
use App\Models\PackagePurchase;
use App\Models\RoiTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FinancialDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_sees_only_own_wallets_and_empty_history_without_balance_changes(): void
    {
        $member = $this->member('STWALL001', 'ST666666', '1.5000', '2.5000', '3.5000');
        $other = $this->member('STWALL002', 'ST666666', '9.0000', '9.0000', '9.0000');
        $this->roi($other, 'ROI-OTHER-1', '4.0000');

        $this->withSession(['member_context_id' => $member->member_id])
            ->get(route('member.wallets.overview'))
            ->assertOk()
            ->assertSeeText('USDT 1.5000')
            ->assertSeeText('USDT 2.5000')
            ->assertSeeText('USDT 3.5000')
            ->assertDontSeeText('USDT 9.0000')
            ->assertDontSeeText('USD 1.5000');

        $this->withSession(['member_context_id' => $member->member_id])
            ->get(route('member.income.roi'))
            ->assertOk()
            ->assertSeeText('No ROI records yet.')
            ->assertDontSeeText('ROI-OTHER-1');

        $this->withSession(['member_context_id' => $member->member_id])
            ->get(route('member.income.level'))
            ->assertOk()
            ->assertSeeText('No level income records yet.');

        $member->refresh();
        $this->assertSame('1.5000', $member->activation_wallet_amount);
        $this->assertSame('2.5000', $member->working_wallet_amount);
        $this->assertSame('3.5000', $member->roi_wallet_amount);
    }

    public function test_member_roi_history_is_paginated_and_not_labeled_as_a_package_credit(): void
    {
        $member = $this->member('STWALL003', 'ST666666');
        foreach (range(1, 11) as $index) {
            $this->roi($member, 'ROI-PAGE-'.str_pad((string) $index, 2, '0', STR_PAD_LEFT), '0.2500');
        }

        $this->withSession(['member_context_id' => $member->member_id])
            ->get(route('member.income.roi', ['page' => 2]))
            ->assertOk()
            ->assertSeeText('ROI-PAGE-01')
            ->assertSeeText('Not linked to a package')
            ->assertDontSeeText('ROI-PAGE-11');
    }

    public function test_referral_page_counts_one_direct_once_and_hides_direct_balances(): void
    {
        $sponsor = $this->member('STELG001', 'ST666666');
        $direct = $this->member('STELG002', $sponsor->member_id, '0.0000', '0.0000', '88.8800');
        $tourOnly = $this->member('STELG003', $sponsor->member_id);
        $package = $this->package('PKG-ELG', 'tour_roi');
        $tour = $this->package('PKG-TOUR', 'tour');
        $this->purchase($direct, $package, 'approved', 'TXELG0001');
        $this->purchase($direct, $package, 'approved', 'TXELG0002');
        $this->purchase($tourOnly, $tour, 'approved', 'TXELG0003');

        $this->withSession(['member_context_id' => $sponsor->member_id])
            ->get(route('member.eligibility'))
            ->assertOk()
            ->assertSeeText('2')
            ->assertSeeText('1')
            ->assertSeeText('4 of 20')
            ->assertSeeText('4 of 20 levels unlocked')
            ->assertSeeText('STELG002')
            ->assertSeeText('Yes')
            ->assertSeeText('No')
            ->assertDontSeeText('88.8800');
    }

    public function test_zero_qualifying_directs_show_locked_levels(): void
    {
        $member = $this->member('STELG010', 'ST666666');

        $this->withSession(['member_context_id' => $member->member_id])
            ->get(route('member.eligibility'))
            ->assertOk()
            ->assertSeeText('0 of 20')
            ->assertSeeText('No levels unlocked')
            ->assertSeeText('No direct referrals yet.');
    }

    public function test_admin_overview_is_read_only_and_member_cannot_open_it(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $member = $this->member('STADM001', 'ST666666', '1.2500', '2.2500', '3.2500');
        $this->roi($member, 'ROI-ADMIN-1', '3.2500');
        LevelCommissionTransaction::query()->create([
            'reference' => 'LC-ADMIN-1',
            'investment_id' => 'INV-ADMIN',
            'member_id' => $member->member_id,
            'member_name' => $member->member_name,
            'from_member_id' => 'ST000001',
            'from_member_name' => 'Source',
            'level' => 1,
            'business_date' => '2026-02-01',
            'on_amount' => '10.0000',
            'rate_percentage' => '25.000',
            'income_amount' => '2.5000',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.wallets.overview'))
            ->assertOk()
            ->assertSeeText('USDT 3.2500')
            ->assertSeeText('cannot be fully reconciled')
            ->assertDontSeeText('USD 3.2500')
            ->assertDontSeeText('Force Credit');

        $this->actingAs($admin)
            ->get(route('admin.wallets.show', ['wallet' => 'roi', 'q' => 'STADM001']))
            ->assertOk()
            ->assertSeeText('STADM001')
            ->assertSeeText('USDT 3.2500');

        $this->actingAs($admin)
            ->get(route('admin.finance.roi-history'))
            ->assertOk()
            ->assertSeeText('ROI-ADMIN-1')
            ->assertSeeText('Not linked to a package');

        $this->actingAs($admin)
            ->get(route('admin.finance.level-income'))
            ->assertOk()
            ->assertSeeText('LC-ADMIN-1')
            ->assertSeeText('USDT 2.5000');

        $this->actingAs($admin)
            ->get(route('admin.finance.eligibility', ['q' => 'STADM001']))
            ->assertOk()
            ->assertSeeText('No levels unlocked');

        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->get(route('admin.wallets.overview'))
            ->assertForbidden();

        $this->get(route('member.wallets.overview'))->assertForbidden();

        config(['deltasupport.legacy_financial_actions' => false]);
        $this->actingAs($admin)->post(route('admin.investments.store'), [])->assertForbidden();

        $member->refresh();
        $this->assertSame('1.2500', $member->activation_wallet_amount);
        $this->assertSame('2.2500', $member->working_wallet_amount);
        $this->assertSame('3.2500', $member->roi_wallet_amount);
    }

    private function member(string $memberId, string $sponsorId, string $activation = '0.0000', string $working = '0.0000', string $roi = '0.0000'): Member
    {
        return Member::query()->create([
            'member_id' => $memberId,
            'sponsor_id' => $sponsorId,
            'sponsor_name' => 'Sponsor',
            'member_name' => 'Member '.$memberId,
            'mobile_no' => substr(preg_replace('/\D/', '', $memberId).'0000000000', 0, 10),
            'email' => strtolower($memberId).'@example.test',
            'status' => 'active',
            'activation_wallet_amount' => $activation,
            'working_wallet_amount' => $working,
            'roi_wallet_amount' => $roi,
        ]);
    }

    private function roi(Member $member, string $reference, string $amount): void
    {
        RoiTransaction::query()->create([
            'reference' => $reference,
            'investment_id' => 'INV-'.$reference,
            'member_id' => $member->member_id,
            'member_name' => $member->member_name,
            'on_amount' => '100.0000',
            'rate_percentage' => '5.000',
            'income_amount' => $amount,
            'roi_date' => '2026-02-01',
            'status' => 'generated',
        ]);
    }

    private function package(string $code, string $type): Package
    {
        return Package::query()->create([
            'code' => $code,
            'package_type' => $type,
            'name' => $code,
            'price_usd' => '10.0000',
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }

    private function purchase(Member $member, Package $package, string $status, string $paymentReference): void
    {
        PackagePurchase::query()->create([
            'reference' => 'PUR-'.$paymentReference,
            'idempotency_key' => (string) Str::uuid(),
            'member_id' => $member->member_id,
            'package_id' => $package->id,
            'package_code' => $package->code,
            'package_name' => $package->name,
            'package_type' => $package->package_type,
            'price_usd' => '10.0000',
            'network' => 'Configured network',
            'payment_reference' => $paymentReference,
            'status' => $status,
            'submitted_at' => now(),
        ]);
    }
}
