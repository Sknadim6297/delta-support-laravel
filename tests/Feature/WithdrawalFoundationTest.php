<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\User;
use App\Models\WalletLedgerEntry;
use App\Models\WithdrawalRequest;
use App\Models\WithdrawalSetting;
use App\Services\FinancialScheduleRegistrar;
use App\Support\WalletLedgerDirection;
use App\Support\WithdrawalStatus;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class WithdrawalFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_withdrawal_settings_are_admin_only_and_stay_disabled(): void
    {
        $this->get(route('admin.withdrawals.settings'))->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->put(route('admin.withdrawals.settings.update'), ['minimum_amount' => '20.0000'])
            ->assertForbidden();

        $member = $this->member('STWD0001');
        $member->forceFill([
            'roi_wallet_amount' => '8.0000',
            'working_wallet_amount' => '9.0000',
            'activation_wallet_amount' => '10.0000',
        ])->save();

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get(route('admin.withdrawals.settings'))
            ->assertOk()
            ->assertSeeText('Withdrawal Settings')
            ->assertSeeText('Withdrawal Overview')
            ->assertSeeText('Withdrawal Requests')
            ->assertSeeText('Payout History')
            ->assertSeeText('Submissions stay disabled.')
            ->assertSeeText('20.0000')
            ->assertSee('value="USDT"', false)
            ->assertSee('name="submissions_enabled"', false)
            ->assertDontSee('admin/withdrawals/approve', false);

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->put(route('admin.withdrawals.settings.update'), [
                'minimum_amount' => '25.5000',
                'networks' => "Admin selected network\nAdmin selected network",
                'address_validation_policy' => 'Compare the public address with the network format supplied by the administrator.',
                'member_cancellation_policy' => 'Cancellation remains unset until the client confirms it.',
            ])->assertRedirect(route('admin.withdrawals.settings'));

        $settings = WithdrawalSetting::current()->fresh();
        $this->assertSame('25.5000', $settings->minimum_amount);
        $this->assertSame('USDT', $settings->denomination);
        $this->assertFalse($settings->submissions_enabled);
        $this->assertNull($settings->charge_type);
        $this->assertNull($settings->maximum_amount);
        $this->assertSame(['Admin selected network'], $settings->supported_networks);
        $this->assertSame(0, WalletLedgerEntry::query()->count());
        $this->assertSame(0, WithdrawalRequest::query()->count());

        $member->refresh();
        $this->assertSame('8.0000', $member->roi_wallet_amount);
        $this->assertSame('9.0000', $member->working_wallet_amount);
        $this->assertSame('10.0000', $member->activation_wallet_amount);
    }

    public function test_draft_settings_reject_invalid_amounts_and_secrets(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->from(route('admin.withdrawals.settings'))
            ->put(route('admin.withdrawals.settings.update'), [
                'minimum_amount' => '0',
            ])->assertSessionHasErrors('minimum_amount');

        $this->actingAs($admin)->from(route('admin.withdrawals.settings'))
            ->put(route('admin.withdrawals.settings.update'), [
                'minimum_amount' => '20.0000',
                'maximum_amount' => '10.0000',
            ])->assertSessionHasErrors('maximum_amount');

        $this->actingAs($admin)->from(route('admin.withdrawals.settings'))
            ->put(route('admin.withdrawals.settings.update'), [
                'minimum_amount' => '20.0000',
                'address_validation_policy' => 'store the wallet private key',
            ])->assertSessionHasErrors('address_validation_policy');

        $this->assertFalse(WithdrawalSetting::current()->fresh()->submissions_enabled);
    }

    public function test_incomplete_configuration_cannot_enable_submissions(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->from(route('admin.withdrawals.settings'))
            ->put(route('admin.withdrawals.settings.update'), [
                'minimum_amount' => '20.0000',
                'submissions_enabled' => '1',
            ])->assertRedirect(route('admin.withdrawals.settings'))
            ->assertSessionHasErrors(['submissions_enabled', 'daily_limit', 'supported_networks']);

        $settings = WithdrawalSetting::current()->fresh();
        $this->assertFalse($settings->submissions_enabled);
        $this->assertFalse($settings->isReady());
        $this->assertSame('20.0000', $settings->minimum_amount);
    }

    public function test_request_reference_and_payout_txid_are_unique_and_statuses_are_defined(): void
    {
        $member = $this->member('STWD0002');
        $this->request($member, 'WD-UNIQUE-1', 'TX-100');

        try {
            $this->request($member, 'WD-UNIQUE-1', null);
            $this->fail('A duplicate withdrawal reference was stored.');
        } catch (QueryException) {
            $this->assertSame(1, WithdrawalRequest::query()->count());
        }

        try {
            $this->request($member, 'WD-UNIQUE-2', 'TX-100');
            $this->fail('A payout transaction ID was reused on the same network.');
        } catch (QueryException) {
            $this->assertSame(1, WithdrawalRequest::query()->count());
        }

        $this->request($member, 'WD-UNIQUE-3', null);
        $this->request($member, 'WD-UNIQUE-4', null);
        $this->assertSame(3, WithdrawalRequest::query()->count());
        $this->assertSame('USDT', WithdrawalRequest::query()->where('reference', 'WD-UNIQUE-3')->value('denomination'));

        $this->assertTrue(WithdrawalStatus::canTransition(WithdrawalStatus::DRAFT, WithdrawalStatus::PENDING_REVIEW));
        $this->assertTrue(WithdrawalStatus::canTransition(WithdrawalStatus::PROCESSING, WithdrawalStatus::COMPLETED));
        $this->assertFalse(WithdrawalStatus::canTransition(WithdrawalStatus::DRAFT, WithdrawalStatus::COMPLETED));
        $this->assertFalse(WithdrawalStatus::canTransition(WithdrawalStatus::PENDING_REVIEW, WithdrawalStatus::COMPLETED));
        $this->assertSame('Pending Review', WithdrawalStatus::label(WithdrawalStatus::PENDING_REVIEW));
        $this->assertSame(WalletLedgerDirection::HOLD, 'hold');
    }

    public function test_there_is_no_payout_route_and_the_scheduler_is_empty(): void
    {
        $names = collect(Route::getRoutes())->map(fn ($route) => $route->getName())->filter()->all();
        $this->assertContains('admin.withdrawals.settings', $names);
        $this->assertContains('admin.withdrawals.settings.update', $names);
        $this->assertNotContains('admin.withdrawals.approve', $names);
        $this->assertNotContains('admin.withdrawals.payout', $names);
        $this->assertNotContains('member.withdrawals.store', $names);
        $uris = array_values(array_unique(array_filter(
            collect(Route::getRoutes())->map(fn ($route) => $route->uri())->all(),
            fn (string $uri): bool => str_starts_with($uri, 'admin/withdrawals')
        )));
        sort($uris);
        $this->assertSame([
            'admin/withdrawals',
            'admin/withdrawals/payouts',
            'admin/withdrawals/requests',
            'admin/withdrawals/requests/{withdrawalRequest}',
            'admin/withdrawals/settings',
        ], $uris);
        $this->assertSame(
            ['PUT'],
            collect(Route::getRoutes())->filter(fn ($route) => str_starts_with($route->uri(), 'admin/withdrawals') || str_starts_with($route->uri(), 'member/withdrawals'))
                ->flatMap(fn ($route) => $route->methods())
                ->reject(fn (string $method) => in_array($method, ['GET', 'HEAD'], true))
                ->unique()
                ->values()
                ->all()
        );

        $schedule = new Schedule;
        app(FinancialScheduleRegistrar::class)->register($schedule);
        $this->assertSame([], $schedule->events());

        $this->withSession(['member_context_id' => $this->member('STWD0005')->member_id])
            ->get(route('member.coming-soon', ['feature' => 'request-withdrawal']))
            ->assertOk()
            ->assertSeeText('Coming Soon');
        $this->get(route('member.coming-soon', ['feature' => 'withdrawal-history']))
            ->assertOk()
            ->assertSeeText('Coming Soon');
    }

    private function request(Member $member, string $reference, ?string $txid): WithdrawalRequest
    {
        return WithdrawalRequest::query()->create([
            'reference' => $reference,
            'idempotency_key' => (string) Str::uuid(),
            'member_id' => $member->member_id,
            'source_wallet' => 'roi',
            'amount' => '20.0000',
            'denomination' => 'USDT',
            'status' => WithdrawalStatus::DRAFT,
            'payout_network' => $txid === null ? null : 'Admin selected network',
            'payout_txid' => $txid,
        ]);
    }

    private function member(string $memberId): Member
    {
        return Member::query()->create([
            'member_id' => $memberId,
            'sponsor_id' => 'ST666666',
            'sponsor_name' => 'Admin',
            'member_name' => 'Withdrawal Member',
            'mobile_no' => '90000'.substr(preg_replace('/\D/', '', $memberId).'00000', 0, 5),
            'email' => strtolower($memberId).'@example.test',
            'status' => 'active',
        ]);
    }
}
