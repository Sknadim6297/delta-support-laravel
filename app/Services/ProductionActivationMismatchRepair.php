<?php

namespace App\Services;

use App\Models\AdminActivityLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

/**
 * Corrects member_transfer_settings only.
 * DST-TOUR-003 income enablement is intentionally excluded; use
 * financial:activate-tour-income after written approval of terms and date.
 */
class ProductionActivationMismatchRepair
{
    public const CONFIRM = 'repair-activation-mismatches';

    /**
     * @return array{
     *   changes: list<array{table: string, key: string, before: string, after: string, needed: bool}>,
     *   needed_count: int,
     *   tour_missing: bool,
     *   tour_income_config_missing: bool
     * }
     */
    public function preview(): array
    {
        if (! Schema::hasTable('member_transfer_settings')) {
            throw new RuntimeException('member_transfer_settings table is missing. No change was made.');
        }

        $transfers = (int) (DB::table('member_transfer_settings')->value('member_transfers_enabled') ?? 0);
        $changes = [[
            'table' => 'member_transfer_settings',
            'key' => 'member_transfers_enabled',
            'before' => (string) $transfers,
            'after' => '0',
            'needed' => $transfers !== 0,
        ]];

        return [
            'changes' => $changes,
            'needed_count' => count(array_filter($changes, fn (array $change): bool => $change['needed'])),
            'tour_missing' => false,
            'tour_income_config_missing' => ! is_array(config('deltasupport.package_income.packages.DST-TOUR-003')),
        ];
    }

    /**
     * @return array{
     *   changes: list<array{table: string, key: string, before: string, after: string, needed: bool}>,
     *   applied: list<array{table: string, key: string, before: string, after: string}>,
     *   needed_count: int,
     *   tour_missing: bool,
     *   tour_income_config_missing: bool
     * }
     */
    public function apply(): array
    {
        $preview = $this->preview();
        if ($preview['needed_count'] === 0) {
            return [
                'changes' => $preview['changes'],
                'applied' => [],
                'needed_count' => 0,
                'tour_missing' => false,
                'tour_income_config_missing' => $preview['tour_income_config_missing'],
            ];
        }

        $applied = [];

        try {
            DB::transaction(function () use ($preview, &$applied): void {
                foreach ($preview['changes'] as $change) {
                    if (! $change['needed']) {
                        continue;
                    }

                    $updated = DB::table('member_transfer_settings')->update([
                        'member_transfers_enabled' => false,
                        'updated_at' => now(),
                    ]);
                    if ($updated < 1) {
                        throw new RuntimeException('member_transfer_settings could not be updated. No change was kept.');
                    }

                    $applied[] = [
                        'table' => $change['table'],
                        'key' => $change['key'],
                        'before' => $change['before'],
                        'after' => $change['after'],
                    ];
                }

                $after = $this->preview();
                if ($after['needed_count'] !== 0) {
                    throw new RuntimeException('Post-apply verification still found mismatches. No change was kept.');
                }
            });
        } catch (Throwable $exception) {
            Log::error('Production activation mismatch repair failed: '.$exception->getMessage(), [
                'planned' => $preview['changes'],
            ]);
            throw $exception;
        }

        Log::info('Production activation mismatch repair applied.', ['applied' => $applied]);
        $this->audit($applied);

        return [
            'changes' => $preview['changes'],
            'applied' => $applied,
            'needed_count' => $preview['needed_count'],
            'tour_missing' => false,
            'tour_income_config_missing' => $preview['tour_income_config_missing'],
        ];
    }

    /**
     * @param  list<array{table: string, key: string, before: string, after: string}>  $applied
     */
    private function audit(array $applied): void
    {
        $adminId = User::query()->where('is_admin', true)->value('id');
        if (! $adminId) {
            return;
        }

        AdminActivityLog::query()->create([
            'user_id' => $adminId,
            'action' => 'financial_activation_mismatch_repaired',
            'subject' => 'financial_activation',
            'format' => null,
            'scope' => 'admin',
            'filters' => [
                'database' => (string) DB::connection()->getDatabaseName(),
                'applied' => $applied,
                'note' => 'Tour income enablement is handled only by financial:activate-tour-income.',
            ],
            'row_count' => count($applied),
        ]);
    }
}
