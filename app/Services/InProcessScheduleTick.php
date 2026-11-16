<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Runs due financial/support schedule jobs in-process via Artisan::call.
 * Intended for Windows Plesk hosts where Symfony Process / proc_open is unavailable.
 *
 * Does not use schedule:run, Symfony Process, shell_exec, exec, or proc_open.
 *
 * Recover schedule note:
 * Laravel registers financial:recover as hourlyAt(25) Asia/Kolkata.
 * schedule:list without --timezone may show minute 55 (UTC). That is the same
 * Kolkata :25 slot, not a different production schedule.
 */
class InProcessScheduleTick
{
    public const MODE_VERIFY = 'verify';

    public const MODE_DRY_RUN = 'dry-run';

    public const MODE_LIVE = 'live';

    /**
     * Commands that can credit wallets when automation is armed.
     *
     * @var list<string>
     */
    public const FINANCIAL_POSTING_COMMANDS = [
        'financial:daily',
        'financial:recover',
    ];

    /**
     * Persistent verification log for every tick (including verify/dry-run).
     * Readable via Plesk File Manager under storage/logs.
     */
    public function verificationLogPath(): string
    {
        $configured = config('deltasupport.scheduler.verification_log');
        $relative = is_string($configured) && trim($configured) !== ''
            ? trim($configured)
            : 'logs/scheduler-tick-verify.log';

        $relative = str_replace('\\', '/', ltrim($relative, '/\\'));

        return storage_path($relative);
    }

    /**
     * @return list<array{
     *   command: string,
     *   due: bool,
     *   ran: bool,
     *   skipped: ?string,
     *   exit_code: ?int,
     *   error: ?string
     * }>
     */
    public function run(string $mode = self::MODE_DRY_RUN, ?CarbonImmutable $now = null): array
    {
        $mode = $this->normalizeMode($mode);
        $timezone = (string) config('deltasupport.package_income.timezone', 'Asia/Kolkata');
        $now ??= CarbonImmutable::now($timezone);
        $now = $now->timezone($timezone);
        $scheduleTime = (string) config('deltasupport.package_income.schedule_time', '00:10');
        $results = [];

        foreach ($this->jobs($now, $scheduleTime) as $job) {
            $results[] = $this->processJob($job, $mode, $now, $timezone);
        }

        $this->writeVerificationLog($mode, $now, $timezone, $results);

        return $results;
    }

    /**
     * @param  list<array{command: string, due: bool, ran: bool, skipped: ?string, exit_code: ?int, error: ?string}>  $results
     */
    public function writeVerificationLog(string $mode, CarbonImmutable $now, string $timezone, array $results): string
    {
        $path = $this->verificationLogPath();
        $directory = dirname($path);
        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $dueCount = count(array_filter($results, fn (array $row): bool => $row['due']));
        $ranCount = count(array_filter($results, fn (array $row): bool => $row['ran']));
        $lines = [];
        $lines[] = sprintf(
            '[%s %s] tick mode=%s due=%d ran=%d jobs=%d',
            $now->toDateTimeString(),
            $timezone,
            $mode,
            $dueCount,
            $ranCount,
            count($results)
        );
        foreach ($results as $row) {
            $lines[] = sprintf(
                '  job=%s due=%s ran=%s skip=%s exit=%s error=%s',
                $row['command'],
                $row['due'] ? 'yes' : 'no',
                $row['ran'] ? 'yes' : 'no',
                $row['skipped'] === null || $row['skipped'] === '' ? '-' : str_replace(["\r", "\n"], ' ', $row['skipped']),
                $row['exit_code'] === null ? '-' : (string) $row['exit_code'],
                $row['error'] === null || $row['error'] === '' ? '-' : str_replace(["\r", "\n"], ' ', $row['error'])
            );
        }
        $lines[] = '';

        file_put_contents($path, implode(PHP_EOL, $lines), FILE_APPEND);
        Log::info('Scheduler tick verification recorded.', [
            'mode' => $mode,
            'at' => $now->toDateTimeString(),
            'timezone' => $timezone,
            'due' => $dueCount,
            'ran' => $ranCount,
            'log' => $path,
        ]);

        return $path;
    }

    /**
     * @return list<array{command: string, due: bool, lock: string, lock_seconds: int, args: array<string, mixed>, financial: bool}>
     */
    public function jobs(CarbonImmutable $now, string $scheduleTime): array
    {
        return [
            [
                'command' => 'financial:daily',
                'due' => $now->format('H:i') === $scheduleTime,
                'lock' => 'inprocess-financial-daily',
                'lock_seconds' => 120 * 60,
                'args' => [],
                'financial' => true,
            ],
            [
                // Authoritative: hourlyAt(25) Asia/Kolkata. UTC listings may show minute 55.
                'command' => 'financial:recover',
                'due' => (int) $now->format('i') === 25,
                'lock' => 'inprocess-financial-recover',
                'lock_seconds' => 30 * 60,
                'args' => [],
                'financial' => true,
            ],
            [
                'command' => 'ranks:evaluate',
                'due' => $now->format('H:i') === '00:20',
                'lock' => 'inprocess-ranks-evaluate',
                'lock_seconds' => 30 * 60,
                'args' => [],
                'financial' => false,
            ],
            [
                'command' => 'income:eligibility',
                'due' => ((int) $now->format('i') % 2) === 0,
                'lock' => 'inprocess-income-eligibility',
                'lock_seconds' => 10 * 60,
                'args' => [],
                'financial' => false,
            ],
            [
                'command' => 'rewards:evaluate',
                'due' => ((int) $now->format('i') % 2) === 0,
                'lock' => 'inprocess-rewards-evaluate',
                'lock_seconds' => 20 * 60,
                'args' => [],
                'financial' => false,
            ],
        ];
    }

    public function normalizeMode(string $mode): string
    {
        $mode = strtolower(trim($mode));

        return match ($mode) {
            self::MODE_LIVE, 'live' => self::MODE_LIVE,
            self::MODE_VERIFY, 'verify', 'verify-only' => self::MODE_VERIFY,
            default => self::MODE_DRY_RUN,
        };
    }

    /**
     * Effect of a live tick when prior business dates missed posting:
     * - financial:daily only posts "today" Asia/Kolkata when due at schedule_time.
     * - financial:recover may retry yesterday and/or today after schedule_time.
     * - Older gaps (before yesterday) are never auto-backfilled by this tick.
     * - Enabling the tick does not itself credit wallets until a due financial job runs
     *   while automation is armed and emergency stop is off.
     *
     * @return array{daily_posts: string, recover_scope: string, older_gaps: string}
     */
    public function missedDateEffectSummary(): array
    {
        return [
            'daily_posts' => 'Only the current Asia/Kolkata business date at schedule_time.',
            'recover_scope' => 'Yesterday (if not succeeded) and today after schedule_time (if not succeeded).',
            'older_gaps' => 'Not processed by the tick. Require a separate approved recovery.',
        ];
    }

    /**
     * @param  array{command: string, due: bool, lock: string, lock_seconds: int, args: array<string, mixed>, financial: bool}  $job
     * @return array{command: string, due: bool, ran: bool, skipped: ?string, exit_code: ?int, error: ?string}
     */
    private function processJob(array $job, string $mode, CarbonImmutable $now, string $timezone): array
    {
        if (! $job['due']) {
            return [
                'command' => $job['command'],
                'due' => false,
                'ran' => false,
                'skipped' => 'not due at '.$now->toDateTimeString().' '.$timezone,
                'exit_code' => null,
                'error' => null,
            ];
        }

        if ($mode === self::MODE_VERIFY || $mode === self::MODE_DRY_RUN) {
            return [
                'command' => $job['command'],
                'due' => true,
                'ran' => false,
                'skipped' => $mode === self::MODE_VERIFY
                    ? 'verify-only: execution disabled'
                    : 'dry-run preview only',
                'exit_code' => null,
                'error' => null,
            ];
        }

        if ($job['financial'] && ! $this->financialExecutionAllowed()) {
            return [
                'command' => $job['command'],
                'due' => true,
                'ran' => false,
                'skipped' => 'live mode blocked: DELTASUPPORT_SCHEDULER_ALLOW_FINANCIAL is not enabled',
                'exit_code' => null,
                'error' => null,
            ];
        }

        $minuteKey = 'inprocess-ran:'.$job['command'].':'.$now->format('YmdHi');
        if (! Cache::add($minuteKey, 1, 120)) {
            return [
                'command' => $job['command'],
                'due' => true,
                'ran' => false,
                'skipped' => 'duplicate tick for this Asia/Kolkata minute',
                'exit_code' => null,
                'error' => null,
            ];
        }

        $lock = Cache::lock($job['lock'], $job['lock_seconds']);
        if (! $lock->get()) {
            return [
                'command' => $job['command'],
                'due' => true,
                'ran' => false,
                'skipped' => 'overlap lock held',
                'exit_code' => null,
                'error' => null,
            ];
        }

        try {
            $code = Artisan::call($job['command'], $job['args']);
            $output = Artisan::output();
            Log::info('In-process schedule tick ran '.$job['command'], [
                'exit_code' => $code,
                'timezone' => $timezone,
                'at' => $now->toDateTimeString(),
                'mode' => $mode,
            ]);
            $this->appendLog($job['command'], $output, null);

            return [
                'command' => $job['command'],
                'due' => true,
                'ran' => true,
                'skipped' => null,
                'exit_code' => $code,
                'error' => null,
            ];
        } catch (Throwable $exception) {
            Log::error('In-process schedule tick failed '.$job['command'].': '.$exception->getMessage(), [
                'timezone' => $timezone,
                'at' => $now->toDateTimeString(),
            ]);
            $this->appendLog($job['command'], '', $exception->getMessage());

            return [
                'command' => $job['command'],
                'due' => true,
                'ran' => false,
                'skipped' => 'exception caught; remaining jobs continue',
                'exit_code' => 1,
                'error' => $exception->getMessage(),
            ];
        } finally {
            $lock->release();
        }
    }

    private function financialExecutionAllowed(): bool
    {
        return filter_var(env('DELTASUPPORT_SCHEDULER_ALLOW_FINANCIAL', false), FILTER_VALIDATE_BOOLEAN)
            || config('deltasupport.scheduler.allow_financial', false) === true;
    }

    private function appendLog(string $command, string $output, ?string $error): void
    {
        $map = [
            'financial:daily' => 'financial-daily.log',
            'financial:recover' => 'financial-daily.log',
            'ranks:evaluate' => 'financial-daily.log',
            'income:eligibility' => 'income-eligibility.log',
            'rewards:evaluate' => 'rewards-evaluate.log',
        ];
        $file = $map[$command] ?? 'scheduler-tick.log';
        $path = storage_path('logs/'.$file);
        $directory = dirname($path);
        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }
        $body = $output;
        if ($error !== null && $error !== '') {
            $body .= ($body === '' ? '' : PHP_EOL).'ERROR: '.$error;
        }
        file_put_contents(
            $path,
            '['.CarbonImmutable::now('Asia/Kolkata')->toDateTimeString().' Asia/Kolkata] '.$command.PHP_EOL.$body.PHP_EOL,
            FILE_APPEND
        );
    }
}
