<?php

namespace Tests\Feature;

use App\Models\AdminActivityLog;
use App\Models\MemberTransferSetting;
use App\Models\Package;
use App\Models\User;
use App\Services\ProductionActivationMismatchRepair;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

class RepairActivationMismatchesTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_is_read_only_and_apply_requires_exact_confirmation(): void
    {
        $this->seedMismatchState();
        $db = (string) DB::connection()->getDatabaseName();

        $this->artisan('financial:repair-activation-mismatches', [
            '--database' => $db,
            '--preview' => true,
        ])->assertSuccessful()
            ->expectsOutputToContain('WILL UPDATE')
            ->expectsOutputToContain('Preview only');

        $this->assertTrue((bool) MemberTransferSetting::current()->member_transfers_enabled);
        $this->assertTrue((bool) Package::query()->where('code', 'DST-TOUR-003')->value('roi_eligible'));

        $this->artisan('financial:repair-activation-mismatches', [
            '--database' => $db,
            '--confirm' => 'wrong',
        ])->assertFailed()->expectsOutputToContain('repair-activation-mismatches');

        $this->assertTrue((bool) MemberTransferSetting::current()->member_transfers_enabled);
    }

    public function test_apply_is_idempotent_and_logs_exact_changes(): void
    {
        User::factory()->create(['is_admin' => true]);
        Log::spy();
        $this->seedMismatchState();
        $db = (string) DB::connection()->getDatabaseName();

        $this->artisan('financial:repair-activation-mismatches', [
            '--database' => $db,
            '--confirm' => ProductionActivationMismatchRepair::CONFIRM,
        ])->assertSuccessful()->expectsOutputToContain('Applied 3 repairs');

        $this->assertFalse((bool) MemberTransferSetting::current()->fresh()->member_transfers_enabled);
        $tour = Package::query()->where('code', 'DST-TOUR-003')->firstOrFail();
        $this->assertFalse((bool) $tour->roi_eligible);
        $this->assertFalse((bool) $tour->level_commission_eligible);
        $this->assertSame(Package::TYPE_TOUR_ROI, $tour->package_type);
        $this->assertSame('1000.0000', (string) $tour->price_usd);
        $this->assertSame(1, AdminActivityLog::query()->where('action', 'financial_activation_mismatch_repaired')->count());
        Log::shouldHaveReceived('info')->withArgs(fn ($message): bool => str_contains((string) $message, 'Production activation mismatch repair applied'));

        $this->artisan('financial:repair-activation-mismatches', [
            '--database' => $db,
            '--confirm' => ProductionActivationMismatchRepair::CONFIRM,
        ])->assertSuccessful()->expectsOutputToContain('No activation-mismatch repairs are needed');

        $this->assertSame(1, AdminActivityLog::query()->where('action', 'financial_activation_mismatch_repaired')->count());
    }

    public function test_failed_apply_rolls_back_all_changes(): void
    {
        $this->seedMismatchState();
        $repair = app(ProductionActivationMismatchRepair::class);
        $preview = $repair->preview();
        $this->assertSame(3, $preview['needed_count']);

        DB::listen(function ($query): void {
            if (str_contains(strtolower($query->sql), 'update')
                && str_contains(strtolower($query->sql), 'packages')
                && str_contains(strtolower($query->sql), 'level_commission_eligible')) {
                throw new RuntimeException('forced package update failure');
            }
        });

        try {
            $repair->apply();
            $this->fail('The forced failure should roll back.');
        } catch (RuntimeException $exception) {
            $this->assertSame('forced package update failure', $exception->getMessage());
        }

        $this->assertTrue((bool) MemberTransferSetting::current()->fresh()->member_transfers_enabled);
        $tour = Package::query()->where('code', 'DST-TOUR-003')->firstOrFail();
        $this->assertTrue((bool) $tour->roi_eligible);
        $this->assertTrue((bool) $tour->level_commission_eligible);
        $this->assertSame(0, AdminActivityLog::query()->count());
    }

    public function test_wrong_database_option_refuses_without_writing(): void
    {
        $this->seedMismatchState();

        $this->artisan('financial:repair-activation-mismatches', [
            '--database' => 'Delta_support_db',
            '--confirm' => ProductionActivationMismatchRepair::CONFIRM,
        ])->assertFailed()->expectsOutputToContain('must exactly match');

        $this->assertTrue((bool) MemberTransferSetting::current()->member_transfers_enabled);
    }

    private function seedMismatchState(): void
    {
        MemberTransferSetting::current()->forceFill([
            'member_transfers_enabled' => true,
        ])->save();

        Package::query()->create([
            'code' => 'DST-TOUR-003',
            'package_type' => Package::TYPE_TOUR_ROI,
            'name' => 'Delta Support 3-Year Tour Package',
            'price_usd' => '1000.0000',
            'denomination' => 'USDT',
            'roi_eligible' => true,
            'level_commission_eligible' => true,
            'is_active' => true,
            'sort_order' => 10,
        ]);
        Package::query()->create([
            'code' => 'DST-ROI-002',
            'package_type' => Package::TYPE_TOUR_ROI,
            'name' => 'Delta Support Digha Tour + Monthly Income',
            'price_usd' => '100.0000',
            'denomination' => 'USDT',
            'roi_eligible' => true,
            'level_commission_eligible' => true,
            'is_active' => true,
            'sort_order' => 20,
        ]);
    }
}
