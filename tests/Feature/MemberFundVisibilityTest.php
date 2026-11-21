<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\RoiTransaction;
use App\Models\User;
use App\Models\WalletLedgerEntry;
use App\Models\WithdrawalRequest;
use App\Services\MemberFundSummary;
use App\Support\WalletLedgerDirection;
use App\Support\WithdrawalStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class MemberFundVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_four_decimal_wallet_snapshot_separates_stored_reserved_available_and_paid(): void
    {
        $member = $this->member();
        $member->forceFill([
            'roi_wallet_amount' => '100.1234',
            'working_wallet_amount' => '50.5000',
            'activation_wallet_amount' => '25.0000',
        ])->save();

        WithdrawalRequest::query()->create([
            'reference' => 'WD-FUND-ROI',
            'idempotency_key' => (string) Str::uuid(),
            'member_id' => $member->member_id,
            'source_wallet' => 'roi',
            'amount' => '10.1234',
            'charge_amount' => '0.1012',
            'net_amount' => '10.0222',
            'denomination' => 'USDT',
            'network' => 'TRC20',
            'receiving_address' => 'public-address',
            'status' => WithdrawalStatus::PENDING_REVIEW,
        ]);
        WithdrawalRequest::query()->create([
            'reference' => 'WD-FUND-WORKING',
            'idempotency_key' => (string) Str::uuid(),
            'member_id' => $member->member_id,
            'source_wallet' => 'working',
            'amount' => '5.2500',
            'charge_amount' => '0.0525',
            'net_amount' => '5.1975',
            'denomination' => 'USDT',
            'network' => 'TRC20',
            'receiving_address' => 'public-address',
            'status' => WithdrawalStatus::COMPLETED,
        ]);

        $summary = app(MemberFundSummary::class)->forMember($member->fresh());

        $this->assertSame('175.6234', $summary['total']['stored']);
        $this->assertSame('10.1234', $summary['total']['reserved']);
        $this->assertSame('165.5000', $summary['total']['available']);
        $this->assertSame('5.2500', $summary['total']['paid']);
        $this->assertSame('90.0000', $summary['wallets']['roi']['available']);
        $this->assertSame('50.5000', $summary['wallets']['working']['available']);
        $this->assertSame('25.0000', $summary['wallets']['activation']['stored']);
    }

    public function test_admin_report_update_lookup_and_member_panel_show_read_only_funds(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $member = $this->member();
        $member->forceFill([
            'roi_wallet_amount' => '12.3400',
            'working_wallet_amount' => '7.6600',
            'activation_wallet_amount' => '1.0000',
        ])->save();

        WalletLedgerEntry::query()->create([
            'wallet' => 'roi',
            'direction' => WalletLedgerDirection::CREDIT,
            'amount' => '12.3400',
            'opening_balance' => '0.0000',
            'closing_balance' => '12.3400',
            'denomination' => 'USDT',
            'member_id' => $member->member_id,
            'reference' => 'LEDGER-FUND-1',
            'idempotency_key' => (string) Str::uuid(),
            'actor' => 'system',
            'reason' => 'Test ledger credit',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.catalog-reports.members.show', ['member' => $member->member_id]))
            ->assertOk()
            ->assertSeeText('Wallet and fund summary')
            ->assertSeeText('ROI Wallet')
            ->assertSeeText('Working Wallet')
            ->assertSeeText('Activation Wallet')
            ->assertSeeText('USDT 21.0000')
            ->assertSeeText('LEDGER-FUND-1');

        $this->actingAs($admin)
            ->get(route('admin.members.update', ['member_id' => $member->member_id]))
            ->assertOk()
            ->assertSeeText('Wallet and fund summary')
            ->assertSeeText('USDT 12.3400');

        $this->actingAs($admin)
            ->getJson(route('admin.members.fetch-details', ['member_id' => $member->member_id]))
            ->assertOk()
            ->assertJsonPath('fund_summary.total.stored', '21.0000')
            ->assertJsonPath('fund_summary.wallets.roi.stored', '12.3400')
            ->assertJsonPath('wallet_transactions.0.reference', 'LEDGER-FUND-1');

        $this->actingAs($admin)
            ->get(route('admin.member-panel', ['member_id' => $member->member_id]))
            ->assertOk()
            ->assertSeeText('Wallet and fund summary')
            ->assertSeeText('USDT 21.0000');

        $memberUser = User::factory()->create(['is_admin' => false]);
        $this->actingAs($memberUser)
            ->get(route('admin.catalog-reports.members.show', ['member' => $member->member_id]))
            ->assertForbidden();
    }

    public function test_zero_balance_and_empty_history_are_displayed_without_invented_totals(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $member = $this->member();

        $this->actingAs($admin)
            ->get(route('admin.catalog-reports.members.show', ['member' => $member->member_id]))
            ->assertOk()
            ->assertSeeText('USDT 0.0000')
            ->assertSeeText('No wallet ledger transactions recorded for this member.');
    }

    public function test_member_fund_summary_uses_bounded_queries_for_one_member(): void
    {
        $member = $this->member();
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = strtolower($query->sql);
        });

        app(MemberFundSummary::class)->forMember($member);

        $this->assertLessThanOrEqual(35, count($queries));
    }

    private function member(): Member
    {
        return Member::query()->create([
            'member_id' => 'STFUND'.strtoupper(Str::random(4)),
            'sponsor_id' => 'ST666666',
            'sponsor_name' => 'Admin',
            'member_name' => 'Fund Test Member',
            'mobile_no' => '9000000000',
            'email' => strtolower('STFUND'.Str::random(6).'@example.test'),
            'status' => 'active',
            'roi_wallet_amount' => '0.0000',
            'working_wallet_amount' => '0.0000',
            'activation_wallet_amount' => '0.0000',
        ]);
    }
}
