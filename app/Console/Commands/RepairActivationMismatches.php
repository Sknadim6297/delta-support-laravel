<?php

namespace App\Console\Commands;

use App\Services\ProductionActivationMismatchRepair;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class RepairActivationMismatches extends Command
{
    protected $signature = 'financial:repair-activation-mismatches
        {--database= : Exact connected database name}
        {--confirm= : Type repair-activation-mismatches to apply}
        {--preview : Show the planned changes without writing}';

    protected $description = 'Preview or apply member_transfer_settings inactive-plan fix only. DST-TOUR-003 income uses financial:activate-tour-income. Does not arm posting or credit wallets.';

    public function handle(ProductionActivationMismatchRepair $repair): int
    {
        $connected = (string) DB::connection()->getDatabaseName();
        $requested = trim((string) $this->option('database'));
        if ($requested === '' || $requested !== $connected) {
            $this->error('Refused. --database must exactly match the connected database ('.$connected.'). No change was made.');

            return self::FAILURE;
        }

        try {
            $preview = $repair->preview();
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->line('Connected database: '.$connected);
        $this->line('This command never arms automation and never credits wallets.');
        $this->newLine();
        $this->table(
            ['Table', 'Key', 'Before', 'After', 'Action'],
            array_map(fn (array $change): array => [
                $change['table'],
                $change['key'],
                $change['before'],
                $change['after'],
                $change['needed'] ? 'WILL UPDATE' : 'already correct',
            ], $preview['changes'])
        );

        if ($preview['tour_income_config_missing']) {
            $this->warn('DST-TOUR-003 package_income config is missing from the deployed map.');
        }
        $this->line('Tour income flags/date are not changed here. Use financial:activate-tour-income after written approval.');

        if ($preview['needed_count'] === 0) {
            $this->info('No activation-mismatch repairs are needed.');

            return self::SUCCESS;
        }

        $confirm = (string) $this->option('confirm');
        $previewOnly = (bool) $this->option('preview') || $confirm === '';
        if ($previewOnly) {
            $this->warn('Preview only. To apply, repeat with --confirm='.ProductionActivationMismatchRepair::CONFIRM);
            $this->line('Example: php artisan financial:repair-activation-mismatches --database='.$connected.' --confirm='.ProductionActivationMismatchRepair::CONFIRM);

            return self::SUCCESS;
        }

        if ($confirm !== ProductionActivationMismatchRepair::CONFIRM) {
            $this->error('Refused. Repeat with --confirm='.ProductionActivationMismatchRepair::CONFIRM.'. No change was made.');

            return self::FAILURE;
        }

        try {
            $result = $repair->apply();
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Applied '.$result['needed_count'].' repair'.($result['needed_count'] === 1 ? '' : 's').'.');
        $this->table(
            ['Table', 'Key', 'Before', 'After'],
            array_map(fn (array $change): array => [
                $change['table'],
                $change['key'],
                $change['before'],
                $change['after'],
            ], $result['applied'])
        );
        $this->line('Next: php artisan financial:status');
        $this->line('This command never arms automation and never credits wallets.');

        return self::SUCCESS;
    }
}
