<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\Package;
use App\Models\PackageLevelAccrual;
use App\Models\PackagePurchase;
use App\Models\PackageRoiAccrual;
use App\Models\WalletLedgerEntry;
use App\Services\MemberRegistrationService;
use App\Services\PackageIncomeAccrual;
use App\Services\PackageIncomeAdministration;
use App\Services\PackageIncomeCalculationPreview;
use App\Services\SponsorChain;
use App\Services\WalletLedgerPoster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SponsorChainTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_root_is_terminal_and_is_not_reported_as_a_missing_member(): void
    {
        $this->member('STROOT01', 'ST666666');

        $report = app(PackageIncomeAdministration::class)->report();
        $sponsor = collect($report['checklist'])->firstWhere('label', 'Sponsor records');

        $this->assertSame('ready', $sponsor['status']);
        $this->assertStringContainsString('ST666666', $sponsor['detail']);
        $this->assertSame(0, Member::query()->where('member_id', 'ST666666')->count());
        $this->assertFalse(config('deltasupport.package_income.enabled'));
    }

    public function test_registration_under_the_root_creates_no_root_member_and_a_cycle_is_rejected(): void
    {
        $result = app(MemberRegistrationService::class)->register([
            'member_name' => 'Root Child',
            'email' => 'root-child@example.test',
            'mobile_no' => '9000000101',
            'sponsor_id' => 'ST666666',
            'password' => 'secret12',
        ], [
            'allow_admin_sponsor' => true,
        ]);

        $this->assertSame('ST666666', $result['member']->sponsor_id);
        $this->assertSame(0, Member::query()->where('member_id', 'ST666666')->count());

        $first = $this->member('STCYC001', 'STCYC002');
        $this->member('STCYC002', 'STCYC001');

        try {
            app(SponsorChain::class)->assertChainEnds($first->member_id);
            $this->fail('A circular sponsor chain was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('sponsor_id', $exception->errors());
        }

        $this->assertSame(0, Member::query()->where('member_id', 'ST666666')->count());
    }

    public function test_a_member_sponsor_is_paid_and_the_root_is_not_given_that_commission(): void
    {
        $sponsor = $this->member('STPAY001', 'ST666666');
        $buyer = $this->member('STPAY002', $sponsor->member_id);
        $hills = $this->package();
        $this->purchase($sponsor, $hills, '1000.0000');
        $this->purchase($buyer, $hills, '99.0000');

        $preview = app(PackageIncomeCalculationPreview::class)->forDate('2026-10-11');
        $row = collect($preview['rows'])->firstWhere('member_id', 'STPAY002');

        $this->assertSame('0.6000', $row['payable_commission']);
        $this->assertSame('2.4000', $row['theoretical_pool']);
        $this->assertSame('STPAY001', $preview['recipients'][0]['member_id']);
        $this->assertSame('0.6000', $preview['recipients'][0]['amount']);
        $this->assertNotContains('ST666666', array_column($preview['recipients'], 'member_id'));

        config(['deltasupport.package_income.enabled' => true]);
        app(PackageIncomeAccrual::class)->accrue('2026-10-11');
        app(PackageIncomeAccrual::class)->accrue('2026-10-11');

        $this->assertSame('0.6000', $sponsor->fresh()->working_wallet_amount);
        $this->assertSame('0.0000', Member::query()->where('member_id', 'ST666666')->value('working_wallet_amount') ?? '0.0000');
        $this->assertSame(2, PackageRoiAccrual::query()->count());
        $this->assertSame(1, PackageLevelAccrual::query()->count());
        $this->assertSame(3, WalletLedgerEntry::query()->count());
        $this->assertSame('2.5000', $buyer->fresh()->roi_wallet_amount);
        $this->assertSame('99.0000', PackagePurchase::query()->where('member_id', $buyer->member_id)->value('price_usd'));
        $this->assertFalse(config('deltasupport.wallet_ledger.enabled'));
    }

    public function test_a_repeated_debit_does_not_move_the_balance_twice(): void
    {
        config(['deltasupport.wallet_ledger.enabled' => true]);
        $member = $this->member('STDBT001', 'ST666666');
        $member->forceFill(['roi_wallet_amount' => '2.5000'])->save();
        $poster = app(WalletLedgerPoster::class);
        $key = (string) Str::uuid();

        $poster->debit($member->member_id, 'roi', '1.0000', 'ROI-DEBIT-1', $key, 'authorized debit');
        $poster->debit($member->member_id, 'roi', '1.0000', 'ROI-DEBIT-1', $key, 'authorized debit');

        $this->assertSame('1.5000', $member->fresh()->roi_wallet_amount);
        $this->assertSame(1, WalletLedgerEntry::query()->where('direction', 'debit')->count());
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

    private function package(): Package
    {
        return Package::query()->create([
            'code' => 'DEMO-HILLS',
            'package_type' => Package::TYPE_TOUR_ROI,
            'name' => 'Hill Station Retreat',
            'price_usd' => '1000.0000',
            'denomination' => 'USDT',
            'roi_eligible' => true,
            'level_commission_eligible' => true,
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }

    private function purchase(Member $member, Package $package, string $price): PackagePurchase
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
            'denomination' => 'USDT',
            'network' => 'TRC20',
            'payment_reference' => 'TX'.$member->member_id,
            'status' => 'approved',
            'submitted_at' => '2026-10-01 06:00:00',
            'reviewed_at' => '2026-10-01 06:00:00',
        ]);
    }
}
