<?php

namespace Tests\Feature;

use App\Models\ActivationWalletTransaction;
use App\Models\Member;
use App\Models\RoiTransaction;
use App\Models\User;
use App\Services\WalletBalanceReconciliation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialReportingAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_reconciliation_does_not_treat_credit_only_wallets_as_complete_or_change_balances(): void
    {
        $member = $this->member('STAUD001', '4.0000', '5.0000', '6.0000');
        $this->roi($member, 'ROI-AUDIT-1', '6.0000');
        ActivationWalletTransaction::query()->create([
            'member_id' => $member->member_id,
            'member_name' => $member->member_name,
            'amount' => '4.0000',
            'type' => 'credit',
            'reference' => 'AW-AUDIT-1',
        ]);

        $report = app(WalletBalanceReconciliation::class)->inspect($member);

        $this->assertTrue($report['roi']['reconciled']);
        $this->assertSame('0.0000', $report['roi']['variance']);
        $this->assertSame('0.0000', $report['roi']['debits']);
        $this->assertFalse($report['working']['reconciled']);
        $this->assertSame('5.0000', $report['working']['variance']);
        $this->assertTrue($report['activation']['reconciled']);
        $this->assertSame('0.0000', $report['activation']['variance']);
        $this->assertSame([
            'roi' => 0,
            'level_income' => 0,
            'activation' => 0,
        ], app(WalletBalanceReconciliation::class)->duplicateReferenceCounts());

        $member->refresh();
        $this->assertSame('4.0000', $member->activation_wallet_amount);
        $this->assertSame('5.0000', $member->working_wallet_amount);
        $this->assertSame('6.0000', $member->roi_wallet_amount);
    }

    public function test_negative_balance_is_reported_and_left_unchanged(): void
    {
        $member = $this->member('STAUD002', '-1.0000', '0.0000', '0.0000');

        $report = app(WalletBalanceReconciliation::class)->inspect($member);

        $this->assertTrue($report['activation']['negative']);
        $this->assertFalse($report['activation']['reconciled']);
        $this->assertSame(1, app(WalletBalanceReconciliation::class)->negativeBalanceCount());
        $this->assertSame('-1.0000', $member->fresh()->activation_wallet_amount);
    }

    public function test_wallet_pages_keep_usdt_labels_and_package_prices_stay_usd(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $member = $this->member('STAUD003');

        $this->withSession(['member_context_id' => $member->member_id])
            ->get(route('member.wallets.show', ['wallet' => 'roi']))
            ->assertOk()
            ->assertSeeText('USDT 0.0000')
            ->assertSeeText('stored USDT balance')
            ->assertDontSeeText('USD 0.0000');

        $this->actingAs($admin)
            ->get(route('admin.finance.roi-history'))
            ->assertOk()
            ->assertSeeText('ROI History')
            ->assertDontSeeText('Existing investment records only')
            ->assertSeeText('No ROI records found.');

        $this->actingAs($admin)
            ->get(route('admin.packages.index'))
            ->assertOk()
            ->assertSeeText('USDT price');
    }

    public function test_legacy_financial_actions_stay_forbidden_and_reports_stay_authorized(): void
    {
        config(['deltasupport.legacy_financial_actions' => false]);
        $admin = User::factory()->create(['is_admin' => true]);
        $outsider = User::factory()->create(['is_admin' => false]);
        $owner = $this->member('STAUD004', '1.0000', '2.0000', '3.0000');
        $other = $this->member('STAUD005');
        $this->roi($other, 'ROI-HIDDEN-1', '8.0000');

        $this->actingAs($admin)->get(route('admin.activation-wallet.credit-entry'))
            ->assertOk()
            ->assertSeeText('This legacy financial action is disabled');
        $this->actingAs($admin)->get('/admin/level-commission/statement')->assertNotFound();
        $this->actingAs($admin)->get(route('admin.report.roi-report'))
            ->assertOk()
            ->assertSeeText('Total Amount (USDT)');

        $this->actingAs($admin)->post(route('admin.activation-wallet.store-credit-entry'), [])->assertForbidden();
        $this->actingAs($admin)->post(route('admin.activation-wallet.store-debit-entry'), [])->assertForbidden();
        $this->actingAs($admin)->post(route('admin.investments.store'), [])->assertForbidden();
        $this->actingAs($admin)->post('/admin/level-commission/statement/1', ['percentage' => '25'])->assertNotFound();

        $this->withSession(['member_context_id' => $owner->member_id])
            ->post(route('member.investments.store'), [])
            ->assertForbidden();
        $this->withSession(['member_context_id' => $owner->member_id])
            ->get(route('member.reports.roi'))
            ->assertOk()
            ->assertDontSeeText('ROI-HIDDEN-1');
        $this->withSession(['member_context_id' => $owner->member_id])
            ->get(route('member.income.roi', ['q' => 'ROI-HIDDEN-1']))
            ->assertOk()
            ->assertSeeText('No ROI records found.')
            ->assertDontSeeText('ROI-HIDDEN-1');

        $this->actingAs($outsider)->get(route('admin.report.roi-report'))->assertForbidden();
        $this->actingAs($outsider)->get(route('admin.wallets.overview'))->assertForbidden();

        $owner->refresh();
        $this->assertSame('1.0000', $owner->activation_wallet_amount);
        $this->assertSame('2.0000', $owner->working_wallet_amount);
        $this->assertSame('3.0000', $owner->roi_wallet_amount);
        $this->assertSame(0, bccomp((string) \Illuminate\Support\Facades\DB::table('level_commissions')->where('level', 1)->value('percentage'), '1.0000', 4));
        $this->assertSame('25.0000', \App\Models\CommissionPlanLevel::query()->where('level', 1)->value('percentage'));
    }

    private function member(string $memberId, string $activation = '0.0000', string $working = '0.0000', string $roi = '0.0000'): Member
    {
        return Member::query()->create([
            'member_id' => $memberId,
            'sponsor_id' => 'ST666666',
            'sponsor_name' => 'Sponsor',
            'member_name' => 'Member '.$memberId,
            'mobile_no' => substr(preg_replace('/\D/', '', $memberId).'1000000000', 0, 10),
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
}
