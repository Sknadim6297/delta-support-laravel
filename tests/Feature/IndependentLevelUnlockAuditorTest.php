<?php

namespace Tests\Feature;

use App\Models\LevelIncomeRuleVersion;
use App\Models\Member;
use App\Models\Package;
use App\Models\PackagePurchase;
use App\Services\IndependentLevelUnlockAuditor;
use App\Services\LevelIncomeRuleAdministration;
use App\Services\LevelIncomeUnlockGrandfathering;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class IndependentLevelUnlockAuditorTest extends TestCase
{
    use RefreshDatabase;

    public function test_option_b_formula_and_pending_exclusion_match_runtime(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-28 00:05:00', 'Asia/Kolkata'));

        $admin = User::factory()->create(['is_admin' => true]);
        $service = app(LevelIncomeRuleAdministration::class);
        $draft = $service->cloneAsDraft(
            LevelIncomeRuleVersion::query()->where('version_code', 'proposed_activation_unlock_v1')->firstOrFail(),
            $admin
        );
        $draft->forceFill([
            'grandfather_existing_unlock' => true,
            'direct_qualification_basis' => 'combined_joining_and_purchase',
            'combined_joining_enabled' => true,
            'combined_purchase_enabled' => true,
            'combined_purchase_scope' => 'personal_approved_purchases',
            'combined_repeat_purchase_mode' => 'each_approved_purchase',
            'combined_double_credit_policy' => 'allow_sum',
            'tour_only_qualifies_for_unlock' => true,
        ])->save();

        $published = $service->publish($draft, $admin, [
            'applicability' => LevelIncomeRuleVersion::APPLICABILITY_EXISTING_PROSPECTIVE,
            'effective_from' => '2026-11-28',
        ]);
        app(LevelIncomeUnlockGrandfathering::class)->captureBaseline($published->fresh(), $admin, '2026-11-28');
        $service->authorizePosting($published->fresh(), $admin, true, null, true);

        $sponsor = $this->member('IND001', 'ST666666');
        $this->purchase($sponsor, $this->package('IND-ROI'), PackagePurchase::APPROVED);
        $this->purchase($sponsor, $this->package('IND-PEND'), PackagePurchase::PENDING);
        $this->member('IND001A', $sponsor->member_id);

        $auditor = app(IndependentLevelUnlockAuditor::class);
        $row = $auditor->verifyMember($sponsor->member_id, $published->fresh(['bands']), '2026-11-28');

        $this->assertSame(1, $row['joining']);
        $this->assertSame(1, $row['approved_purchases']); // pending excluded
        $this->assertSame(1, $row['pending_purchases']);
        $this->assertTrue($row['activated']);
        $this->assertSame(8, $row['expected_level']); // min(20, 4*(1+1))
        $this->assertSame($row['protected_level'], $row['runtime_level']);
        $this->assertTrue($row['pass']);

        Carbon::setTestNow();
    }

    public function test_command_requires_existing_rule_version(): void
    {
        $this->artisan('deltasupport:verify-all-members-level-unlock', [
            '--rule-version' => 'does_not_exist_v9',
            '--business-date' => '2026-11-28',
        ])->assertFailed();
    }

    private function member(string $memberId, string $sponsorId): Member
    {
        return Member::query()->create([
            'member_id' => $memberId,
            'sponsor_id' => $sponsorId,
            'sponsor_name' => 'Sponsor',
            'member_name' => 'Member '.$memberId,
            'mobile_no' => substr(preg_replace('/\D/', '', $memberId).'0000000000', 0, 10),
            'email' => strtolower($memberId).'@example.test',
            'status' => 'active',
            'roi_wallet_amount' => '0.0000',
            'working_wallet_amount' => '0.0000',
            'activation_wallet_amount' => '0.0000',
        ]);
    }

    private function package(string $code): Package
    {
        return Package::query()->create([
            'code' => $code,
            'package_type' => Package::TYPE_ROI,
            'name' => 'Test '.$code,
            'price_usd' => '100.0000',
            'denomination' => 'USDT',
            'roi_eligible' => true,
            'level_commission_eligible' => true,
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }

    private function purchase(Member $member, Package $package, string $status): PackagePurchase
    {
        return PackagePurchase::query()->create([
            'reference' => 'PUR-'.$member->member_id.'-'.$package->code,
            'idempotency_key' => (string) Str::uuid(),
            'member_id' => $member->member_id,
            'package_id' => $package->id,
            'package_code' => $package->code,
            'package_name' => $package->name,
            'package_type' => $package->package_type,
            'price_usd' => '100.0000',
            'denomination' => 'USDT',
            'network' => 'TRC20',
            'payment_reference' => 'TX'.$member->member_id.$package->code,
            'status' => $status,
            'submitted_at' => '2026-11-01 06:00:00',
            'reviewed_at' => $status === PackagePurchase::APPROVED ? '2026-11-01 06:00:00' : null,
        ]);
    }
}
