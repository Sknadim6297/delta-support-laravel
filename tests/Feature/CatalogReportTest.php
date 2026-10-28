<?php

namespace Tests\Feature;

use App\Models\ActivationWalletTransaction;
use App\Models\AdminActivityLog;
use App\Models\LevelCommissionTransaction;
use App\Models\Member;
use App\Models\Package;
use App\Models\PackagePurchase;
use App\Models\RoiTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
use ZipArchive;

class CatalogReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_are_admin_only_and_exports_are_refused_to_other_roles(): void
    {
        $this->get(route('admin.catalog-reports.show', ['report' => 'members']))->assertRedirect(route('login'));
        $this->get(route('admin.catalog-reports.export', ['report' => 'members', 'format' => 'csv', 'scope' => 'full']))
            ->assertRedirect(route('login'));

        $member = User::factory()->create(['is_admin' => false]);
        $this->actingAs($member)->get(route('admin.catalog-reports.show', ['report' => 'payments']))->assertForbidden();
        $this->actingAs($member)->get(route('admin.catalog-reports.export', [
            'report' => 'payments',
            'format' => 'pdf',
            'scope' => 'filtered',
        ]))->assertForbidden();
    }

    public function test_member_package_and_payment_reports_show_stored_rows_and_separate_currencies(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'name' => 'Reviewer']);
        $older = $this->member('STREP001', '=1+1');
        $newer = $this->member('STREP002', 'Newer Member');
        $older->forceFill(['created_at' => '2026-10-01 04:30:00', 'updated_at' => '2026-10-01 04:30:00'])->save();
        $newer->forceFill(['created_at' => '2026-10-20 04:30:00', 'updated_at' => '2026-10-20 04:30:00'])->save();
        $usd = $this->package('VERIFY-1', 'Verification Package', 'USD', '11.2500');
        $usdt = $this->package('DEMO-HILLS', 'Hill Station Retreat', 'USDT', '22.5000');
        $this->purchase($older, $usd, 'USD-PAY-1', 'approved', '2026-10-02 06:00:00', $admin->id);
        $this->purchase($newer, $usdt, 'USDT-PAY-1', 'pending', '2026-10-21 06:00:00');

        $members = $this->actingAs($admin)->get(route('admin.catalog-reports.show', ['report' => 'members']));
        $members->assertOk()
            ->assertSeeTextInOrder(['STREP002', 'STREP001'])
            ->assertSeeText('Not tracked')
            ->assertSeeText('View details');

        $this->actingAs($admin)->get(route('admin.catalog-reports.show', [
            'report' => 'members',
            'q' => 'STREP001',
            'from' => '2026-10-01',
            'to' => '2026-10-05',
        ]))->assertOk()->assertSeeText('STREP001')->assertDontSeeText('STREP002');

        $this->actingAs($admin)->get(route('admin.catalog-reports.show', [
            'report' => 'members',
            'from' => '2026-10-20',
            'to' => '2026-10-01',
        ]))->assertSessionHasErrors('to');

        $payments = $this->actingAs($admin)->get(route('admin.catalog-reports.show', ['report' => 'payments']));
        $payments->assertOk()
            ->assertSeeText('USD-PAY-1')
            ->assertSeeText('USDT-PAY-1')
            ->assertSeeText('Total USD')
            ->assertSeeText('11.2500')
            ->assertSeeText('Total USDT')
            ->assertSeeText('22.5000')
            ->assertDontSeeText('33.7500');

        $this->actingAs($admin)->get(route('admin.catalog-reports.show', [
            'report' => 'payments',
            'currency' => 'USD',
            'status' => 'approved',
        ]))->assertOk()->assertSeeText('USD-PAY-1')->assertDontSeeText('USDT-PAY-1');

        $this->actingAs($admin)->get(route('admin.catalog-reports.show', [
            'report' => 'packages',
            'dataset' => 'catalog',
        ]))->assertOk()->assertSeeText('Hill Station Retreat')->assertSeeText('Package catalog');

        $this->actingAs($admin)->get(route('admin.catalog-reports.members.show', ['member' => 'STREP001']))
            ->assertOk()
            ->assertSeeText('Verification Package')
            ->assertSeeText('No activity records found.')
            ->assertDontSeeText($older->password);
    }

    public function test_earnings_report_is_read_only_and_labels_legacy_records(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $member = $this->member('STEARN001', 'Earn Member');
        $member->forceFill([
            'roi_wallet_amount' => '4.0000',
            'working_wallet_amount' => '5.0000',
            'activation_wallet_amount' => '6.0000',
        ])->save();
        RoiTransaction::query()->create([
            'reference' => 'ROI-EARN-1',
            'investment_id' => 'INV-EARN-1',
            'member_id' => $member->member_id,
            'member_name' => $member->member_name,
            'on_amount' => '100.0000',
            'rate_percentage' => '5.000',
            'income_amount' => '1.2500',
            'roi_date' => '2026-10-10',
            'status' => 'generated',
        ]);
        LevelCommissionTransaction::query()->create([
            'reference' => 'LVL-EARN-1',
            'investment_id' => 'INV-EARN-1',
            'member_id' => $member->member_id,
            'member_name' => $member->member_name,
            'from_member_id' => 'STSOURCE',
            'from_member_name' => 'Source Member',
            'level' => 2,
            'business_date' => '2026-10-11',
            'on_amount' => '100.0000',
            'rate_percentage' => '12.500',
            'income_amount' => '2.5000',
        ]);
        ActivationWalletTransaction::query()->create([
            'member_id' => $member->member_id,
            'member_name' => $member->member_name,
            'amount' => '3.0000',
            'type' => 'credit',
            'remarks' => 'Manual credit',
            'reference' => 'ACT-EARN-1',
        ]);

        $this->actingAs($admin)->get(route('admin.catalog-reports.show', ['report' => 'earnings']))
            ->assertOk()
            ->assertSeeText('not calculated')
            ->assertSeeText('Stored ROI records')
            ->assertSeeText('Legacy investment')
            ->assertSeeText('ROI-EARN-1')
            ->assertSeeText('Level commission')
            ->assertSeeText('STSOURCE')
            ->assertSeeText('Wallet credit')
            ->assertSeeText('Not stored');

        $this->actingAs($admin)->get(route('admin.catalog-reports.show', [
            'report' => 'earnings',
            'income_type' => 'roi',
        ]))->assertOk()->assertSeeText('ROI-EARN-1')->assertDontSeeText('LVL-EARN-1');

        $member->refresh();
        $this->assertSame('4.0000', $member->roi_wallet_amount);
        $this->assertSame('5.0000', $member->working_wallet_amount);
        $this->assertSame('6.0000', $member->activation_wallet_amount);
        $this->assertSame(1, RoiTransaction::query()->count());
    }

    public function test_exports_respect_filters_formats_and_the_pdf_limit(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->member('STEXP001', '=1+1');
        $this->member('STEXP002', 'Second');
        $this->member('STEXP003', 'Third');

        $filtered = $this->actingAs($admin)->get(route('admin.catalog-reports.export', [
            'report' => 'members',
            'format' => 'csv',
            'scope' => 'filtered',
            'q' => 'STEXP001',
        ]));
        $filtered->assertOk();
        $csv = $filtered->streamedContent();
        $this->assertStringContainsString('STEXP001', $csv);
        $this->assertStringContainsString("'=1+1", $csv);
        $this->assertStringNotContainsString('STEXP002', $csv);
        $this->assertDatabaseHas('admin_activity_logs', [
            'action' => 'report_export',
            'subject' => 'members',
            'format' => 'csv',
            'scope' => 'filtered',
            'row_count' => 1,
        ]);

        $full = $this->actingAs($admin)->get(route('admin.catalog-reports.export', [
            'report' => 'members',
            'format' => 'xlsx',
            'scope' => 'full',
        ]));
        $xlsx = $full->streamedContent();
        $this->assertSame('PK', substr($xlsx, 0, 2));
        $path = storage_path('app/report-export-test.xlsx');
        file_put_contents($path, $xlsx);
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path));
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        @unlink($path);
        $this->assertStringContainsString('STEXP002', $sheet);
        $this->assertStringContainsString('Member ID', $sheet);

        $pdf = $this->actingAs($admin)->get(route('admin.catalog-reports.export', [
            'report' => 'members',
            'format' => 'pdf',
            'scope' => 'full',
        ]));
        $this->assertStringStartsWith('%PDF', $pdf->streamedContent());

        config(['deltasupport.report_pdf_row_limit' => 2]);
        $this->actingAs($admin)->get(route('admin.catalog-reports.export', [
            'report' => 'members',
            'format' => 'pdf',
            'scope' => 'full',
        ]))->assertStatus(422);
        $this->assertSame(3, AdminActivityLog::query()->count());
    }

    public function test_pagination_and_empty_reports_stay_stable(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->get(route('admin.catalog-reports.show', ['report' => 'earnings']))
            ->assertOk()
            ->assertSeeText('No records found.');

        foreach (range(1, 30) as $index) {
            $this->member('STPAG'.str_pad((string) $index, 3, '0', STR_PAD_LEFT), 'Page '.$index);
        }

        $page = $this->actingAs($admin)->get(route('admin.catalog-reports.show', [
            'report' => 'members',
            'sort' => 'oldest',
            'q' => 'STPAG',
        ]));
        $page->assertOk()->assertSeeText('STPAG001')->assertDontSeeText('STPAG030');
        $this->actingAs($admin)->get(route('admin.catalog-reports.show', [
            'report' => 'members',
            'sort' => 'oldest',
            'q' => 'STPAG',
            'page' => 2,
        ]))->assertOk()->assertSeeText('STPAG030')->assertSee('q=STPAG', false);
    }

    private function member(string $memberId, string $name): Member
    {
        return Member::query()->create([
            'member_id' => $memberId,
            'sponsor_id' => 'ST666666',
            'sponsor_name' => 'Admin',
            'member_name' => $name,
            'mobile_no' => '9000000000',
            'email' => strtolower($memberId).'@example.test',
            'status' => 'active',
        ]);
    }

    private function package(string $code, string $name, string $currency, string $price): Package
    {
        return Package::query()->create([
            'code' => $code,
            'package_type' => 'tour',
            'name' => $name,
            'price_usd' => $price,
            'denomination' => $currency,
            'roi_eligible' => false,
            'level_commission_eligible' => false,
            'is_active' => true,
        ]);
    }

    private function purchase(Member $member, Package $package, string $reference, string $status, string $submittedAt, ?int $reviewerId = null): void
    {
        PackagePurchase::query()->create([
            'reference' => 'PUR-'.$reference,
            'idempotency_key' => (string) Str::uuid(),
            'member_id' => $member->member_id,
            'package_id' => $package->id,
            'package_code' => $package->code,
            'package_name' => $package->name,
            'package_type' => $package->package_type,
            'price_usd' => $package->price_usd,
            'denomination' => $package->denomination,
            'network' => 'TRC20',
            'payment_reference' => $reference,
            'status' => $status,
            'submitted_at' => $submittedAt,
            'reviewed_at' => $status === 'approved' ? $submittedAt : null,
            'reviewed_by' => $reviewerId,
        ]);
    }
}
