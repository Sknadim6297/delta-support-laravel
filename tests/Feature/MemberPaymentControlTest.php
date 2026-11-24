<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\MemberPaymentBlock;
use App\Models\MemberPaymentControl;
use App\Models\PackageIncomeRun;
use App\Models\User;
use App\Services\MemberPaymentControlService;
use App\Services\PackageIncomeAccrual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MemberPaymentControlTest extends TestCase
{
    use RefreshDatabase;

    private function member(string $id = 'ST900001'): Member
    {
        return Member::query()->create([
            'member_id' => $id,
            'sponsor_id' => 'ST666666',
            'sponsor_name' => 'Admin',
            'member_name' => 'Payment Control '.$id,
            'wallet_address' => '0x'.str_pad(substr($id, -6), 40, '0'),
            'activation_wallet_amount' => '0.0000',
            'working_wallet_amount' => '0.0000',
            'roi_wallet_amount' => '0.0000',
            'mobile_no' => '90000'.substr(preg_replace('/\D/', '', $id) ?: '1', -5),
            'email' => strtolower($id).'@local-test.invalid',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);
    }

    public function test_existing_members_default_to_payments_allowed_and_integration_is_off(): void
    {
        $member = $this->member();
        $service = app(MemberPaymentControlService::class);

        $this->assertFalse($service->financialIntegrationAuthorized());
        $this->assertTrue($service->allowsRoi($member->member_id, now('Asia/Kolkata')->toDateString()));
        $this->assertTrue($service->allowsLevel($member->member_id, now('Asia/Kolkata')->toDateString()));
        $this->assertSame('Active', $service->statusBadge($member->member_id)['label']);
    }

    public function test_master_switch_preserves_and_restores_preferences(): void
    {
        config(['deltasupport.member_payment_controls.financial_integration_authorized' => true]);
        $admin = User::factory()->create(['is_admin' => true]);
        $member = $this->member();
        $service = app(MemberPaymentControlService::class);
        $date = $service->suggestEffectiveFrom();

        $service->apply($member->member_id, [
            'master_enabled' => true,
            'roi_preference' => false,
            'level_preference' => true,
            'reason' => 'ROI preference off only',
            'effective_from' => $date,
            'confirm' => true,
        ], $admin);

        $control = MemberPaymentControl::query()->where('member_id', $member->member_id)->firstOrFail();
        $this->assertTrue($control->master_enabled);
        $this->assertFalse($control->roi_preference);
        $this->assertTrue($control->level_preference);
        $this->assertSame(MemberPaymentControl::STATUS_PARTIAL, $control->status);
        $this->assertFalse($service->allowsRoi($member->member_id, $date));
        $this->assertTrue($service->allowsLevel($member->member_id, $date));

        $service->apply($member->member_id, [
            'master_enabled' => false,
            'roi_preference' => false,
            'level_preference' => true,
            'reason' => 'Master stop all',
            'effective_from' => $date,
            'confirm' => true,
        ], $admin);

        $control->refresh();
        $this->assertFalse($control->master_enabled);
        $this->assertFalse($control->roi_preference);
        $this->assertTrue($control->level_preference);
        $this->assertSame(MemberPaymentControl::STATUS_STOPPED, $control->status);
        $this->assertFalse($service->allowsRoi($member->member_id, $date));
        $this->assertFalse($service->allowsLevel($member->member_id, $date));

        $service->apply($member->member_id, [
            'master_enabled' => true,
            'roi_preference' => false,
            'level_preference' => true,
            'reason' => 'Master resume restores prefs',
            'effective_from' => $date,
            'confirm' => true,
        ], $admin);

        $control->refresh();
        $this->assertTrue($control->master_enabled);
        $this->assertFalse($control->roi_preference);
        $this->assertTrue($control->level_preference);
        $this->assertSame(MemberPaymentControl::STATUS_PARTIAL, $control->status);
        $this->assertFalse($service->allowsRoi($member->member_id, $date));
        $this->assertTrue($service->allowsLevel($member->member_id, $date));
    }

    public function test_resume_closes_block_without_backfill_semantics(): void
    {
        config(['deltasupport.member_payment_controls.financial_integration_authorized' => true]);
        $admin = User::factory()->create(['is_admin' => true]);
        $member = $this->member();
        $service = app(MemberPaymentControlService::class);
        $stopDay = $service->suggestEffectiveFrom();
        $resumeDay = \Carbon\CarbonImmutable::parse($stopDay, 'Asia/Kolkata')->addDays(3)->toDateString();

        $service->apply($member->member_id, [
            'master_enabled' => true,
            'roi_preference' => false,
            'level_preference' => true,
            'reason' => 'Stop ROI',
            'effective_from' => $stopDay,
            'confirm' => true,
        ], $admin);

        $mid = \Carbon\CarbonImmutable::parse($stopDay, 'Asia/Kolkata')->addDay()->toDateString();
        $this->assertFalse($service->allowsRoi($member->member_id, $mid));

        $service->apply($member->member_id, [
            'master_enabled' => true,
            'roi_preference' => true,
            'level_preference' => true,
            'reason' => 'Resume ROI',
            'effective_from' => $resumeDay,
            'confirm' => true,
        ], $admin);

        $this->assertFalse($service->allowsRoi($member->member_id, $mid));
        $this->assertTrue($service->allowsRoi($member->member_id, $resumeDay));

        $block = MemberPaymentBlock::query()
            ->where('member_id', $member->member_id)
            ->where('income_type', MemberPaymentBlock::TYPE_ROI)
            ->orderByDesc('id')
            ->first();
        $this->assertNotNull($block);
        $this->assertSame($stopDay, $block->start_date->toDateString());
        $this->assertSame($resumeDay, $block->end_date?->toDateString());
    }

    public function test_unauthorized_integration_ignores_blocks_in_accrual_gate(): void
    {
        config(['deltasupport.member_payment_controls.financial_integration_authorized' => false]);
        $admin = User::factory()->create(['is_admin' => true]);
        $member = $this->member();
        $service = app(MemberPaymentControlService::class);
        $date = $service->suggestEffectiveFrom();

        $service->apply($member->member_id, [
            'master_enabled' => false,
            'roi_preference' => true,
            'level_preference' => true,
            'reason' => 'Configured but unauthorized',
            'effective_from' => $date,
            'confirm' => true,
        ], $admin);

        $this->assertTrue($service->allowsRoi($member->member_id, $date));
        $this->assertTrue($service->allowsLevel($member->member_id, $date));
        $this->assertDatabaseHas('member_payment_blocks', [
            'member_id' => $member->member_id,
            'income_type' => 'roi',
        ]);
    }

    public function test_effective_date_cannot_precede_completed_daily_cycle(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $member = $this->member();
        $service = app(MemberPaymentControlService::class);
        $today = now('Asia/Kolkata')->toDateString();

        PackageIncomeRun::query()->create([
            'business_date' => $today,
            'status' => PackageIncomeRun::SUCCEEDED,
            'credited_roi' => 1,
            'credited_commissions' => 0,
            'message' => 'test',
        ]);

        $this->assertSame(
            \Carbon\CarbonImmutable::parse($today, 'Asia/Kolkata')->addDay()->toDateString(),
            $service->suggestEffectiveFrom()
        );

        $this->expectException(ValidationException::class);
        $service->apply($member->member_id, [
            'master_enabled' => false,
            'roi_preference' => true,
            'level_preference' => true,
            'reason' => 'Too early',
            'effective_from' => $today,
            'confirm' => true,
        ], $admin);
    }

    public function test_admin_ui_requires_auth_and_reason(): void
    {
        $member = $this->member();
        $admin = User::factory()->create(['is_admin' => true]);
        $guest = User::factory()->create(['is_admin' => false]);

        $this->actingAs($guest)
            ->post(route('admin.members.payment-controls', $member->member_id), [
                'master_enabled' => '0',
                'roi_preference' => '1',
                'level_preference' => '1',
                'reason' => 'Nope',
                'effective_from' => now('Asia/Kolkata')->toDateString(),
                'confirm' => '1',
            ])
            ->assertForbidden();

        $this->actingAs($admin)
            ->post(route('admin.members.payment-controls', $member->member_id), [
                'master_enabled' => '0',
                'roi_preference' => '1',
                'level_preference' => '1',
                'effective_from' => now('Asia/Kolkata')->toDateString(),
                'confirm' => '1',
            ])
            ->assertSessionHasErrors('reason');

        $this->actingAs($admin)
            ->get(route('admin.members.update', ['member_id' => $member->member_id]))
            ->assertOk()
            ->assertSeeText('Payment Controls')
            ->assertSeeText('All Income Payment');

        $this->actingAs($admin)
            ->get(route('admin.members.directory', ['payment_status' => 'stopped']))
            ->assertOk()
            ->assertSeeText('Payments');
    }

    public function test_reason_required_on_service_apply(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $member = $this->member();
        $this->expectException(ValidationException::class);
        app(MemberPaymentControlService::class)->apply($member->member_id, [
            'master_enabled' => false,
            'roi_preference' => true,
            'level_preference' => true,
            'reason' => '',
            'effective_from' => now('Asia/Kolkata')->toDateString(),
            'confirm' => true,
        ], $admin);
    }

    public function test_package_income_accrual_resolves_with_payment_control_service(): void
    {
        $this->assertInstanceOf(PackageIncomeAccrual::class, app(PackageIncomeAccrual::class));
        $this->assertFalse(app(MemberPaymentControlService::class)->financialIntegrationAuthorized());
    }
}
