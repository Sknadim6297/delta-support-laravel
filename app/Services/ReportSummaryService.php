<?php

namespace App\Services;

use App\Models\LevelCommissionTransaction;
use App\Models\RoiTransaction;
use Illuminate\Support\Facades\DB;
use LogicException;
use RuntimeException;

class ReportSummaryService
{
    public function roiReportTotal(?string $memberSearch = null, ?int $fromDateKey = null, ?int $toDateKey = null): ?string
    {
        $generationId = $this->activeGenerationId();
        if ($generationId === null) {
            return null;
        }

        $table = $memberSearch !== null && $memberSearch !== ''
            ? 'roi_report_member_daily_summaries'
            : 'roi_report_global_daily_summaries';
        $query = DB::table($table)->where('generation_id', $generationId);

        if ($memberSearch !== null && $memberSearch !== '') {
            $query->where('member_id', 'like', '%' . $memberSearch . '%');
        }

        $this->applyDateKeys($query, $fromDateKey, $toDateKey);

        return bcadd((string) $query->sum('total_income_amount'), '0', 4);
    }

    public function roiReportTransactionCount(?string $memberSearch = null, ?int $fromDateKey = null, ?int $toDateKey = null): ?string
    {
        $generationId = $this->activeGenerationId();
        if ($generationId === null) {
            return null;
        }

        $table = $memberSearch !== null && $memberSearch !== ''
            ? 'roi_report_member_daily_summaries'
            : 'roi_report_global_daily_summaries';
        $query = DB::table($table)->where('generation_id', $generationId);

        if ($memberSearch !== null && $memberSearch !== '') {
            $query->where('member_id', 'like', '%' . $memberSearch . '%');
        }

        $this->applyDateKeys($query, $fromDateKey, $toDateKey);

        return (string) $query->sum('transaction_count');
    }

    public function levelCommissionReportTotal(
        ?string $memberSearch = null,
        ?int $level = null,
        ?int $fromDateKey = null,
        ?int $toDateKey = null,
    ): ?string {
        $generationId = $this->activeGenerationId();
        if ($generationId === null) {
            return null;
        }

        if ($memberSearch !== null && $memberSearch !== '') {
            $table = 'level_commission_report_member_level_daily_summaries';
        } elseif ($level !== null) {
            $table = 'level_commission_report_level_daily_summaries';
        } else {
            $table = 'level_commission_report_global_daily_summaries';
        }

        $query = DB::table($table)->where('generation_id', $generationId);

        if ($memberSearch !== null && $memberSearch !== '') {
            $query->where('member_id', 'like', '%' . $memberSearch . '%');
        }
        if ($level !== null) {
            $query->where('level', $level);
        }

        $this->applyDateKeys($query, $fromDateKey, $toDateKey);

        return bcadd((string) $query->sum('total_income_amount'), '0', 4);
    }

    public function levelCommissionReportTransactionCount(
        ?string $memberSearch = null,
        ?int $level = null,
        ?int $fromDateKey = null,
        ?int $toDateKey = null,
    ): ?string {
        $generationId = $this->activeGenerationId();
        if ($generationId === null) {
            return null;
        }

        if ($memberSearch !== null && $memberSearch !== '') {
            $table = 'level_commission_report_member_level_daily_summaries';
        } elseif ($level !== null) {
            $table = 'level_commission_report_level_daily_summaries';
        } else {
            $table = 'level_commission_report_global_daily_summaries';
        }

        $query = DB::table($table)->where('generation_id', $generationId);

        if ($memberSearch !== null && $memberSearch !== '') {
            $query->where('member_id', 'like', '%' . $memberSearch . '%');
        }
        if ($level !== null) {
            $query->where('level', $level);
        }

        $this->applyDateKeys($query, $fromDateKey, $toDateKey);

        return (string) $query->sum('transaction_count');
    }

    public function addRoiTransaction(RoiTransaction $transaction): void
    {
        $generationId = $this->writableGenerationId();
        if ($generationId === null) {
            return;
        }

        $row = DB::table('roi_transactions')
            ->select(['member_id', 'income_amount'])
            ->selectRaw($this->dateKeyExpression() . ' AS date_key')
            ->where('id', $transaction->getKey())
            ->first();

        if (! $row) {
            throw new RuntimeException('The ROI transaction must be inserted before its report summary is updated.');
        }

        $this->increment('roi_report_global_daily_summaries', [
            'generation_id' => $generationId,
            'date_key' => $row->date_key,
        ], (string) $row->income_amount);

        $this->increment('roi_report_member_daily_summaries', [
            'generation_id' => $generationId,
            'member_id' => $row->member_id,
            'date_key' => $row->date_key,
        ], (string) $row->income_amount);
    }

    public function addLevelCommissionTransaction(LevelCommissionTransaction $transaction): void
    {
        $generationId = $this->writableGenerationId();
        if ($generationId === null) {
            return;
        }

        $row = DB::table('level_commission_transactions')
            ->select(['member_id', 'level', 'income_amount'])
            ->selectRaw($this->dateKeyExpression() . ' AS date_key')
            ->where('id', $transaction->getKey())
            ->first();

        if (! $row) {
            throw new RuntimeException('The Level Commission transaction must be inserted before its report summary is updated.');
        }

        $this->increment('level_commission_report_global_daily_summaries', [
            'generation_id' => $generationId,
            'date_key' => $row->date_key,
        ], (string) $row->income_amount);

        $this->increment('level_commission_report_level_daily_summaries', [
            'generation_id' => $generationId,
            'level' => $row->level,
            'date_key' => $row->date_key,
        ], (string) $row->income_amount);

        $this->increment('level_commission_report_member_level_daily_summaries', [
            'generation_id' => $generationId,
            'member_id' => $row->member_id,
            'level' => $row->level,
            'date_key' => $row->date_key,
        ], (string) $row->income_amount);
    }

    /**
     * Batch equivalent of addLevelCommissionTransaction(): same buckets and totals,
     * but one state read, one row read and one upsert per summary table.
     *
     * @param  array<int, int>  $transactionIds
     */
    public function addLevelCommissionTransactions(array $transactionIds): void
    {
        if ($transactionIds === []) {
            return;
        }

        $generationId = $this->writableGenerationId();
        if ($generationId === null) {
            return;
        }

        $rows = DB::table('level_commission_transactions')
            ->select(['member_id', 'level', 'income_amount'])
            ->selectRaw($this->dateKeyExpression() . ' AS date_key')
            ->whereIn('id', $transactionIds)
            ->get();

        if ($rows->count() !== count($transactionIds)) {
            throw new RuntimeException('The Level Commission transaction must be inserted before its report summary is updated.');
        }

        $global = [];
        $levels = [];
        $members = [];
        foreach ($rows as $row) {
            $amount = (string) $row->income_amount;
            $keys = [
                'global' => (string) $row->date_key,
                'level' => $row->level . '|' . $row->date_key,
                'member' => $row->member_id . '|' . $row->level . '|' . $row->date_key,
            ];
            foreach ($keys as $name => $key) {
                $buckets[$name][$key] ??= ['count' => 0, 'amount' => '0.0000', 'row' => $row];
                $buckets[$name][$key]['count']++;
                $buckets[$name][$key]['amount'] = bcadd($buckets[$name][$key]['amount'], $amount, 4);
            }
        }
        $global = $buckets['global'] ?? [];
        $levels = $buckets['level'] ?? [];
        $members = $buckets['member'] ?? [];

        $this->incrementMany('level_commission_report_global_daily_summaries', array_map(
            fn (array $b): array => ['dimensions' => ['generation_id' => $generationId, 'date_key' => $b['row']->date_key]] + $b,
            array_values($global)
        ));
        $this->incrementMany('level_commission_report_level_daily_summaries', array_map(
            fn (array $b): array => ['dimensions' => ['generation_id' => $generationId, 'level' => $b['row']->level, 'date_key' => $b['row']->date_key]] + $b,
            array_values($levels)
        ));
        $this->incrementMany('level_commission_report_member_level_daily_summaries', array_map(
            fn (array $b): array => ['dimensions' => [
                'generation_id' => $generationId,
                'member_id' => $b['row']->member_id,
                'level' => $b['row']->level,
                'date_key' => $b['row']->date_key,
            ]] + $b,
            array_values($members)
        ));
    }

    private function incrementMany(string $table, array $buckets): void
    {
        if ($buckets === []) {
            return;
        }

        $columns = array_keys($buckets[0]['dimensions']);
        $quotedColumns = implode(', ', array_map(fn (string $column): string => '`' . $column . '`', $columns));
        $tuple = '(' . implode(', ', array_fill(0, count($columns) + 2, '?')) . ')';
        $bindings = [];
        foreach ($buckets as $bucket) {
            array_push($bindings, ...array_values($bucket['dimensions']), ...[$bucket['count'], $bucket['amount']]);
        }

        $sql = 'INSERT INTO `' . $table . '` (' . $quotedColumns . ', `transaction_count`, `total_income_amount`) VALUES '
            . implode(', ', array_fill(0, count($buckets), $tuple));

        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement(
                $sql . ' ON CONFLICT (' . $quotedColumns . ') DO UPDATE SET '
                    . '`transaction_count` = `transaction_count` + excluded.`transaction_count`, '
                    . '`total_income_amount` = `total_income_amount` + excluded.`total_income_amount`',
                $bindings
            );

            return;
        }

        DB::statement(
            $sql . ' ON DUPLICATE KEY UPDATE '
                . '`transaction_count` = `transaction_count` + VALUES(`transaction_count`), '
                . '`total_income_amount` = `total_income_amount` + VALUES(`total_income_amount`)',
            $bindings
        );
    }

    public function removeRoiTransaction(RoiTransaction|int $transaction): void
    {
        $generationId = $this->writableGenerationId();
        if ($generationId === null) {
            return;
        }

        $row = DB::table('roi_transactions')
            ->select(['id', 'member_id', 'income_amount'])
            ->selectRaw($this->dateKeyExpression() . ' AS date_key')
            ->where('id', $transaction instanceof RoiTransaction ? $transaction->getKey() : $transaction)
            ->first();

        if (! $row) {
            throw new RuntimeException('The ROI transaction was not found before summary removal.');
        }

        $this->decrement('roi_report_global_daily_summaries', [
            'generation_id' => $generationId,
            'date_key' => $row->date_key,
        ], (string) $row->income_amount);

        $this->decrement('roi_report_member_daily_summaries', [
            'generation_id' => $generationId,
            'member_id' => $row->member_id,
            'date_key' => $row->date_key,
        ], (string) $row->income_amount);
    }

    public function removeLevelCommissionTransaction(LevelCommissionTransaction|int $transaction): void
    {
        $generationId = $this->writableGenerationId();
        if ($generationId === null) {
            return;
        }

        $row = DB::table('level_commission_transactions')
            ->select(['id', 'member_id', 'level', 'income_amount'])
            ->selectRaw($this->dateKeyExpression() . ' AS date_key')
            ->where('id', $transaction instanceof LevelCommissionTransaction ? $transaction->getKey() : $transaction)
            ->first();

        if (! $row) {
            throw new RuntimeException('The Level Commission transaction was not found before summary removal.');
        }

        $this->decrement('level_commission_report_global_daily_summaries', [
            'generation_id' => $generationId,
            'date_key' => $row->date_key,
        ], (string) $row->income_amount);

        $this->decrement('level_commission_report_level_daily_summaries', [
            'generation_id' => $generationId,
            'level' => $row->level,
            'date_key' => $row->date_key,
        ], (string) $row->income_amount);

        $this->decrement('level_commission_report_member_level_daily_summaries', [
            'generation_id' => $generationId,
            'member_id' => $row->member_id,
            'level' => $row->level,
            'date_key' => $row->date_key,
        ], (string) $row->income_amount);
    }

    public function summaryWritesActive(): bool
    {
        return $this->writableGenerationId() !== null;
    }

    public function activeGenerationId(): ?int
    {
        $state = DB::table('report_summary_state')
            ->where('id', 1)
            ->first(['active_generation_id', 'writes_enabled', 'rebuild_in_progress']);

        if (
            ! $state
            || $state->active_generation_id === null
            || ! $state->writes_enabled
            || $state->rebuild_in_progress
        ) {
            return null;
        }

        $generation = DB::table('report_summary_generations')
            ->where('id', $state->active_generation_id)
            ->where('status', 'active')
            ->first(['source_timezone']);

        if (! $generation) {
            return null;
        }

        if (DB::connection()->getDriverName() === 'mysql') {
            $timezone = DB::selectOne('SELECT @@session.time_zone AS session_timezone, @@system_time_zone AS system_timezone');
            $currentTimezone = ($timezone->session_timezone ?? 'UNKNOWN') . '/' . ($timezone->system_timezone ?? 'UNKNOWN');

            if ($generation->source_timezone !== $currentTimezone) {
                return null;
            }
        }

        return (int) $state->active_generation_id;
    }

    private function writableGenerationId(): ?int
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Report summaries must be updated in the raw transaction transaction.');
        }

        $stateQuery = DB::table('report_summary_state')->where('id', 1);
        if (DB::connection()->getDriverName() === 'mysql') {
            $stateQuery->lock('lock in share mode');
        }
        $state = $stateQuery->first();

        if (! $state) {
            throw new RuntimeException('Report summary state has not been initialized.');
        }

        if ($state->rebuild_in_progress) {
            throw new RuntimeException('Transaction generation is paused while report summaries are being rebuilt.');
        }

        if ($state->active_generation_id === null) {
            return null;
        }

        if (! $state->writes_enabled) {
            throw new RuntimeException('Report summary writes are disabled for the active generation.');
        }

        return (int) $state->active_generation_id;
    }

    private function increment(string $table, array $dimensions, string $amount): void
    {
        $columns = array_keys($dimensions);
        $quotedColumns = implode(', ', array_map(fn (string $column): string => '`' . $column . '`', $columns));
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $bindings = array_values($dimensions);
        $bindings[] = 1;
        $bindings[] = $amount;

        $sql = 'INSERT INTO `' . $table . '` (' . $quotedColumns . ', `transaction_count`, `total_income_amount`) VALUES ('
            . $placeholders . ', ?, ?)';

        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement(
                $sql . ' ON CONFLICT (' . $quotedColumns . ') DO UPDATE SET '
                    . '`transaction_count` = `transaction_count` + excluded.`transaction_count`, '
                    . '`total_income_amount` = `total_income_amount` + excluded.`total_income_amount`',
                $bindings
            );

            return;
        }

        DB::statement(
            $sql . ' ON DUPLICATE KEY UPDATE '
                . '`transaction_count` = `transaction_count` + VALUES(`transaction_count`), '
                . '`total_income_amount` = `total_income_amount` + VALUES(`total_income_amount`)',
            $bindings
        );
    }

    private function decrement(string $table, array $dimensions, string $amount): void
    {
        $query = DB::table($table);
        foreach ($dimensions as $column => $value) {
            $query->where($column, $value);
        }

        $updated = $query
            ->where('transaction_count', '>', 0)
            ->update([
                'transaction_count' => DB::raw('transaction_count - 1'),
                'total_income_amount' => DB::raw('total_income_amount - ' . DB::connection()->getPdo()->quote($amount)),
            ]);

        if ($updated !== 1) {
            throw new RuntimeException('The report summary row is missing or has an invalid count.');
        }

        $emptyBucket = DB::table($table);
        foreach ($dimensions as $column => $value) {
            $emptyBucket->where($column, $value);
        }
        $emptyBucket->where('transaction_count', 0)->delete();
    }

    private function dateKeyExpression(): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "COALESCE(CAST(strftime('%Y%m%d', created_at) AS INTEGER), 0)"
            : "COALESCE(CAST(DATE_FORMAT(created_at, '%Y%m%d') AS UNSIGNED), 0)";
    }

    private function applyDateKeys($query, ?int $fromDateKey, ?int $toDateKey): void
    {
        if ($fromDateKey !== null) {
            $query->where('date_key', '>=', $fromDateKey);
        }
        if ($toDateKey !== null) {
            $query->where('date_key', '<=', $toDateKey);
        }
    }
}
