<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\Package;
use App\Models\PackageLevelAccrual;
use App\Models\PackagePurchase;
use App\Models\PackageRoiAccrual;
use App\Models\User;
use App\Services\PackageIncomeAccrual;
use App\Services\PackageIncomeCalculationPreview;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PackageIncomeCalculationPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_is_read_only_and_keeps_generation_disabled(): void
    {
        $this->get(route('admin.finance.roi-schedule', ['date' => '2026-11-06']))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->get(route('admin.finance.roi-schedule', ['date' => '2026-11-06']))
            ->assertForbidden();

        $buyer = $this->member('STPVW001', 'ST666666');
        $later = $this->member('STPVW002', 'ST666666');
        $hills = $this->package('DEMO-HILLS', true, true);
        $this->purchase($buyer, $hills, 'approved', '2026-10-27 06:00:00', '99.0000');
        $this->purchase($later, $hills, 'approved', '2026-10-28 06:00:00', '99.0000');
        $writes = 0;
        DB::listen(function ($query) use (&$writes): void {
            if (preg_match('/^\s*(insert|update|delete|replace)/i', $query->sql)) {
                $writes++;
            }
        });

        $preview = app(PackageIncomeCalculationPreview::class);
        $before = $preview->forDate('2026-10-28');
        $on = $preview->forDate('2026-11-06');
        $next = $preview->forDate('2026-11-07');

        $this->assertSame(0, $writes);
        $this->assertSame(0, PackageRoiAccrual::query()->count());
        $this->assertSame(0, PackageLevelAccrual::query()->count());
        $this->assertSame('0.0000', $buyer->fresh()->roi_wallet_amount);
        $this->assertSame('99.0000', PackagePurchase::query()->value('price_usd'));
        $this->assertFalse(config('deltasupport.package_income.enabled'));
        $this->assertFalse(config('deltasupport.wallet_ledger.enabled'));

        $early = $this->only($before, 'STPVW001');
        $this->assertContains('Not yet eligible', $early['labels']);
        $this->assertSame('0.0000', $early['pending_roi']);
        $this->assertSame('0.0000', $early['payable_commission']);
        $this->assertSame('2.4000', $early['theoretical_pool']);

        $due = $this->only($on, 'STPVW001');
        $this->assertContains('Eligible for ROI', $due['labels']);
        $this->assertContains('Blocked by ineligible sponsor', $due['labels']);
        $this->assertNotContains('Eligible for commission', $due['labels']);
        $this->assertSame('2.5000', $due['daily_roi']);
        $this->assertSame('2.5000', $due['pending_roi']);
        $this->assertSame('0.0000', $due['payable_commission']);
        $this->assertSame('2.4000', $due['theoretical_pool']);
        $this->assertSame('99.0000', $due['purchase_amount']);
        $this->assertSame('USDT', $due['currency']);
        $this->assertSame('2026-11-06', $due['first_roi_date']);
        $this->assertSame('2027-12-10', $due['last_commission_date']);
        $this->assertSame('2029-08-01', $due['last_roi_date']);
        $this->assertSame([], $on['recipients']);
        $this->assertContains('Not yet eligible', $this->only($on, 'STPVW002')['labels']);

        $second = $this->only($next, 'STPVW002');
        $this->assertContains('Eligible for ROI', $second['labels']);
        $this->assertContains('Blocked by ineligible sponsor', $second['labels']);
        $this->assertSame('2.5000', $second['pending_roi']);
        $this->assertSame('0.0000', $second['payable_commission']);
        $this->assertSame('2026-11-07', $second['first_roi_date']);
        $stillDue = $this->only($next, 'STPVW001');
        $this->assertContains('Eligible for ROI', $stillDue['labels']);
        $this->assertSame('2.5000', $stillDue['pending_roi']);
    }

    public function test_locked_levels_do_not_use_the_theoretical_pool_as_payable(): void
    {
        $top = $this->member('STL005', 'ST666666');
        $fourth = $this->member('STL004', $top->member_id);
        $third = $this->member('STL003', $fourth->member_id);
        $second = $this->member('STL002', $third->member_id);
        $first = $this->member('STL001', $second->member_id);
        $source = $this->member('STL000', $first->member_id);
        $hills = $this->package('DEMO-HILLS', true, true);
        foreach ([$top, $fourth, $third, $second, $first, $source] as $member) {
            $this->purchase($member, $hills, 'approved', '2026-10-01 06:00:00', '99.0000');
        }

        $row = $this->only(app(PackageIncomeCalculationPreview::class)->forDate('2026-10-11'), 'STL000');
        $byLevel = collect($row['levels'])->keyBy('level');

        $this->assertSame('1.2000', $row['payable_commission']);
        $this->assertSame('2.4000', $row['theoretical_pool']);
        $this->assertNotSame($row['theoretical_pool'], $row['payable_commission']);
        $this->assertSame('0.6000', $byLevel['1']['payable_amount']);
        $this->assertSame('0.3000', $byLevel['2']['payable_amount']);
        $this->assertSame('0.1800', $byLevel['3']['payable_amount']);
        $this->assertSame('0.1200', $byLevel['4']['payable_amount']);
        $this->assertSame('Blocked by locked levels', $byLevel['5']['status']);
        $this->assertSame('0.0000', $byLevel['5']['payable_amount']);
        $this->assertSame('0.0600', $byLevel['5']['rate_amount']);
        $this->assertSame(0, PackageLevelAccrual::query()->count());
        $this->assertSame('0.0000', $source->fresh()->working_wallet_amount);
    }

    public function test_date_boundaries_missing_package_and_existing_credit_do_not_write(): void
    {
        $sponsor = $this->member('STBND001', 'ST666666');
        $buyer = $this->member('STBND002', $sponsor->member_id);
        $coast = $this->package('DEMO-COAST', true, true);
        $this->purchase($sponsor, $coast, 'approved', '2026-10-01 06:00:00', '100.0000');
        $this->purchase($buyer, $coast, 'approved', '2026-10-01 06:00:00', '49.0000');
        $this->purchase($this->member('STBND003', 'ST666666'), $this->package('NOT-A-PLAN', true, true), 'approved', '2026-10-01 06:00:00', '10.0000');
        $this->purchase($this->member('STBND004', 'ST666666'), $coast, 'pending', '2026-10-01 06:00:00', '100.0000');
        $this->member('STBND005', 'ST666666');

        $first = CarbonImmutable::parse('2026-10-11');
        $lastCommission = $first->addDays(399)->toDateString();
        $afterCommission = $first->addDays(400)->toDateString();
        $afterRoi = $first->addDays(800)->toDateString();
        $preview = app(PackageIncomeCalculationPreview::class);

        $open = $this->only($preview->forDate($first->toDateString()), 'STBND002');
        $this->assertContains('Eligible for ROI', $open['labels']);
        $this->assertContains('Eligible for commission', $open['labels']);
        $this->assertSame('0.2500', $open['pending_roi']);
        $this->assertSame('0.0600', $open['payable_commission']);
        $this->assertSame('0.2400', $open['theoretical_pool']);
        $this->assertSame('49.0000', $open['purchase_amount']);
        $this->assertSame('0.0600', $preview->forDate($first->toDateString())['recipients'][0]['amount']);

        $commissionEnded = $this->only($preview->forDate($afterCommission), 'STBND002');
        $this->assertContains('Eligible for ROI', $commissionEnded['labels']);
        $this->assertContains('Past the payout duration', $commissionEnded['labels']);
        $this->assertNotContains('Eligible for commission', $commissionEnded['labels']);
        $this->assertSame('0.0000', $commissionEnded['payable_commission']);
        $this->assertSame('0.2500', $commissionEnded['pending_roi']);
        $this->assertSame($lastCommission, $open['last_commission_date']);

        $roiEnded = $this->only($preview->forDate($afterRoi), 'STBND002');
        $this->assertContains('Past the payout duration', $roiEnded['labels']);
        $this->assertNotContains('Eligible for ROI', $roiEnded['labels']);
        $this->assertSame('0.0000', $roiEnded['pending_roi']);

        $invalidPackage = $this->only($preview->forDate($first->toDateString()), 'STBND003');
        $this->assertContains('Blocked by missing package', $invalidPackage['labels']);
        $this->assertNotContains('Not yet eligible', $invalidPackage['labels']);
        $this->assertSame('0.0000', $invalidPackage['pending_roi']);
        $invalidEarly = $this->only($preview->forDate('2026-10-01'), 'STBND003');
        $this->assertSame(['Blocked by missing package'], $invalidEarly['labels']);

        $unpaid = $this->only($preview->forDate($first->toDateString()), 'STBND004');
        $this->assertContains('Not yet eligible', $unpaid['labels']);
        $this->assertSame('0.0000', $unpaid['pending_roi']);

        $none = $this->only($preview->forDate($first->toDateString()), 'STBND005');
        $this->assertSame(['Blocked by missing package'], $none['labels']);

        $invalid = $preview->forDate('2026-13-40');
        $this->assertFalse($invalid['valid']);
        $this->assertSame([], $invalid['rows']);

        config(['deltasupport.package_income.enabled' => true]);
        app(PackageIncomeAccrual::class)->accrue($first->toDateString());
        $credited = $this->only($preview->forDate($first->toDateString()), 'STBND002');
        $this->assertContains('Already credited', $credited['labels']);
        $this->assertSame('0.0000', $credited['pending_roi']);
        $this->assertSame('0.0000', $credited['pending_commission']);
        $this->assertSame(2, PackageRoiAccrual::query()->count());
        $this->assertSame(1, PackageLevelAccrual::query()->count());
        $this->assertSame('0.2500', $buyer->fresh()->roi_wallet_amount);
        $this->assertSame('0.0600', $sponsor->fresh()->working_wallet_amount);
        $preview->forDate($first->toDateString());
        $this->assertSame(2, PackageRoiAccrual::query()->count());
        $this->assertSame(1, PackageLevelAccrual::query()->count());
    }

    public function test_income_control_preview_page_rejects_a_bad_date_and_has_no_enable_control(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get(route('admin.finance.roi-schedule', ['date' => '2026-11-06']))
            ->assertOk()
            ->assertSeeText('Main database calculation preview')
            ->assertSeeText('These figures are not stored credits and are not income history.')
            ->assertSeeText('Scheduler health')
            ->assertSeeText('Historical backfill is off.')
            ->assertSeeText('Wallet debit ledger')
            ->assertSeeText('No recipient is payable on this date.')
            ->assertDontSeeText('Enable generation');

        $this->actingAs($admin)->get(route('admin.finance.roi-schedule', ['date' => 'not-a-date']))
            ->assertOk()
            ->assertSeeText('No preview was calculated.');

        $this->assertFalse(config('deltasupport.package_income.enabled'));
    }

    /**
     * @param  array<string, mixed>  $preview
     * @return array<string, mixed>
     */
    private function only(array $preview, string $memberId): array
    {
        $rows = array_values(array_filter(
            $preview['rows'],
            fn (array $row): bool => $row['member_id'] === $memberId && ($memberId === 'STBND005' || $row['package_code'] !== 'None')
        ));
        if ($memberId !== 'STBND005') {
            $rows = array_values(array_filter($rows, fn (array $row): bool => $row['package_code'] !== 'None'));
        }
        $this->assertCount(1, $rows, $memberId);

        return $rows[0];
    }

    private function member(string $memberId, string $sponsorId): Member
    {
        return Member::query()->create([
            'member_id' => $memberId,
            'sponsor_id' => $sponsorId,
            'sponsor_name' => 'Sponsor',
            'member_name' => 'Member '.$memberId,
            'mobile_no' => str_pad((string) random_int(1000000000, 1999999999), 10, '0', STR_PAD_LEFT),
            'email' => strtolower($memberId).'@example.test',
            'status' => 'active',
            'roi_wallet_amount' => '0.0000',
            'working_wallet_amount' => '0.0000',
            'activation_wallet_amount' => '0.0000',
        ]);
    }

    private function package(string $code, bool $roi, bool $level): Package
    {
        return Package::query()->create([
            'code' => $code,
            'package_type' => $roi || $level ? Package::TYPE_TOUR_ROI : Package::TYPE_TOUR,
            'name' => $code,
            'price_usd' => '1000.0000',
            'denomination' => 'USDT',
            'roi_eligible' => $roi,
            'level_commission_eligible' => $level,
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }

    private function purchase(Member $member, Package $package, string $status, string $reviewedAt, string $price): PackagePurchase
    {
        return PackagePurchase::query()->create([
            'reference' => 'PUR-'.$member->member_id.'-'.Str::upper(Str::random(4)),
            'idempotency_key' => (string) Str::uuid(),
            'member_id' => $member->member_id,
            'package_id' => $package->id,
            'package_code' => $package->code,
            'package_name' => $package->name,
            'package_type' => $package->package_type,
            'price_usd' => $price,
            'denomination' => $package->denomination,
            'network' => 'TRC20',
            'payment_reference' => 'TX'.$member->member_id,
            'status' => $status,
            'submitted_at' => $reviewedAt,
            'reviewed_at' => $status === 'approved' ? $reviewedAt : null,
        ]);
    }
}
