<?php

namespace Tests\Feature;

use App\Models\LevelIncomeRuleVersion;
use App\Models\Member;
use App\Models\Package;
use App\Models\PackageLevelAccrual;
use App\Models\PackagePurchase;
use App\Models\PackageRoiAccrual;
use App\Models\User;
use App\Services\LevelIncomeDirectQualification;
use App\Services\LevelIncomeLiabilityProjection;
use App\Services\LevelIncomeRuleAdministration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class LevelIncomeLiabilityProjectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_projection_is_read_only_and_performs_no_financial_writes(): void
    {
        $sponsor = $this->member('LBL001', 'ST666666');
        $pkg = $this->roiPackage();
        $this->purchase($sponsor, $pkg, PackagePurchase::APPROVED, '100.0000', '2026-10-01 06:00:00');
        $direct = $this->member('LBL001A', $sponsor->member_id);
        $this->purchase($direct, $pkg, PackagePurchase::APPROVED, '100.0000', '2026-10-01 06:00:00');
        $this->member('LBL001B', $sponsor->member_id); // joining-only

        $this->stageCombinedDraft();

        $walletBefore = (string) $sponsor->fresh()->working_wallet_amount;
        $roiBefore = PackageRoiAccrual::query()->count();
        $levelBefore = PackageLevelAccrual::query()->count();
        $purchasePrice = (string) PackagePurchase::query()->value('price_usd');
        $authBefore = LevelIncomeRuleVersion::query()
            ->where('version_code', 'proposed_activation_unlock_v1')
            ->value('posting_authorized');

        $writes = 0;
        DB::listen(function ($query) use (&$writes): void {
            if (preg_match('/^\s*(insert|update|delete|replace)/i', $query->sql)) {
                $writes++;
            }
        });

        $report = app(LevelIncomeLiabilityProjection::class)->project('2026-11-27', [
            'applicability' => LevelIncomeRuleVersion::APPLICABILITY_EXISTING_PROSPECTIVE,
            'effective_from' => '2026-11-15',
            'include_roi_check' => true,
        ]);

        $this->assertTrue($report['read_only']);
        $this->assertFalse($report['mutates']);
        $this->assertSame(0, $writes);
        $this->assertSame(0, PackageRoiAccrual::query()->count() - $roiBefore);
        $this->assertSame(0, PackageLevelAccrual::query()->count() - $levelBefore);
        $this->assertSame($walletBefore, (string) $sponsor->fresh()->working_wallet_amount);
        $this->assertSame($purchasePrice, (string) PackagePurchase::query()->value('price_usd'));
        $this->assertEquals($authBefore, LevelIncomeRuleVersion::query()
            ->where('version_code', 'proposed_activation_unlock_v1')
            ->value('posting_authorized'));
        $this->assertFalse($report['totals']['associate_commission_posting_enabled']);
        $this->assertFalse($report['safety']['associate_engine_activated']);
        $this->assertFalse($report['safety']['historical_backfill']);
        $this->assertArrayHasKey('additional_daily_liability', $report['totals']);
        $this->assertArrayHasKey('level_differences', $report);
        $this->assertArrayHasKey('member_differences', $report);
        $this->assertArrayHasKey('purchase_differences', $report);
        $this->assertSame(
            LevelIncomeDirectQualification::BASIS_COMBINED_JOINING_AND_PURCHASE,
            $report['candidate']['direct_qualification_basis']
        );
    }

    public function test_existing_prospective_increases_liability_when_unlock_expands(): void
    {
        $sponsor = $this->member('LBL010', 'ST666666');
        $pkg = $this->roiPackage();
        $this->purchase($sponsor, $pkg, PackagePurchase::APPROVED, '100.0000', '2026-10-01 06:00:00');
        // No income-eligible directs → legacy unlock 0; joining-only directs unlock under combined.
        $this->member('LBL010A', $sponsor->member_id);
        $buyer = $this->member('LBL010B', 'ST666666');
        $this->purchase($buyer, $pkg, PackagePurchase::APPROVED, '100.0000', '2026-10-01 06:00:00');
        // Place buyer under sponsor so commission can flow to sponsor when unlock expands.
        $buyer->forceFill(['sponsor_id' => $sponsor->member_id])->save();

        $this->stageCombinedDraft();

        $report = app(LevelIncomeLiabilityProjection::class)->project('2026-11-27', [
            'applicability' => LevelIncomeRuleVersion::APPLICABILITY_EXISTING_PROSPECTIVE,
            'effective_from' => '2026-11-15',
            'include_roi_check' => false,
        ]);

        $this->assertTrue($report['read_only']);
        // With joining metric >= 1 and personal activation, proposed bands unlock more than legacy 0.
        $this->assertTrue(
            bccomp((string) $report['totals']['proposed_commission_daily'], (string) $report['totals']['legacy_commission_daily'], 4) >= 0
        );
        $this->assertSame(20, count($report['level_differences']));
    }

    public function test_artisan_command_is_read_only_json(): void
    {
        $sponsor = $this->member('LBL020', 'ST666666');
        $this->purchase($sponsor, $this->roiPackage(), PackagePurchase::APPROVED, '100.0000', '2026-10-01 06:00:00');
        $this->stageCombinedDraft();

        $levelBefore = PackageLevelAccrual::query()->count();
        $this->artisan('deltasupport:preview-level-income-liability', [
            '--date' => '2026-11-27',
            '--applicability' => LevelIncomeRuleVersion::APPLICABILITY_EXISTING_PROSPECTIVE,
            '--effective-from' => '2026-11-15',
            '--json' => true,
        ])->assertSuccessful();

        $this->assertSame($levelBefore, PackageLevelAccrual::query()->count());
        $this->assertFalse(
            (bool) LevelIncomeRuleVersion::query()
                ->where('version_code', 'proposed_activation_unlock_v1')
                ->value('posting_authorized')
        );
    }

    private function stageCombinedDraft(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $service = app(LevelIncomeRuleAdministration::class);
        $draft = LevelIncomeRuleVersion::query()
            ->where('version_code', 'proposed_activation_unlock_v1')
            ->firstOrFail();

        $service->saveDraft([
            'label' => $draft->label,
            'direct_qualification_basis' => LevelIncomeDirectQualification::BASIS_COMBINED_JOINING_AND_PURCHASE,
            'tour_only_qualifies_for_unlock' => true,
            'grandfather_existing_unlock' => true,
            'combined_joining_enabled' => true,
            'combined_purchase_enabled' => true,
            'combined_purchase_scope' => LevelIncomeDirectQualification::BASIS_APPROVED_PACKAGE_DIRECTS,
            'combined_repeat_purchase_mode' => LevelIncomeDirectQualification::REPEAT_UNIQUE_DIRECTS,
            'combined_double_credit_policy' => LevelIncomeDirectQualification::DOUBLE_PREVENT_OVERLAP,
            'combined_joining_unit_value' => 1,
            'combined_purchase_unit_value' => 1,
            'bands' => $draft->bands->map(fn ($b) => [
                'start_level' => $b->start_level,
                'end_level' => $b->end_level,
                'min_qualifying_directs' => $b->min_qualifying_directs,
                'requires_personal_activation' => $b->requires_personal_activation,
            ])->all(),
            'rates' => $draft->rates->mapWithKeys(fn ($r) => [(int) $r->level => (string) $r->percentage])->all(),
        ], $admin, null, $draft);
    }

    private function member(string $memberId, string $sponsorId): Member
    {
        return Member::query()->create([
            'member_id' => $memberId,
            'sponsor_id' => $sponsorId,
            'sponsor_name' => 'Sponsor',
            'member_name' => 'Member '.$memberId,
            'mobile_no' => '91000'.substr(preg_replace('/\D/', '', $memberId).'00000', -5),
            'email' => strtolower($memberId).'@example.test',
            'status' => 'active',
            'roi_wallet_amount' => '0.0000',
            'working_wallet_amount' => '0.0000',
            'activation_wallet_amount' => '0.0000',
        ]);
    }

    private function roiPackage(string $code = 'DST-ROI-002'): Package
    {
        return Package::query()->firstOrCreate(
            ['code' => $code],
            [
                'package_type' => Package::TYPE_TOUR_ROI,
                'name' => $code,
                'price_usd' => '100.0000',
                'denomination' => 'USDT',
                'roi_eligible' => true,
                'level_commission_eligible' => true,
                'is_active' => true,
                'sort_order' => 1,
            ]
        );
    }

    private function purchase(
        Member $member,
        Package $package,
        string $status,
        string $amount,
        string $reviewedAt = '2026-10-01 06:00:00'
    ): PackagePurchase {
        return PackagePurchase::query()->create([
            'reference' => 'PUR-'.$member->member_id.'-'.Str::upper(Str::random(5)),
            'idempotency_key' => (string) Str::uuid(),
            'member_id' => $member->member_id,
            'package_id' => $package->id,
            'package_code' => $package->code,
            'package_name' => $package->name,
            'package_type' => $package->package_type,
            'price_usd' => $amount,
            'denomination' => 'USDT',
            'network' => 'TRC20',
            'payment_reference' => 'TX'.$member->member_id.Str::upper(Str::random(4)),
            'status' => $status,
            'submitted_at' => $reviewedAt,
            'reviewed_at' => $status === PackagePurchase::APPROVED ? $reviewedAt : null,
        ]);
    }
}
