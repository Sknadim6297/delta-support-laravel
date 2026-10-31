<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\User;
use App\Models\WalletLedgerEntry;
use App\Models\WithdrawalRequest;
use App\Models\WithdrawalSetting;
use App\Models\WithdrawalStatusEvent;
use App\Services\FinancialScheduleRegistrar;
use App\Support\MoneyDisplay;
use App\Support\WalletLedgerDirection;
use App\Support\WithdrawalStatus;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class WithdrawalSecurityAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_and_members_cannot_reach_admin_withdrawal_pages_or_submit(): void
    {
        $member = $this->member('STWD3001');
        $own = $this->request($member, 'WD-SEC-1');

        $this->get(route('admin.withdrawals.overview'))->assertRedirect(route('login'));
        $this->get(route('admin.withdrawals.index'))->assertRedirect(route('login'));
        $this->get(route('admin.withdrawals.payouts'))->assertRedirect(route('login'));
        $this->get(route('admin.withdrawals.settings'))->assertRedirect(route('login'));
        $this->put(route('admin.withdrawals.settings.update'), ['minimum_amount' => '20.0000'])->assertRedirect(route('login'));

        $this->get(route('member.withdrawals.request'))->assertForbidden();
        $this->get(route('member.withdrawals.history'))->assertForbidden();
        $this->get(route('member.withdrawals.show', $own))->assertForbidden();

        $this->withSession(['member_context_id' => $member->member_id])
            ->get(route('admin.withdrawals.overview'))
            ->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->get(route('admin.withdrawals.show', $own))
            ->assertForbidden();

        $this->withSession(['member_context_id' => $member->member_id])
            ->post(route('member.withdrawals.request'), ['amount' => '20.0000'])
            ->assertForbidden();

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->post(route('admin.withdrawals.payouts'))->assertStatus(405);
        $this->actingAs($admin)->post(route('admin.withdrawals.index'))->assertStatus(405);
        foreach (['approve', 'reject', 'complete'] as $action) {
            $this->actingAs($admin)->post('/admin/withdrawals/requests/'.$own->id.'/'.$action)->assertForbidden();
        }
        $this->actingAs($admin)->post('/admin/withdrawals/requests/'.$own->id.'/transfer')->assertNotFound();

        $this->assertSame(1, WithdrawalRequest::query()->count());
        $this->assertSame(0, WalletLedgerEntry::query()->count());
        $this->assertFalse(WithdrawalSetting::current()->submissions_enabled);
    }

    public function test_member_queries_stay_scoped_and_invalid_filters_do_not_widen_results(): void
    {
        $owner = $this->member('STWD3002', '3.0000');
        $other = $this->member('STWD3003', '9.0000');
        $this->request($owner, 'WD-OWNER-1');
        $foreign = $this->request($other, 'WD-FOREIGN-1');

        $this->withSession(['member_context_id' => $owner->member_id])
            ->get(route('member.withdrawals.show', $foreign))
            ->assertNotFound();

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)
            ->get(route('admin.withdrawals.index', ['from_date' => '2026-02-31', 'member_id' => 'STWD3002']))
            ->assertOk()
            ->assertSeeText('WD-OWNER-1')
            ->assertDontSeeText('WD-FOREIGN-1');

        $owner->refresh();
        $this->assertSame('3.0000', $owner->roi_wallet_amount);
        $this->assertSame('9.0000', $other->fresh()->roi_wallet_amount);
    }

    public function test_settings_placeholders_secrets_and_enablement_cannot_open_submissions(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->from(route('admin.withdrawals.settings'))
            ->put(route('admin.withdrawals.settings.update'), [
                'minimum_amount' => '20.0000',
                'network' => 'Configured network',
            ])->assertSessionHasErrors('network');

        $this->actingAs($admin)->from(route('admin.withdrawals.settings'))
            ->put(route('admin.withdrawals.settings.update'), [
                'minimum_amount' => '20.0000',
                'network' => 'seed phrase network',
            ])->assertSessionHasErrors('network');

        $settings = WithdrawalSetting::current();
        $settings->submissions_enabled = true;
        $settings->denomination = 'USD';
        $settings->save();
        $settings->refresh();

        $this->assertFalse($settings->submissions_enabled);
        $this->assertFalse($settings->isReady());
        $this->assertSame('USDT', $settings->denomination);
        $this->assertSame('20.0000', $settings->minimum_amount);
        $this->assertNull($settings->supported_networks);
        $this->assertNull($settings->charge_type);
    }

    public function test_ledger_and_status_history_reject_eloquent_changes_without_touching_wallets(): void
    {
        $member = $this->member('STWD3004', '7.2500');
        $request = $this->request($member, 'WD-LEDGER-1');
        $entry = WalletLedgerEntry::query()->create([
            'wallet' => 'roi',
            'direction' => WalletLedgerDirection::HOLD,
            'amount' => '20.0000',
            'denomination' => 'USDT',
            'member_id' => $member->member_id,
            'reference' => 'LEDGER-HOLD-1',
            'idempotency_key' => (string) Str::uuid(),
            'withdrawal_request_id' => $request->id,
            'reason' => 'Audit fixture',
        ]);
        $event = WithdrawalStatusEvent::query()->create([
            'withdrawal_request_id' => $request->id,
            'from_status' => WithdrawalStatus::DRAFT,
            'to_status' => WithdrawalStatus::PENDING_REVIEW,
            'actor' => 'audit',
        ]);

        try {
            $entry->update(['amount' => '1.0000']);
            $this->fail('A ledger row was updated.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Wallet ledger entries are append-only.', $exception->getMessage());
        }
        try {
            $entry->delete();
            $this->fail('A ledger row was deleted.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Wallet ledger entries are append-only.', $exception->getMessage());
        }
        try {
            $event->update(['reason' => 'changed']);
            $this->fail('A status event was updated.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Withdrawal status events are append-only.', $exception->getMessage());
        }

        $this->assertSame('20.0000', WalletLedgerEntry::query()->findOrFail($entry->id)->amount);
        $this->assertNull(WithdrawalStatusEvent::query()->findOrFail($event->id)->reason);
        $member->refresh();
        $this->assertSame('7.2500', $member->roi_wallet_amount);
        $this->assertSame('0.0000', $member->working_wallet_amount);
        $this->assertSame('0.0000', $member->activation_wallet_amount);

        $this->withSession(['member_context_id' => $member->member_id])
            ->get(route('member.withdrawals.request'))
            ->assertOk();
        $this->assertSame(1, WalletLedgerEntry::query()->count());
    }

    public function test_references_idempotency_currency_and_status_stay_consistent(): void
    {
        $member = $this->member('STWD3005');
        $key = (string) Str::uuid();
        $this->request($member, 'WD-DUP-1', $key);

        try {
            $this->request($member, 'WD-DUP-1');
            $this->fail('A duplicate withdrawal reference was stored.');
        } catch (QueryException) {
            $this->assertSame(1, WithdrawalRequest::query()->count());
        }

        try {
            $this->request($member, 'WD-DUP-2', $key);
            $this->fail('A duplicate idempotency key was stored.');
        } catch (QueryException) {
            $this->assertSame(1, WithdrawalRequest::query()->count());
        }

        foreach ([
            ['denomination' => 'USD'],
            ['status' => 'sent'],
            ['payout_txid' => 'TX-NAKED', 'payout_network' => null],
        ] as $override) {
            try {
                $this->request($member, 'WD-BAD-'.Str::random(4), null, $override);
                $this->fail('An inconsistent withdrawal request was stored.');
            } catch (RuntimeException) {
                $this->assertSame(1, WithdrawalRequest::query()->count());
            }
        }

        $stored = WithdrawalRequest::query()->firstOrFail();
        $this->assertSame('USDT', $stored->denomination);
        $this->assertSame(WithdrawalStatus::DRAFT, $stored->status);
        $this->assertFalse($stored->canTransitionTo(WithdrawalStatus::COMPLETED));
        $this->assertTrue($stored->canTransitionTo(WithdrawalStatus::PENDING_REVIEW));
        $this->assertSame('USDT 20.0000', MoneyDisplay::format($stored->amount, $stored->denomination));
        $this->assertSame('USD 1.0000', MoneyDisplay::format('1.0000', 'USD'));
    }

    public function test_legacy_withdrawal_post_scheduler_and_csrf_group_stay_closed(): void
    {
        config(['deltasupport.legacy_financial_actions' => false]);
        $before = DB::table('investment_withdrawals')->count();

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->post(route('admin.investments.investment-withdrawal-store'), [])
            ->assertForbidden();

        $this->assertSame($before, DB::table('investment_withdrawals')->count());
        $this->assertSame(0, WalletLedgerEntry::query()->count());

        $update = Route::getRoutes()->getByName('admin.withdrawals.settings.update');
        $this->assertNotNull($update);
        $this->assertContains('PUT', $update->methods());
        $this->assertContains('auth', $update->gatherMiddleware());
        $this->assertContains('admin', $update->gatherMiddleware());

        $web = app('router')->getMiddlewareGroups()['web'] ?? [];
        $this->assertTrue(collect($web)->contains(fn (mixed $middleware): bool => is_string($middleware) && str_contains($middleware, 'ValidateCsrfToken')));

        $memberRoute = Route::getRoutes()->getByName('member.withdrawals.show');
        $this->assertContains('member.context', $memberRoute->gatherMiddleware());

        $uris = collect(Route::getRoutes())->map(fn ($route) => $route->uri());
        $this->assertFalse($uris->contains(fn (string $uri): bool => str_contains($uri, 'ledger')));
        $this->assertFalse($uris->contains('member/withdrawals/store'));

        $schedule = new Schedule;
        app(FinancialScheduleRegistrar::class)->register($schedule);
        $this->assertSame([], $schedule->events());
    }

    private function request(Member $member, string $reference, ?string $idempotencyKey = null, array $override = []): WithdrawalRequest
    {
        return WithdrawalRequest::query()->create(array_merge([
            'reference' => $reference,
            'idempotency_key' => $idempotencyKey ?: (string) Str::uuid(),
            'member_id' => $member->member_id,
            'source_wallet' => 'roi',
            'amount' => '20.0000',
            'denomination' => 'USDT',
            'status' => WithdrawalStatus::DRAFT,
        ], $override));
    }

    private function member(string $memberId, string $roi = '0.0000'): Member
    {
        return Member::query()->create([
            'member_id' => $memberId,
            'sponsor_id' => 'ST666666',
            'sponsor_name' => 'Admin',
            'member_name' => 'Withdrawal Member',
            'mobile_no' => '92000'.substr(preg_replace('/\D/', '', $memberId).'00000', 0, 5),
            'email' => strtolower($memberId).'@example.test',
            'status' => 'active',
            'roi_wallet_amount' => $roi,
            'working_wallet_amount' => '0.0000',
            'activation_wallet_amount' => '0.0000',
        ]);
    }
}
