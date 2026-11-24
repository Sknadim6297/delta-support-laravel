<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\Package;
use App\Models\PackagePurchase;
use App\Models\User;
use App\Models\WalletLedgerEntry;
use App\Services\AssociateCommissionCalculator;
use App\Services\AssociateCommissionStructure;
use App\Services\PackageIncomePolicy;
use App\Services\ReferralLevelEligibility;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AssociateCommissionDryRunTest extends TestCase
{
    use RefreshDatabase;

    private AssociateCommissionCalculator $calculator;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'deltasupport.associate_commission.posting_enabled' => false,
            'deltasupport.associate_commission.snapshot_on_approval' => false,
            'deltasupport.package_income.enabled' => false,
            'deltasupport.wallet_ledger.enabled' => false,
        ]);
        $this->calculator = app(AssociateCommissionCalculator::class);
        $this->seedCatalog();
    }

    public function test_rank_chart_and_flags_remain_exact_and_inactive(): void
    {
        $structure = app(AssociateCommissionStructure::class);
        $this->assertSame([], $structure->integrityErrors());
        $this->assertFalse($structure->postingEnabled());
        $this->assertFalse($structure->snapshotOnApprovalEnabled());
        $this->assertFalse($structure->accrualUsesAssociatePostingPath());

        foreach (AssociateCommissionStructure::TIERS as $tier => $def) {
            $sum = '0.0000';
            foreach ($def['ranks'] as $amount) {
                $sum = bcadd($sum, $amount, 4);
            }
            $this->assertSame($def['daily_total'], $sum, $tier);
            $this->assertSame($def['duration_total'], bcmul($sum, '400', 4), $tier);
            $this->assertCount(20, $def['ranks']);
        }

        $eligibility = app(ReferralLevelEligibility::class);
        $this->assertSame(0, $eligibility->levelsForCount(0));
        $this->assertSame(4, $eligibility->levelsForCount(1));
        $this->assertSame(8, $eligibility->levelsForCount(2));
        $this->assertSame(12, $eligibility->levelsForCount(3));
        $this->assertSame(16, $eligibility->levelsForCount(4));
        $this->assertSame(20, $eligibility->levelsForCount(5));
    }

    public function test_silver_four_unlocked_levels_dry_run(): void
    {
        [$buyer, $purchase] = $this->chainPurchase('DST-TOUR-001', 4, 1);
        $date = $this->firstDay($purchase);
        $before = WalletLedgerEntry::query()->count();

        $result = $this->calculator->previewForPurchase($purchase, $date);

        $this->assertSame('dry_run_complete', $result['status']);
        $this->assertSame('silver', $result['tier_code']);
        $this->assertFalse($result['mutates']);
        $this->assertSame(0, $result['ledger_mutations']);
        $eligible = collect($result['lines'])->where('eligible', true);
        $this->assertCount(4, $eligible);
        $this->assertSame('0.0300', $eligible->firstWhere('rank', 1)['amount']);
        $this->assertSame('0.0060', $eligible->firstWhere('rank', 4)['amount']);
        $this->assertSame('0.0600', $result['eligible_total']);
        $this->assertSame($before, WalletLedgerEntry::query()->count());
        $this->assertSame('0.0000', $buyer->fresh()->working_wallet_amount);
    }

    public function test_gold_eight_unlocked_levels_dry_run(): void
    {
        [, $purchase] = $this->chainPurchase('DST-ROI-002', 8, 2);
        $result = $this->calculator->previewForPurchase($purchase, $this->firstDay($purchase));

        $this->assertSame('gold', $result['tier_code']);
        $eligible = collect($result['lines'])->where('eligible', true);
        $this->assertCount(8, $eligible);
        $this->assertSame('0.0600', $eligible->firstWhere('rank', 1)['amount']);
        $this->assertSame('0.0060', $eligible->firstWhere('rank', 8)['amount']);
        $this->assertNotNull($result['legacy_overlap']);
        $this->assertSame(0, $result['ledger_mutations']);
    }

    public function test_premium_twelve_unlocked_levels_dry_run(): void
    {
        [, $purchase] = $this->chainPurchase('DST-TOUR-003', 12, 3);
        $result = $this->calculator->previewForPurchase($purchase, $this->firstDay($purchase));

        $this->assertSame('premium', $result['tier_code']);
        $eligible = collect($result['lines'])->where('eligible', true);
        $this->assertCount(12, $eligible);
        $this->assertSame('1.2000', $result['daily_total']);
        $this->assertSame('0.3000', $eligible->firstWhere('rank', 1)['amount']);
        $this->assertSame('2.4000', app(PackageIncomePolicy::class)->definition('DST-TOUR-003')['commission_pool']);
        $this->assertNotNull($result['legacy_overlap']);
        $this->assertSame(0, $result['wallet_mutations']);
    }

    public function test_vip_twenty_unlocked_levels_dry_run(): void
    {
        [, $purchase] = $this->chainPurchase('DST-TOUR-004', 20, 5);
        $result = $this->calculator->previewForPurchase($purchase, $this->firstDay($purchase));

        $this->assertSame('vip', $result['tier_code']);
        $eligible = collect($result['lines'])->where('eligible', true);
        $this->assertCount(20, $eligible);
        $this->assertSame('2.4000', $result['eligible_total']);
        $this->assertSame('0.0000', $result['unallocated_pool']);
        $this->assertSame('0.6000', $eligible->firstWhere('rank', 1)['amount']);
        $this->assertSame('0.1800', $eligible->firstWhere('rank', 20)['amount']);
        $this->assertNull(app(PackageIncomePolicy::class)->definition('DST-TOUR-004'));
    }

    public function test_unqualified_missing_inactive_cycle_and_cancel_scenarios(): void
    {
        $admin = 'ST666666';
        $this->member($admin, $admin);

        // Unqualified upline: sponsor has no qualifying purchase.
        $unqual = $this->member($this->id('UQ'), $admin);
        $buyer = $this->member($this->id('BQ'), $unqual->member_id);
        $purchase = $this->purchase($buyer, Package::query()->where('code', 'DST-ROI-002')->firstOrFail(), '2026-10-01 06:00:00');
        $unqualResult = $this->calculator->previewForPurchase($purchase, $this->firstDay($purchase));
        $line1 = collect($unqualResult['lines'])->firstWhere('rank', 1);
        $this->assertFalse($line1['eligible']);
        $this->assertStringContainsString('no qualifying purchase', $line1['skip_reason']);

        // Missing sponsor.
        $orphan = $this->member($this->id('OR'), 'MISSING-SPONSOR');
        $orphanPurchase = $this->purchase($orphan, Package::query()->where('code', 'DST-TOUR-001')->firstOrFail(), '2026-10-01 06:00:00');
        $missing = $this->calculator->previewForPurchase($orphanPurchase, $this->firstDay($orphanPurchase));
        $this->assertFalse(collect($missing['lines'])->firstWhere('rank', 1)['eligible']);
        $this->assertStringContainsString('no member record', collect($missing['lines'])->firstWhere('rank', 1)['skip_reason']);

        // Inactive/unqualified recipient already covered; cancelled purchase.
        $cancelled = $this->purchase($buyer, Package::query()->where('code', 'DST-TOUR-004')->firstOrFail(), '2026-10-01 06:00:00', PackagePurchase::REJECTED);
        $blocked = $this->calculator->previewForPurchase($cancelled, '2026-10-11');
        $this->assertSame('blocked', $blocked['status']);
        $this->assertStringContainsString('not approved', $blocked['block_reason']);

        // Duplicate/circular chain.
        $a = $this->member($this->id('CA'), $admin);
        $b = $this->member($this->id('CB'), $a->member_id);
        $a->update(['sponsor_id' => $b->member_id]);
        $qualify = Package::query()->where('code', 'DST-ROI-002')->firstOrFail();
        $this->purchase($a, $qualify, '2026-08-01 06:00:00');
        $this->purchase($b, $qualify, '2026-08-01 06:00:00');
        $this->giveQualifyingDirects($a, 5);
        $this->giveQualifyingDirects($b, 5);
        $cyclePurchase = $this->purchase($b, Package::query()->where('code', 'DST-TOUR-004')->firstOrFail(), '2026-10-01 06:00:00');
        $cycle = $this->calculator->previewForPurchase($cyclePurchase, $this->firstDay($cyclePurchase));
        $this->assertTrue(collect($cycle['lines'])->contains(fn (array $line): bool => str_contains((string) ($line['skip_reason'] ?? ''), 'Circular') || str_contains((string) ($line['skip_reason'] ?? ''), 'duplicate')));
        $this->assertSame(0, $cycle['ledger_mutations']);
    }

    public function test_window_boundaries_idempotency_keys_and_repeated_dry_runs(): void
    {
        [, $purchase] = $this->chainPurchase('DST-ROI-002', 4, 1);
        $policy = app(PackageIncomePolicy::class);
        $first = $this->firstDay($purchase);
        $last = $policy->lastAccrualDate($first, 400);
        $day401 = $last->addDay();

        $day1 = $this->calculator->previewForPurchase($purchase, $first);
        $final = $this->calculator->previewForPurchase($purchase, $last);
        $after = $this->calculator->previewForPurchase($purchase, $day401);

        $this->assertSame('dry_run_complete', $day1['status']);
        $this->assertSame('dry_run_complete', $final['status']);
        $this->assertSame('outside_window', $after['status']);
        $this->assertStringContainsString('after final accrual', $after['block_reason']);

        $key1 = collect($day1['lines'])->firstWhere('rank', 1)['idempotency_key'];
        $again = $this->calculator->previewForPurchase($purchase, $first);
        $this->assertSame($key1, collect($again['lines'])->firstWhere('rank', 1)['idempotency_key']);
        $this->assertSame($day1['eligible_total'], $again['eligible_total']);
        $this->assertSame(0, WalletLedgerEntry::query()->count());
    }

    public function test_multiple_purchases_and_legacy_overlap_marked_not_posted(): void
    {
        [$buyer, $first] = $this->chainPurchase('DST-ROI-002', 4, 1);
        $second = $this->purchase($buyer, Package::query()->where('code', 'DST-ROI-002')->firstOrFail(), '2026-10-05 06:00:00');

        $a = $this->calculator->previewForPurchase($first, $this->firstDay($first));
        $b = $this->calculator->previewForPurchase($second, $this->firstDay($second));
        $this->assertNotSame($a['purchase_id'], $b['purchase_id']);
        $this->assertNotSame(
            collect($a['lines'])->firstWhere('rank', 1)['idempotency_key'],
            collect($b['lines'])->firstWhere('rank', 1)['idempotency_key']
        );
        $this->assertNotNull($a['legacy_overlap']);
        $this->assertSame('possible_double_payment_if_both_engines_active', $a['legacy_overlap']['risk']);
        $this->assertNotEmpty($a['unresolved']);
        $this->assertSame(0, $a['ledger_mutations'] + $b['ledger_mutations']);
    }

    public function test_version_change_warning_and_admin_commission_page_shows_engine(): void
    {
        [, $purchase] = $this->chainPurchase('DST-TOUR-001', 4, 1);
        $result = $this->calculator->simulate([
            'name' => 'config_version_probe',
            'purchase' => $purchase,
            'business_date' => $this->firstDay($purchase),
            'expected_outcome' => 'dry_run',
        ]);
        $this->assertSame('associate_v1', $result['version_code']);
        $this->assertSame(0, $result['ledger_mutations']);

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)
            ->get(route('admin.finance.commission-settings', ['tab' => 'unlock']))
            ->assertOk()
            ->assertSeeText('dry_run_only')
            ->assertSeeText('5 or more');

        $this->actingAs($admin)
            ->get(route('admin.finance.commission-settings', ['tab' => 'history']))
            ->assertOk()
            ->assertSeeText('Associate Commission Structure')
            ->assertSeeText('0.0300')
            ->assertSeeText('0.6000');
    }

    public function test_failed_transaction_path_never_opens_when_posting_disabled(): void
    {
        config(['deltasupport.associate_commission.posting_enabled' => true]);
        [, $purchase] = $this->chainPurchase('DST-TOUR-004', 4, 1);
        $result = $this->calculator->previewForPurchase($purchase, $this->firstDay($purchase));
        $this->assertTrue($result['posting_enabled']);
        $this->assertFalse($result['mutates']);
        $this->assertSame(0, $result['ledger_mutations']);
        $this->assertNotEmpty($result['warnings']);
        config(['deltasupport.associate_commission.posting_enabled' => false]);
    }

    /**
     * @return array{0: Member, 1: PackagePurchase}
     */
    private function chainPurchase(string $packageCode, int $depth, int $qualifyingDirectsPerSponsor): array
    {
        $admin = 'ST666666';
        if (! Member::query()->where('member_id', $admin)->exists()) {
            $this->member($admin, $admin);
        }

        $sponsorId = $admin;
        $qualifyPackage = Package::query()->where('code', 'DST-ROI-002')->firstOrFail();
        for ($i = $depth; $i >= 1; $i--) {
            $sponsor = $this->member($this->id('S'.$i), $sponsorId);
            // Recipient must hold a qualifying purchase (legacy recipientEligible rule).
            $this->purchase($sponsor, $qualifyPackage, '2026-08-01 06:00:00');
            $this->giveQualifyingDirects($sponsor, $qualifyingDirectsPerSponsor);
            $sponsorId = $sponsor->member_id;
        }

        $buyer = $this->member($this->id('BUY'), $sponsorId);
        $purchase = $this->purchase($buyer, Package::query()->where('code', $packageCode)->firstOrFail(), '2026-10-01 06:00:00');

        return [$buyer, $purchase];
    }

    private function giveQualifyingDirects(Member $sponsor, int $count): void
    {
        $package = Package::query()->where('code', 'DST-ROI-002')->firstOrFail();
        for ($i = 0; $i < $count; $i++) {
            $direct = $this->member($this->id('QD'), $sponsor->member_id);
            $this->purchase($direct, $package, '2026-09-01 06:00:00');
        }
    }

    private function firstDay(PackagePurchase $purchase): CarbonImmutable
    {
        return app(PackageIncomePolicy::class)->firstAccrualDate($purchase);
    }

    private function seedCatalog(): void
    {
        foreach ([
            ['DST-TOUR-001', 'SILVER', '50.0000', Package::TYPE_TOUR, false],
            ['DST-ROI-002', 'GOLD', '100.0000', Package::TYPE_TOUR_ROI, true],
            ['DST-TOUR-003', 'PREMIUM', '500.0000', Package::TYPE_PREMIUM, true],
            ['DST-TOUR-004', 'VIP', '1000.0000', Package::TYPE_PREMIUM, true],
        ] as [$code, $name, $price, $type, $income]) {
            Package::query()->create([
                'code' => $code,
                'name' => $name,
                'price_usd' => $price,
                'denomination' => 'USDT',
                'package_type' => $type,
                'roi_eligible' => $income,
                'level_commission_eligible' => $income,
                'is_active' => true,
                'sort_order' => 10,
            ]);
        }
    }

    private function member(string $memberId, string $sponsorId): Member
    {
        return Member::query()->create([
            'member_id' => $memberId,
            'sponsor_id' => $sponsorId,
            'sponsor_name' => 'Sponsor',
            'member_name' => 'Member '.$memberId,
            'mobile_no' => str_pad((string) random_int(1000000000, 1999999999), 10, '0'),
            'email' => strtolower($memberId).'@example.test',
            'status' => 'active',
            'roi_wallet_amount' => '0.0000',
            'working_wallet_amount' => '0.0000',
            'activation_wallet_amount' => '0.0000',
        ]);
    }

    private function purchase(Member $member, Package $package, string $reviewedAt, string $status = PackagePurchase::APPROVED): PackagePurchase
    {
        return PackagePurchase::query()->create([
            'reference' => 'PUR-'.$member->member_id.'-'.Str::upper(Str::random(4)),
            'idempotency_key' => (string) Str::uuid(),
            'member_id' => $member->member_id,
            'package_id' => $package->id,
            'package_code' => $package->code,
            'package_name' => $package->name,
            'package_type' => $package->package_type,
            'price_usd' => $package->price_usd,
            'denomination' => $package->denomination,
            'network' => 'TRC20',
            'payment_reference' => 'TX'.$member->member_id.Str::upper(Str::random(3)),
            'status' => $status,
            'submitted_at' => $reviewedAt,
            'reviewed_at' => $status === PackagePurchase::APPROVED ? $reviewedAt : null,
        ]);
    }

    private function id(string $prefix): string
    {
        $this->seq++;

        return 'ST'.$prefix.str_pad((string) $this->seq, 4, '0', STR_PAD_LEFT);
    }
}
