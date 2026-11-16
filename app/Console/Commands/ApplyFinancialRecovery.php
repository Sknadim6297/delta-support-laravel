<?php

namespace App\Console\Commands;

use App\Services\FinancialControls;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Stage B controlled recovery entrypoint. Does not widen date windows.
 * Calls financial:recover only after explicit confirmations.
 * Never backfills dates older than yesterday.
 */
class ApplyFinancialRecovery extends Command
{
    public const CONFIRM = 'apply-financial-recovery';

    public const BACKUP_CONFIRM = 'database-backup-confirmed';

    public const RULES_CONFIRM = 'business-rules-confirmed';

    protected $signature = 'financial:recovery-apply
        {--database= : Exact connected database name}
        {--confirm= : Type apply-financial-recovery to execute Stage B}
        {--backup-confirmed= : Type database-backup-confirmed after a verified DB backup}
        {--rules-confirmed= : Type business-rules-confirmed after written business-rule approval}
        {--preview : Show the recovery-preview report only}';

    protected $description = 'Stage B controlled recovery: requires confirm + backup + rules tokens, then runs financial:recover (yesterday/today only). No historical backfill.';

    public function handle(FinancialControls $controls): int
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
        $this->line('Idempotent retries rely on accrual unique keys and ledger references; reconcile with financial:audit-posted after apply.');

        Artisan::call('financial:recovery-preview', ['--days' => 7, '--detailed' => true]);
        $this->output->write(Artisan::output());

        if ((bool) $this->option('preview') || trim((string) $this->option('confirm')) === '') {
            $this->warn('Preview only. Stage B requires:');
            $this->line('  --confirm='.self::CONFIRM);
            $this->line('  --backup-confirmed='.self::BACKUP_CONFIRM);
            $this->line('  --rules-confirmed='.self::RULES_CONFIRM);
            $this->warn('No wallet was credited.');

            return self::SUCCESS;
        }

        if (trim((string) $this->option('confirm')) !== self::CONFIRM) {
            $this->error('Refused. Repeat with --confirm='.self::CONFIRM.'. No wallet was credited.');

            return self::FAILURE;
        }
        if (trim((string) $this->option('backup-confirmed')) !== self::BACKUP_CONFIRM) {
            $this->error('Refused. Verify a database backup, then pass --backup-confirmed='.self::BACKUP_CONFIRM.'. No wallet was credited.');

            return self::FAILURE;
        }
        if (trim((string) $this->option('rules-confirmed')) !== self::RULES_CONFIRM) {
            $this->error('Refused. Confirm written business rules, then pass --rules-confirmed='.self::RULES_CONFIRM.'. No wallet was credited.');

            return self::FAILURE;
        }

        $mode = strtolower((string) (
            env('DELTASUPPORT_SCHEDULER_MODE')
            ?: config('deltasupport.scheduler.mode', 'dry-run')
        ));
        if (in_array($mode, ['verify', 'verify-only'], true)) {
            $this->warn('Note: scheduler mode is still '.$mode.'. This Stage B CLI call does not change scheduler mode.');
        }

        if (! $controls->automaticPostingAllowed()) {
            $this->error('Refused. Automation is not posting (unarmed or emergency stop). No wallet was credited.');

            return self::FAILURE;
        }

        $code = Artisan::call('financial:recover');
        $this->output->write(Artisan::output());
        if ($code !== 0) {
            $this->error('Stage B recovery finished with exit code '.$code.'. Run financial:audit-posted for reconciliation.');

            return self::FAILURE;
        }

        $this->info('Stage B financial:recover completed. Reconcile with financial:audit-posted and financial:recovery-preview --detailed.');

        return self::SUCCESS;
    }
}
