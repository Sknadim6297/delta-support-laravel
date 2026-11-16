<?php

namespace App\Console\Commands;

use App\Services\InProcessScheduleTick;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RunInProcessScheduleTick extends Command
{
    protected $signature = 'deltasupport:scheduler-tick
        {--dry-run : Show which jobs would be due without executing them}
        {--verify-only : Non-financial verification mode; never executes any scheduled command}
        {--live : Execute due jobs (financial jobs still require DELTASUPPORT_SCHEDULER_ALLOW_FINANCIAL=true)}
        {--force= : Comma-separated commands for local non-production testing only}';

    protected $description = 'Run due Asia/Kolkata schedule jobs in-process without proc_open/schedule:run. Default is dry-run.';

    public function handle(InProcessScheduleTick $tick): int
    {
        $timezone = (string) config('deltasupport.package_income.timezone', 'Asia/Kolkata');
        $now = CarbonImmutable::now($timezone);
        $db = (string) DB::connection()->getDatabaseName();

        $this->line('Asia/Kolkata now: '.$now->toDateTimeString());
        $this->line('Connected database: '.$db);
        $this->line('This command does not use Symfony Process, shell_exec, exec, or proc_open.');
        $this->line('Recover due minute is :25 Asia/Kolkata (UTC listings may show :55).');

        $force = trim((string) $this->option('force'));
        if ($force !== '') {
            if ($this->isProductionDatabase($db)) {
                $this->error('Refused. --force is blocked on production database names. No command was run.');

                return self::FAILURE;
            }
            foreach (array_filter(array_map('trim', explode(',', $force))) as $command) {
                if (in_array($command, InProcessScheduleTick::FINANCIAL_POSTING_COMMANDS, true)) {
                    $this->error('Refused. --force cannot run financial posting commands. No command was run.');

                    return self::FAILURE;
                }
                $code = $this->call($command);
                $this->line($command.' exit='.$code);
            }

            return self::SUCCESS;
        }

        $mode = $this->resolveMode($tick);
        $this->line('Scheduler mode: '.$mode);
        if ($mode === InProcessScheduleTick::MODE_LIVE) {
            $allow = filter_var(env('DELTASUPPORT_SCHEDULER_ALLOW_FINANCIAL', false), FILTER_VALIDATE_BOOLEAN)
                || config('deltasupport.scheduler.allow_financial', false) === true;
            $this->line('Financial posting jobs allowed by env/config: '.($allow ? 'yes' : 'no (blocked)'));
        }

        $effect = $tick->missedDateEffectSummary();
        $this->line('Missed-date effect: daily='.$effect['daily_posts'].' recover='.$effect['recover_scope'].' older='.$effect['older_gaps']);

        $results = $tick->run($mode, $now);
        $this->table(
            ['Command', 'Due', 'Ran', 'Skipped', 'Exit', 'Error'],
            array_map(fn (array $row): array => [
                $row['command'],
                $row['due'] ? 'yes' : 'no',
                $row['ran'] ? 'yes' : 'no',
                (string) ($row['skipped'] ?? ''),
                $row['exit_code'] === null ? '' : (string) $row['exit_code'],
                (string) ($row['error'] ?? ''),
            ], $results)
        );

        return self::SUCCESS;
    }

    private function resolveMode(InProcessScheduleTick $tick): string
    {
        if ((bool) $this->option('verify-only')) {
            return InProcessScheduleTick::MODE_VERIFY;
        }
        if ((bool) $this->option('live')) {
            return InProcessScheduleTick::MODE_LIVE;
        }
        if ((bool) $this->option('dry-run')) {
            return InProcessScheduleTick::MODE_DRY_RUN;
        }

        $fromEnv = (string) env('DELTASUPPORT_SCHEDULER_MODE', InProcessScheduleTick::MODE_DRY_RUN);

        return $tick->normalizeMode($fromEnv);
    }

    private function isProductionDatabase(string $db): bool
    {
        $approved = trim((string) config('deltasupport.financial_approved_database', ''));

        return strcasecmp($db, 'Delta_support_db') === 0
            || strcasecmp($db, 'delta_support_db') === 0
            || ($approved !== '' && strcasecmp($db, $approved) === 0);
    }
}
