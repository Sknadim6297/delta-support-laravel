<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\Package;
use App\Models\PackagePurchase;
use App\Models\User;
use App\Services\LocalTestMarker;
use App\Services\Rewards\TeamBusinessCalculationService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class MemberBusinessSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_lifetime_business_uses_eligible_approved_usdt_snapshots_once(): void
    {
        $root = $this->member('STBUS001', 'ST666666');
        $direct = $this->member('STBUS002', $root->member_id);
        $directTwo = $this->member('STBUS003', $root->member_id);
        $grandchild = $this->member('STBUS004', $direct->member_id);
        $localTest = $this->member('STBUS005', $root->member_id, 'buyer@local-test.invalid');
        $testReference = $this->member('STBUS006', $root->member_id);
        $unrelated = $this->member('STBUS007', 'ST666666');

        $eligible = $this->package('BIZ-ELIGIBLE', Package::TYPE_TOUR_ROI);
        $tourOnly = $this->package('BIZ-TOUR', Package::TYPE_TOUR);
        $verification = $this->package('VERIFY-1', Package::TYPE_TOUR_ROI);

        $this->purchase($root, $eligible, PackagePurchase::APPROVED, '2.0000');
        $this->purchase($root, $tourOnly, PackagePurchase::APPROVED, '200.0000');
        $this->purchase($direct, $eligible, PackagePurchase::APPROVED, '3.0000');
        $this->purchase($direct, $eligible, PackagePurchase::APPROVED, '4.0000');
        $this->purchase($directTwo, $eligible, PackagePurchase::APPROVED, '5.0000');
        $this->purchase($grandchild, $eligible, PackagePurchase::APPROVED, '11.0000');
        $this->purchase($localTest, $eligible, PackagePurchase::APPROVED, '500.0000');
        $this->purchase($testReference, $eligible, PackagePurchase::APPROVED, '600.0000', 'USDT', LocalTestMarker::PAYMENT_PREFIX.'STBUS006');
        $this->purchase($directTwo, $eligible, PackagePurchase::PENDING, '700.0000');
        $this->purchase($directTwo, $eligible, PackagePurchase::REJECTED, '800.0000');
        $this->purchase($directTwo, $eligible, PackagePurchase::APPROVED, '900.0000', 'USD');
        $this->purchase($directTwo, $verification, PackagePurchase::APPROVED, '1000.0000');
        $this->purchase($unrelated, $eligible, PackagePurchase::APPROVED, '2000.0000');

        $business = app(TeamBusinessCalculationService::class);
        $summaries = $business->businessForMembers([
            $root->member_id,
            $direct->member_id,
            $root->member_id,
        ]);

        $this->assertSame('12.0000', $summaries[$root->member_id]['first_level_business']);
        $this->assertSame('25.0000', $summaries[$root->member_id]['total_team_business']);
        $this->assertSame('11.0000', $summaries[$direct->member_id]['first_level_business']);
        $this->assertSame('18.0000', $summaries[$direct->member_id]['total_team_business']);

        $again = $business->businessForMembers([$root->member_id]);
        $this->assertSame('25.0000', $again[$root->member_id]['total_team_business']);
        $this->assertSame([], $business->businessForMembers([]));
    }

    public function test_member_dashboard_admin_report_and_directory_show_business_with_access_controls(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $root = $this->member('STBUI001', 'ST666666');
        $direct = $this->member('STBUI002', $root->member_id);
        $grandchild = $this->member('STBUI003', $direct->member_id);
        $other = $this->member('STBUI004', 'ST666666');
        $package = $this->package('BIZ-UI', Package::TYPE_TOUR_ROI);
        $this->purchase($root, $package, PackagePurchase::APPROVED, '2.0000');
        $this->purchase($direct, $package, PackagePurchase::APPROVED, '3.0000');
        $this->purchase($grandchild, $package, PackagePurchase::APPROVED, '5.0000');
        $this->purchase($other, $package, PackagePurchase::APPROVED, '100.0000');

        $this->withSession(['member_context_id' => $root->member_id])
            ->get(route('member.dashboard'))
            ->assertOk()
            ->assertSeeText('First Level Business')
            ->assertSeeText('Total Team Business')
            ->assertSeeText('USDT 3.0000')
            ->assertSeeText('USDT 10.0000')
            ->assertDontSeeText('USDT 100.0000');

        $this->actingAs($admin)
            ->get(route('admin.catalog-reports.members.show', ['member' => $root->member_id]))
            ->assertOk()
            ->assertSeeText('First Level Business')
            ->assertSeeText('Total Team Business')
            ->assertSeeText('USDT 3.0000')
            ->assertSeeText('USDT 10.0000');

        $this->actingAs($admin)
            ->get(route('admin.members.directory'))
            ->assertOk()
            ->assertSeeText('First Level Business')
            ->assertSeeText('Total Team Business')
            ->assertSeeText('USDT 3.0000')
            ->assertSeeText('USDT 10.0000')
            ->assertSeeText('USDT 5.0000')
            ->assertSeeText('USDT 8.0000');

        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->get(route('admin.catalog-reports.members.show', ['member' => $root->member_id]))
            ->assertForbidden();
    }

    public function test_directory_business_uses_one_batched_purchase_aggregate(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $root = $this->member('STBATCH001', 'ST666666');
        $package = $this->package('BIZ-BATCH', Package::TYPE_TOUR_ROI);
        for ($number = 2; $number <= 31; $number++) {
            $this->member('STBATCH'.str_pad((string) $number, 3, '0', STR_PAD_LEFT), $root->member_id);
        }
        $this->purchase($root, $package, PackagePurchase::APPROVED, '1.0000');

        $businessAggregates = 0;
        DB::listen(function (QueryExecuted $query) use (&$businessAggregates): void {
            $sql = strtolower($query->sql);
            if (str_contains($sql, 'sum(price_usd)') && str_contains($sql, 'package_purchases')) {
                $businessAggregates++;
            }
        });

        $this->actingAs($admin)->get(route('admin.members.directory'))->assertOk();

        $this->assertSame(1, $businessAggregates);
    }

    private function member(string $memberId, string $sponsorId, string $email = ''): Member
    {
        return Member::query()->create([
            'member_id' => $memberId,
            'sponsor_id' => $sponsorId,
            'sponsor_name' => 'Sponsor',
            'member_name' => 'Member '.$memberId,
            'mobile_no' => '90000'.substr($memberId, -5),
            'email' => $email !== '' ? $email : strtolower($memberId).'@example.test',
            'status' => 'active',
        ]);
    }

    private function package(string $code, string $type): Package
    {
        return Package::query()->create([
            'code' => $code,
            'package_type' => $type,
            'name' => $code,
            'price_usd' => '1.0000',
            'denomination' => 'USDT',
            'roi_eligible' => $type !== Package::TYPE_TOUR,
            'level_commission_eligible' => $type !== Package::TYPE_TOUR,
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }

    private function purchase(Member $member, Package $package, string $status, string $amount, string $denomination = 'USDT', string $paymentReference = ''): PackagePurchase
    {
        return PackagePurchase::query()->create([
            'reference' => 'PUR-'.$member->member_id.'-'.Str::upper(Str::random(6)),
            'idempotency_key' => (string) Str::uuid(),
            'member_id' => $member->member_id,
            'package_id' => $package->id,
            'package_code' => $package->code,
            'package_name' => $package->name,
            'package_type' => $package->package_type,
            'price_usd' => $amount,
            'denomination' => $denomination,
            'network' => 'USDT',
            'payment_reference' => $paymentReference !== '' ? $paymentReference : 'PAY-'.Str::upper(Str::random(10)),
            'status' => $status,
            'submitted_at' => '2020-01-01 00:00:00',
            'reviewed_at' => $status === PackagePurchase::APPROVED ? '2020-01-02 00:00:00' : null,
        ]);
    }
}
