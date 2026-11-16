<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Stage B controlled recovery entrypoint. Does not widen date windows.
 * Calls financial:recover only after an explicit confirm token.
 * Never backfills dates older than yesterday.
 */
class ApplyFinancialRecovery extends Command
{
    public const CONFIRM = 'apply-financial-recovery';

    protected $signature = 'financial:recovery-apply
        {--database= : Exact connected database name}
        {--confirm= : Type apply-financial-recovery to execute Stage B}
        {--preview : Show the recovery-preview report only}';

    protected $description = 'Stage B controlled recovery: requires confirm, then runs financial:recover (yesterday/today only). No historical backfill.';

    public function handle(): int
    {
        $connected = (string) DB::connection()->getDatabaseName();
        $requested = trim((string) $this->option('database'));
        if ($requested === '' || $requested !== $connected) {
            $this->error('Refused. --database must exactly match the connected database ('.$connected.'). No wallet was credited.');

            return self::FAILURE;
        }

        $this->line('Connected database: '.$connected);
        $this->line('Stage B uses financial:recover only (yesterday if unfinished, today after schedule_time).');
        $this->line('Older dates are never credited by this command.');
        $this->line('Tour posting still requires DELTASUPPORT_TOUR_INCOME_POSTING_AUTHORIZED=true after written approval.');

        Artisan::call('financial:recovery-preview', ['--days' => 7]);
        $this->output->write(Artisan::output());

        if ((bool) $this->option('preview') || trim((string) $this->option('confirm')) === '') {
            $this->warn('Preview only. To apply Stage B: --confirm='.self::CONFIRM);
            $this->warn('No wallet was credited.');

            return self::SUCCESS;
        }

        if (trim((string) $this->option('confirm')) !== self::CONFIRM) {
            $this->error('Refused. Repeat with --confirm='.self::CONFIRM.'. No wallet was credited.');

            return self::FAILURE;
        }

        $code = Artisan::call('financial:recover');
        $this->output->write(Artisan::output());
        if ($code !== 0) {
            $this->error('Stage B recovery finished with exit code '.$code.'.');

            return self::FAILURE;
        }

        $this->info('Stage B financial:recover completed.');

        return self::SUCCESS;
    }
}
