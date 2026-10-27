<?php

namespace Tests\Feature;

use App\Models\LevelCommissionTransaction;
use App\Models\Member;
use App\Models\RoiTransaction;
use App\Models\User;
use App\Http\Controllers\ReportController;
use App\Services\ReportSummaryService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class ReportSummaryTest extends TestCase
{
    use RefreshDatabase;

    private int $generationId;

    private ReportSummaryService $summaries;

    protected function setUp(): void
    {
        parent::setUp();

        $this->generationId = (int) DB::table('report_summary_generations')->insertGetId([
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('report_summary_state')->where('id', 1)->update([
            'active_generation_id' => $this->generationId,
            'writes_enabled' => true,
            'rebuild_in_progress' => false,
        ]);
        $this->summaries = app(ReportSummaryService::class);
    }

    public function test_roi_writes_exact_global_member_and_undated_summaries(): void
    {
        $this->addRoi('ROI-SUM-1', 'STSUM001', '12.3400', '2026-09-01 12:00:00');
        $this->addRoi('ROI-SUM-2', 'STSUM001', '0.6600', '2026-09-01 14:00:00');
        $this->addRoi('ROI-SUM-3', 'STSUM002', '2.0000', null);

        $this->assertSummary('roi_report_global_daily_summaries', ['date_key' => 20260901], 2, '13.0000');
        $this->assertSummary('roi_report_global_daily_summaries', ['date_key' => 0], 1, '2.0000');
        $this->assertSummary('roi_report_member_daily_summaries', ['member_id' => 'STSUM001', 'date_key' => 20260901], 2, '13.0000');
        $this->assertSummary('roi_report_member_daily_summaries', ['member_id' => 'STSUM002', 'date_key' => 0], 1, '2.0000');
    }

    public function test_level_commission_writes_all_three_required_grains(): void
    {
        $this->addLevel('LC-SUM-1', 'STSUM001', 2, '10.1200', '2026-09-01 12:00:00');
        $this->addLevel('LC-SUM-2', 'STSUM001', 2, '0.8800', '2026-09-01 14:00:00');
        $this->addLevel('LC-SUM-3', 'STSUM001', 3, '5.0000', '2026-09-02 00:00:00');
        $this->addLevel('LC-SUM-4', 'STSUM002', 3, '2.0000', null);

        $this->assertSummary('level_commission_report_global_daily_summaries', ['date_key' => 20260901], 2, '11.0000');
        $this->assertSummary('level_commission_report_level_daily_summaries', ['level' => 2, 'date_key' => 20260901], 2, '11.0000');
        $this->assertSummary('level_commission_report_member_level_daily_summaries', ['member_id' => 'STSUM001', 'level' => 2, 'date_key' => 20260901], 2, '11.0000');
        $this->assertSummary('level_commission_report_member_level_daily_summaries', ['member_id' => 'STSUM001', 'level' => 3, 'date_key' => 20260902], 1, '5.0000');
        $this->assertSummary('level_commission_report_global_daily_summaries', ['date_key' => 0], 1, '2.0000');
        $this->assertSummary('level_commission_report_level_daily_summaries', ['level' => 3, 'date_key' => 0], 1, '2.0000');
        $this->assertSummary('level_commission_report_member_level_daily_summaries', ['member_id' => 'STSUM002', 'level' => 3, 'date_key' => 0], 1, '2.0000');
    }

    public function test_summary_updates_roll_back_with_the_raw_transaction(): void
    {
        try {
            DB::transaction(function (): void {
                $this->addRoiInsideTransaction('ROI-ROLLBACK', 'STSUM001', '99.9900', '2026-09-03 12:00:00');
                throw new RuntimeException('force rollback');
            });
        } catch (RuntimeException $exception) {
            $this->assertSame('force rollback', $exception->getMessage());
        }

        $this->assertDatabaseMissing('roi_transactions', ['reference' => 'ROI-ROLLBACK']);
        $this->assertDatabaseMissing('roi_report_global_daily_summaries', [
            'generation_id' => $this->generationId,
            'date_key' => 20260903,
        ]);
    }

    public function test_level_commission_summary_updates_roll_back_with_the_raw_transaction(): void
    {
        try {
            DB::transaction(function (): void {
                $transaction = new LevelCommissionTransaction;
                $transaction->forceFill([
                    'reference' => 'LC-ROLLBACK',
                    'investment_id' => 'INV-LC-ROLLBACK',
                    'member_id' => 'STSUM001',
                    'member_name' => 'Summary Test Member',
                    'from_member_id' => 'STSOURCE',
                    'from_member_name' => 'Summary Test Source',
                    'level' => 2,
                    'business_date' => '2026-09-01',
                    'on_amount' => '100.0000',
                    'rate_percentage' => '1.000',
                    'income_amount' => '99.9900',
                    'created_at' => '2026-09-03 12:00:00',
                    'updated_at' => '2026-09-03 12:00:00',
                ])->save();

                $this->summaries->addLevelCommissionTransaction($transaction);
                throw new RuntimeException('force rollback');
            });
        } catch (RuntimeException $exception) {
            $this->assertSame('force rollback', $exception->getMessage());
        }

        $this->assertDatabaseMissing('level_commission_transactions', ['reference' => 'LC-ROLLBACK']);
        $this->assertDatabaseMissing('level_commission_report_global_daily_summaries', [
            'generation_id' => $this->generationId,
            'date_key' => 20260903,
        ]);
    }

    public function test_duplicate_raw_transaction_does_not_increment_summary_twice(): void
    {
        $transaction = $this->addRoi('ROI-DUPLICATE', 'STSUM001', '1.2500', '2026-09-04 12:00:00');

        try {
            DB::transaction(function () use ($transaction): void {
                $duplicate = $transaction->replicate();
                $duplicate->save();
                $this->summaries->addRoiTransaction($duplicate);
            });
            $this->fail('The raw unique reference should reject a duplicate transaction.');
        } catch (QueryException) {
            $this->assertSummary('roi_report_global_daily_summaries', ['date_key' => 20260904], 1, '1.2500');
        }
    }

    public function test_removing_a_transaction_reverses_every_summary_delta(): void
    {
        $transaction = $this->addLevel('LC-REMOVE', 'STSUM002', 4, '7.2500', '2026-09-05 12:00:00');

        DB::transaction(function () use ($transaction): void {
            $this->summaries->removeLevelCommissionTransaction($transaction);
            $transaction->delete();
        });

        $this->assertDatabaseMissing('level_commission_report_global_daily_summaries', [
            'generation_id' => $this->generationId,
            'date_key' => 20260905,
        ]);
        $this->assertDatabaseMissing('level_commission_report_level_daily_summaries', [
            'generation_id' => $this->generationId,
            'level' => 4,
            'date_key' => 20260905,
        ]);
        $this->assertDatabaseMissing('level_commission_report_member_level_daily_summaries', [
            'generation_id' => $this->generationId,
            'member_id' => 'STSUM002',
            'level' => 4,
            'date_key' => 20260905,
        ]);
    }

    public function test_writes_fail_closed_during_a_summary_rebuild(): void
    {
        DB::table('report_summary_state')->where('id', 1)->update(['rebuild_in_progress' => true]);

        try {
            $this->addRoi('ROI-REBUILD-PAUSE', 'STSUM001', '1.0000', '2026-09-06 12:00:00');
            $this->fail('Transaction generation must stop while summaries are being rebuilt.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('paused', $exception->getMessage());
        }

        $this->assertDatabaseMissing('roi_transactions', ['reference' => 'ROI-REBUILD-PAUSE']);
    }

    public function test_large_decimal_totals_are_formatted_without_float_precision_loss(): void
    {
        $controller = app(ReportController::class);
        $formatter = new \ReflectionMethod($controller, 'formatDecimalAmount');

        $this->assertSame(
            '9007199254740993.1234',
            $formatter->invoke($controller, '9007199254740993.1234')
        );
        $this->assertSame(
            '123456789012345678901234567890.1',
            $formatter->invoke($controller, '123456789012345678901234567890.1000')
        );
    }

    public function test_roi_report_uses_exact_summary_filters_without_raw_aggregate_scans(): void
    {
        $this->createMember('STROI001');
        $this->createMember('STROI002');
        $this->addRoi('ROI-REPORT-1', 'STROI001', '1.0001', '2026-09-01 12:00:00');
        $this->addRoi('ROI-REPORT-2', 'STROI001', '2.0002', '2026-09-02 12:00:00');
        $this->addRoi('ROI-REPORT-3', 'STROI002', '3.0003', '2026-09-02 14:00:00');
        $this->addRoi('ROI-REPORT-4', 'STROI002', '7.0000', null);
        $this->actingAs(User::factory()->create(['is_admin' => true]));

        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = strtolower($query->sql);
        });

        $this->get(route('admin.report.roi-report'))->assertOk()->assertSee('<strong>USDT 13.0006</strong>', false);
        $this->get(route('admin.report.roi-report', ['member_id' => 'STROI001']))
            ->assertOk()->assertSee('<strong>USDT 3.0003</strong>', false);
        $this->get(route('admin.report.roi-report', ['from_date' => '2026-09-02', 'to_date' => '2026-09-02']))
            ->assertOk()->assertSee('<strong>USDT 5.0005</strong>', false);
        $this->get(route('admin.report.roi-report', [
            'member_id' => 'STROI',
            'from_date' => '2026-09-02',
            'to_date' => '2026-09-02',
        ]))->assertOk()->assertSee('<strong>USDT 5.0005</strong>', false);

        $aggregateQueries = array_values(array_filter($queries, fn (string $sql): bool => str_contains($sql, 'sum(')));
        $this->assertCount(4, $aggregateQueries);
        $this->assertTrue(collect($aggregateQueries)->every(fn (string $sql): bool => str_contains($sql, 'roi_report_')));
        $this->assertFalse(collect($aggregateQueries)->contains(fn (string $sql): bool => str_contains($sql, 'from `roi_transactions`')));
    }

    public function test_level_commission_report_uses_the_three_approved_grains_for_every_filter_combination(): void
    {
        $this->createMember('STLC001');
        $this->createMember('STLC002');
        $this->addLevel('LC-REPORT-1', 'STLC001', 1, '1.0001', '2026-09-01 12:00:00');
        $this->addLevel('LC-REPORT-2', 'STLC001', 2, '2.0002', '2026-09-02 12:00:00');
        $this->addLevel('LC-REPORT-3', 'STLC002', 2, '3.0003', '2026-09-02 14:00:00');
        $this->addLevel('LC-REPORT-4', 'STLC002', 3, '4.0000', null);
        $this->actingAs(User::factory()->create(['is_admin' => true]));

        $this->get(route('admin.report.level-income'))->assertOk()->assertSee('<strong>USDT 10.0006</strong>', false);
        $this->get(route('admin.report.level-income', ['member_id' => 'STLC001']))
            ->assertOk()->assertSee('<strong>USDT 3.0003</strong>', false);
        $this->get(route('admin.report.level-income', ['member_id' => 'STLC']))
            ->assertOk()->assertSee('<strong>USDT 10.0006</strong>', false);
        $this->get(route('admin.report.level-income', ['from_date' => '2026-09-02', 'to_date' => '2026-09-02']))
            ->assertOk()->assertSee('<strong>USDT 5.0005</strong>', false);
        $this->get(route('admin.report.level-income', ['level' => 2]))
            ->assertOk()->assertSee('<strong>USDT 5.0005</strong>', false);
        $this->get(route('admin.report.level-income', ['member_id' => 'STLC001', 'level' => 2]))
            ->assertOk()->assertSee('<strong>USDT 2.0002</strong>', false);
        $this->get(route('admin.report.level-income', [
            'member_id' => 'STLC001',
            'from_date' => '2026-09-02',
            'to_date' => '2026-09-02',
        ]))->assertOk()->assertSee('<strong>USDT 2.0002</strong>', false);
        $this->get(route('admin.report.level-income', [
            'level' => 2,
            'from_date' => '2026-09-02',
            'to_date' => '2026-09-02',
        ]))->assertOk()->assertSee('<strong>USDT 5.0005</strong>', false);
        $this->get(route('admin.report.level-income', [
            'member_id' => 'STLC001',
            'level' => 2,
            'from_date' => '2026-09-02',
            'to_date' => '2026-09-02',
        ]))->assertOk()->assertSee('<strong>USDT 2.0002</strong>', false);
    }

    private function addRoi(string $reference, string $memberId, string $amount, ?string $createdAt): RoiTransaction
    {
        return DB::transaction(fn (): RoiTransaction => $this->addRoiInsideTransaction($reference, $memberId, $amount, $createdAt));
    }

    private function createMember(string $memberId): Member
    {
        $number = (int) substr($memberId, -3);

        return Member::create([
            'member_id' => $memberId,
            'sponsor_id' => 'STSOURCE',
            'sponsor_name' => 'Source Member',
            'member_name' => 'Summary Test Member ' . $memberId,
            'mobile_no' => (string) (9000000000 + $number),
            'pan_card_no' => 'SUM' . str_pad((string) $number, 7, '0', STR_PAD_LEFT),
            'email' => strtolower($memberId) . '@example.test',
            'status' => 'active',
        ]);
    }

    private function addRoiInsideTransaction(string $reference, string $memberId, string $amount, ?string $createdAt): RoiTransaction
    {
        $transaction = new RoiTransaction;
        $transaction->forceFill([
            'reference' => $reference,
            'investment_id' => 'INV-' . $reference,
            'member_id' => $memberId,
            'member_name' => 'Summary Test Member',
            'on_amount' => '100.0000',
            'rate_percentage' => '5.000',
            'income_amount' => $amount,
            'roi_date' => '2026-09-01',
            'status' => 'generated',
            'withdrawable_on' => '2026-10-01',
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->save();

        $this->summaries->addRoiTransaction($transaction);

        return $transaction;
    }

    private function addLevel(string $reference, string $memberId, int $level, string $amount, ?string $createdAt): LevelCommissionTransaction
    {
        return DB::transaction(function () use ($reference, $memberId, $level, $amount, $createdAt): LevelCommissionTransaction {
            $transaction = new LevelCommissionTransaction;
            $transaction->forceFill([
                'reference' => $reference,
                'investment_id' => 'INV-' . $reference,
                'member_id' => $memberId,
                'member_name' => 'Summary Test Member',
                'from_member_id' => 'STSOURCE',
                'from_member_name' => 'Summary Test Source',
                'level' => $level,
                'business_date' => '2026-09-01',
                'on_amount' => '100.0000',
                'rate_percentage' => '1.000',
                'income_amount' => $amount,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ])->save();

            $this->summaries->addLevelCommissionTransaction($transaction);

            return $transaction;
        });
    }

    private function assertSummary(string $table, array $dimensions, int $count, string $amount): void
    {
        $summary = DB::table($table)
            ->where('generation_id', $this->generationId)
            ->where($dimensions)
            ->first();

        $this->assertNotNull($summary);
        $this->assertSame((string) $count, (string) $summary->transaction_count);
        $this->assertSame($amount, number_format((float) $summary->total_income_amount, 4, '.', ''));
    }
}
