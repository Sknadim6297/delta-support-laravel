<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LevelIncomePerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_uses_bounded_pages_and_sql_aggregate_with_a_small_query_count(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $this->insertTransactions(125);

        $queries = [];
        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            $queries[] = ['sql' => $query->sql, 'time_ms' => $query->time];
        });

        $response = $this->get(route('admin.report.level-income'));

        $response->assertOk()
            ->assertSee('Showing 1 to 50 of 125 entries', false)
            ->assertSee('<strong>125</strong>', false)
            ->assertSeeText('LC-BENCH-00125')
            ->assertDontSeeText('LC-BENCH-00001');

        $this->assertLessThanOrEqual(8, count($queries), json_encode($queries));
        $this->assertTrue(collect($queries)->contains(fn (array $query): bool => str_contains(strtolower($query['sql']), 'sum(')));

        $this->get(route('admin.report.level-income', ['per_page' => 1000]))
            ->assertOk()
            ->assertSee('Showing 1 to 100 of 125 entries', false)
            ->assertSeeText('LC-BENCH-00026')
            ->assertDontSeeText('LC-BENCH-00025');
    }

    public function test_level_income_filters_and_streamed_csv_preserve_results_and_order(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $this->insertTransactions(6);

        $this->get(route('admin.report.level-income', [
            'member_id' => 'STBENCH0002',
            'level' => 2,
            'from_date' => '2026-01-02',
            'to_date' => '2026-01-04',
        ]))->assertOk()
            ->assertSee('Showing 1 to 1 of 1 entries', false)
            ->assertSeeText('LC-BENCH-00002')
            ->assertDontSeeText('LC-BENCH-00001')
            ->assertSee('<strong>2</strong>', false);

        $response = $this->get(route('admin.report.level-income.export'));
        $response->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertSeeText('LC-BENCH-00006')
            ->assertSeeText('LC-BENCH-00001');

        $content = $response->streamedContent();
        $this->assertSame(7, substr_count(trim($content), "\n") + 1);
        $this->assertLessThan(strpos($content, 'LC-BENCH-00001'), strpos($content, 'LC-BENCH-00006'));
    }

    private function insertTransactions(int $count): void
    {
        $rows = [];
        for ($number = 1; $number <= $count; $number++) {
            $id = str_pad((string) $number, 5, '0', STR_PAD_LEFT);
            $memberNumber = str_pad((string) (($number % 10) + 1), 4, '0', STR_PAD_LEFT);
            $createdAt = sprintf('2026-01-%02d 12:00:00', (($number - 1) % 6) + 1);
            // Row 2 is the filtered sample (STBENCH0002, level 2, 2 Jan 2026, amount 2).
            // Row 1 offsets that extra unit so 125 rows still total 125.
            $incomeAmount = match ($number) {
                1 => '0.0000',
                2 => '2.0000',
                default => '1.0000',
            };
            $rows[] = [
                'reference' => 'LC-BENCH-' . $id,
                'investment_id' => 'INV-BENCH-' . $id,
                'member_id' => $number === 2 ? 'STBENCH0002' : 'STBENCH' . $memberNumber,
                'member_name' => 'Benchmark Member ' . $memberNumber,
                'from_member_id' => 'STSOURCE' . $id,
                'from_member_name' => 'Source Member ' . $id,
                'level' => $number === 2 ? 2 : ($number % 3) + 1,
                'business_date' => substr($createdAt, 0, 10),
                'on_amount' => '100.0000',
                'rate_percentage' => '2.000',
                'income_amount' => $incomeAmount,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('level_commission_transactions')->insert($chunk);
        }
    }
}
