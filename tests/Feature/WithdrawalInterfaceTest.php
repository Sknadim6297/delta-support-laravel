<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\User;
use App\Models\WalletLedgerEntry;
use App\Models\WithdrawalRequest;
use App\Models\WithdrawalSetting;
use App\Models\WithdrawalStatusEvent;
use App\Services\FinancialScheduleRegistrar;
use App\Support\WithdrawalStatus;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class WithdrawalInterfaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_withdrawal_screen_is_read_only_and_isolated(): void
    {
        $owner = $this->member('STWD1001', '4.0000', '5.0000', '6.0000');
        $other = $this->member('STWD1002', '1.0000', '1.0000', '1.0000');
        $own = $this->request($owner, 'WD-OWN-1', WithdrawalStatus::PENDING_REVIEW);
        $foreign = $this->request($other, 'WD-OTHER-1', WithdrawalStatus::PENDING_REVIEW);
        WithdrawalStatusEvent::query()->create([
            'withdrawal_request_id' => $own->id,
            'from_status' => WithdrawalStatus::DRAFT,
            'to_status' => WithdrawalStatus::PENDING_REVIEW,
            'actor' => 'system',
            'reason' => 'Recorded for review',
        ]);

        $this->withSession(['member_context_id' => $owner->member_id])
            ->get(route('member.withdrawals.request'))
            ->assertOk()
            ->assertSeeText('Withdraw USDT')
            ->assertSeeText('USDT 4.0000')
            ->assertSeeText('USDT 5.0000')
            ->assertSeeText('USDT 6.0000')
            ->assertSee('USDT 20.0000 proposed', false)
            ->assertSeeText('Not configured')
            ->assertSeeText('Withdrawal submissions are unavailable.')
            ->assertSee('disabled>Submit Withdrawal Request', false);

        $this->withSession(['member_context_id' => $owner->member_id])
            ->get(route('member.withdrawals.history'))
            ->assertOk()
            ->assertSeeText('WD-OWN-1')
            ->assertDontSeeText('WD-OTHER-1');

        $this->withSession(['member_context_id' => $owner->member_id])
            ->get(route('member.withdrawals.show', $own))
            ->assertOk()
            ->assertSeeText('Recorded for review');

        $this->withSession(['member_context_id' => $owner->member_id])
            ->get(route('member.withdrawals.show', $foreign))
            ->assertNotFound();

        $owner->refresh();
        $this->assertSame('4.0000', $owner->roi_wallet_amount);
        $this->assertSame('5.0000', $owner->working_wallet_amount);
        $this->assertSame('6.0000', $owner->activation_wallet_amount);
        $this->assertSame(0, WalletLedgerEntry::query()->count());
        $this->assertFalse(WithdrawalSetting::current()->submissions_enabled);
    }

    public function test_empty_member_history_and_configured_choices_do_not_invent_a_charge(): void
    {
        $member = $this->member('STWD1003', '0.0000', '0.0000', '0.0000');
        WithdrawalSetting::current()->update([
            'eligible_wallets' => ['roi'],
            'supported_networks' => ['Admin selected network'],
            'charge_type' => null,
        ]);

        $this->withSession(['member_context_id' => $member->member_id])
            ->get(route('member.withdrawals.history'))
            ->assertOk()
            ->assertSeeText('No withdrawal requests yet.');

        $this->withSession(['member_context_id' => $member->member_id])
            ->get(route('member.withdrawals.request'))
            ->assertOk()
            ->assertSeeText('ROI Wallet')
            ->assertSeeText('Admin selected network')
            ->assertSee('value="Not configured"', false);
    }

    public function test_admin_review_pages_count_filter_and_page_real_records_only(): void
    {
        $this->get(route('admin.withdrawals.overview'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->get(route('admin.withdrawals.index'))
            ->assertForbidden();

        $first = $this->member('STWD2001', '0.0000', '0.0000', '0.0000');
        $second = $this->member('STWD2002', '0.0000', '0.0000', '0.0000');
        $pending = $this->request($first, 'WD-PEND-1', WithdrawalStatus::PENDING_REVIEW, 'Network A');
        $paid = $this->request($second, 'WD-PAID-1', WithdrawalStatus::COMPLETED, 'Network B', 'TX-PAID-1');
        $this->request($first, 'WD-DONE-NO-TX', WithdrawalStatus::COMPLETED, 'Network A');
        $this->request($first, 'WD-REJ-1', WithdrawalStatus::REJECTED, 'Network A');
        foreach (range(1, 8) as $number) {
            $this->request($first, 'WD-PAGE-'.$number, WithdrawalStatus::DRAFT, 'Network A');
        }

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->get(route('admin.withdrawals.overview'))
            ->assertOk()
            ->assertSeeText('Total requests')
            ->assertSeeText('Pending Review')
            ->assertSee('>12</p>', false);

        $this->actingAs($admin)->get(route('admin.withdrawals.index', ['member_id' => 'STWD2002']))
            ->assertOk()
            ->assertSeeText('WD-PAID-1')
            ->assertDontSeeText('WD-PEND-1');

        $this->actingAs($admin)->get(route('admin.withdrawals.index', [
            'reference' => 'WD-PEND-1',
            'status' => WithdrawalStatus::PENDING_REVIEW,
            'network' => 'Network A',
            'from_date' => now()->toDateString(),
            'to_date' => now()->toDateString(),
        ]))->assertOk()->assertSeeText('WD-PEND-1')->assertDontSeeText('WD-PAID-1');

        $this->actingAs($admin)->get(route('admin.withdrawals.index', ['page' => 2]))
            ->assertOk()
            ->assertSeeText('WD-PEND-1')
            ->assertDontSeeText('WD-PAGE-8');

        $this->actingAs($admin)->get(route('admin.withdrawals.show', $pending))
            ->assertOk()
            ->assertSeeText('Rejecting this request releases the reservation.')
            ->assertSeeText('No status events recorded.')
            ->assertSeeText('Reject request')
            ->assertDontSeeText('Approve request');

        $this->actingAs($admin)->get(route('admin.withdrawals.payouts'))
            ->assertOk()
            ->assertSeeText('TX-PAID-1')
            ->assertDontSeeText('WD-DONE-NO-TX');

        $this->actingAs($admin)->get(route('admin.withdrawals.payouts', ['page' => 1]))
            ->assertSeeText('WD-PAID-1');

        WithdrawalRequest::query()->delete();
        $this->actingAs($admin)->get(route('admin.withdrawals.payouts'))
            ->assertOk()
            ->assertSeeText('No payouts recorded yet.');
        $this->actingAs($admin)->get(route('admin.withdrawals.index'))
            ->assertOk()
            ->assertSeeText('No withdrawal requests yet.');

        $first->refresh();
        $this->assertSame('0.0000', $first->roi_wallet_amount);
        $this->assertSame(0, WalletLedgerEntry::query()->count());
        $schedule = new Schedule;
        app(FinancialScheduleRegistrar::class)->register($schedule);
        $this->assertSame([], $schedule->events());
        $this->assertNotNull($paid->id);
    }

    private function request(Member $member, string $reference, string $status, ?string $network = null, ?string $txid = null): WithdrawalRequest
    {
        return WithdrawalRequest::query()->create([
            'reference' => $reference,
            'idempotency_key' => (string) Str::uuid(),
            'member_id' => $member->member_id,
            'source_wallet' => 'roi',
            'amount' => '20.0000',
            'denomination' => 'USDT',
            'network' => $network,
            'status' => $status,
            'payout_network' => $txid ? $network : null,
            'payout_txid' => $txid,
        ]);
    }

    private function member(string $memberId, string $roi, string $working, string $activation): Member
    {
        return Member::query()->create([
            'member_id' => $memberId,
            'sponsor_id' => 'ST666666',
            'sponsor_name' => 'Admin',
            'member_name' => 'Withdrawal Member',
            'mobile_no' => '91000'.substr(preg_replace('/\D/', '', $memberId).'00000', 0, 5),
            'email' => strtolower($memberId).'@example.test',
            'status' => 'active',
            'roi_wallet_amount' => $roi,
            'working_wallet_amount' => $working,
            'activation_wallet_amount' => $activation,
        ]);
    }
}
