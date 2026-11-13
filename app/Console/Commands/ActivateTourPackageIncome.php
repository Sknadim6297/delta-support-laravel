<?php

namespace App\Console\Commands;

use App\Services\TourPackageIncomeActivation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class ActivateTourPackageIncome extends Command
{
    protected $signature = 'financial:activate-tour-income
        {--database= : Exact connected database name}
        {--date= : Asia/Kolkata activation business date YYYY-MM-DD}
        {--confirm= : Type activate-tour-package-income to apply}
        {--preview : Show the planned activation without writing}';

    protected $description = 'Forward-only DST-TOUR-003 income activation: stores the activation business date and enables package flags. Never arms posting, never credits wallets, never backfills.';

    public function handle(TourPackageIncomeActivation $activation): int
    {
        $connected = (string) DB::connection()->getDatabaseName();
        $requested = trim((string) $this->option('database'));
        if ($requested === '' || $requested !== $connected) {
            $this->error('Refused. --database must exactly match the connected database ('.$connected.'). No change was made.');

            return self::FAILURE;
        }

        $date = trim((string) $this->option('date'));
        if ($date === '') {
            $this->error('Refused. --date=YYYY-MM-DD is required. No change was made.');

            return self::FAILURE;
        }

        try {
            $preview = $activation->preview($date);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->line('Connected database: '.$preview['database']);
        $this->line('Package: '.$preview['package_code']);
        $this->line('Requested activation business date: '.$preview['requested_date']);
        $this->line('Existing activation: '.($preview['existing_activation'] ?? '(none)'));
        $this->line('Terms: '.($preview['terms'] === null ? 'MISSING' : json_encode($preview['terms'])));
        $this->line($preview['duration_rule']);
        $this->line('This command never arms automation, never credits wallets, and never backfills history.');
        $this->newLine();
        $this->table(
            ['Key', 'Before', 'After', 'Action'],
            array_map(fn (array $row): array => [
                $row['key'],
                $row['before'],
                $row['after'],
                $row['needed'] ? 'WILL UPDATE' : 'already correct',
            ], $preview['planned_changes'])
        );

        if ($preview['existing_activation'] !== null && $preview['existing_activation'] !== $preview['requested_date']) {
            $this->error(
                'Refused. '.$preview['package_code'].' is already activated for '.$preview['existing_activation']
                .'. The activation date cannot be silently changed. No change was made.'
            );

            return self::FAILURE;
        }

        if ($preview['needed_count'] === 0) {
            $this->info('DST-TOUR-003 income activation is already complete for '.$preview['requested_date'].'.');

            return self::SUCCESS;
        }

        $confirm = (string) $this->option('confirm');
        $previewOnly = (bool) $this->option('preview') || $confirm === '';
        if ($previewOnly) {
            $this->warn('Preview only. To apply, repeat with --confirm='.TourPackageIncomeActivation::CONFIRM);
            $this->line('Example: php artisan financial:activate-tour-income --database='.$connected.' --date='.$date.' --confirm='.TourPackageIncomeActivation::CONFIRM);

            return self::SUCCESS;
        }

        if ($confirm !== TourPackageIncomeActivation::CONFIRM) {
            $this->error('Refused. Repeat with --confirm='.TourPackageIncomeActivation::CONFIRM.'. No change was made.');

            return self::FAILURE;
        }

        try {
            $result = $activation->activate($date);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Activated '.$preview['package_code'].' from '.$result['activation_business_date'].' Asia/Kolkata forward-only.');
        $this->table(
            ['Key', 'Before', 'After'],
            array_map(fn (array $row): array => [$row['key'], $row['before'], $row['after']], $result['applied'])
        );
        $this->line('Next: php artisan financial:audit-tour-purchases --database='.$connected);
        $this->line('Next: php artisan financial:daily --dry-run --date='.$result['activation_business_date']);
        $this->warn('No historical backfill was performed. Automation arm state was not changed.');

        return self::SUCCESS;
    }
}
