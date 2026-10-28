<?php

namespace App\Services;

use App\Models\Investment;
use App\Models\Member;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * One coordinated accelerated financial cycle:
 * ROI -> Level Commission -> Rank (all for the same simulated business date) -> verification -> checkpoint.
 *
 * Every stage is idempotent (unique ROI/commission keys, one-step rank reconciliation), so a failed or
 * interrupted cycle is simply re-run for the same date. The checkpoint only advances after verification.
 */
class FinancialCycleService
{
    public const STAGES = ['roi' => 'roi:generate', 'rank' => 'rank:advance'];

    public const STATUS_SKIPPED_LOCKED = 'skipped_locked';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    /** @var resource|null */
    private $lockHandle = null;

    public function __construct(private readonly FinancialCycleCheckpoint $checkpoint)
    {
    }

    public static function make(): self
    {
        return new self(new FinancialCycleCheckpoint(config('financial.accelerated.checkpoint_path')));
    }

    /**
     * @param  ?string  $forcedDate  Re-run a specific date without touching the checkpoint.
     * @param  ?string  $failAfter  Test hook (non-production only): throw after the named stage.
     */
    public function run(?string $forcedDate = null, ?string $failAfter = null): array
    {
        $startedAt = microtime(true);
        $this->assertSafeToRun();

        if (! $this->acquireLock()) {
            $this->log('Cycle skipped: a previous cycle is still running.');

            return ['status' => self::STATUS_SKIPPED_LOCKED];
        }

        $queryCount = 0;
        DB::listen(function () use (&$queryCount): void {
            $queryCount++;
        });

        $state = $this->checkpoint->load();
        $date = null;
        $stage = 'date_resolution';
        $stageResults = [];

        try {
            $date = $forcedDate !== null
                ? CarbonImmutable::parse($forcedDate, 'Asia/Kolkata')->toDateString()
                : $this->resolveNextDate($state);

            if ($forcedDate === null && ($state['status'] ?? null) === 'running') {
                $this->log('Resuming interrupted cycle.', ['date' => $date, 'interrupted_stage' => $state['current_stage'] ?? null]);
            }

            $this->log('Cycle started.', ['date' => $date, 'forced' => $forcedDate !== null]);
            if ($forcedDate === null) {
                $this->saveState($state, $date, 'running', 'roi');
            }

            foreach (self::STAGES as $name => $command) {
                $stage = $name;
                if ($forcedDate === null) {
                    $this->saveState($state, $date, 'running', $name);
                }

                $stageStartedAt = microtime(true);
                $arguments = ['--date' => $date];
                $output = new BufferedOutput;
                $exitCode = Artisan::call($command, $arguments, $output);
                $stageResults[$name] = [
                    'exit_code' => $exitCode,
                    'seconds' => round(microtime(true) - $stageStartedAt, 3),
                    'output' => trim($output->fetch()),
                ];
                $this->log("Stage {$name} finished.", ['date' => $date] + $stageResults[$name]);

                if ($exitCode !== 0) {
                    throw new RuntimeException("Stage {$name} failed with exit code {$exitCode}.");
                }

                if ($failAfter === $name && ! app()->environment('production')) {
                    throw new RuntimeException("Simulated failure after stage {$name}.");
                }
            }

            $stage = 'verification';
            $verification = $this->verify($date);
            $this->log('Verification passed.', ['date' => $date] + $verification);

            $summary = [
                'status' => self::STATUS_COMPLETED,
                'date' => $date,
                'stages' => $stageResults,
                'verification' => $verification,
                'seconds' => round(microtime(true) - $startedAt, 3),
                'queries' => $queryCount,
                'peak_memory_mb' => round(memory_get_peak_usage(true) / 1048576, 1),
            ];

            if ($forcedDate === null) {
                $next = CarbonImmutable::parse($date, 'Asia/Kolkata')->addDay()->toDateString();
                $this->checkpoint->save([
                    'next_business_date' => $next,
                    'last_completed_date' => $date,
                    'status' => 'idle',
                    'current_stage' => null,
                    'completed_cycles' => (int) ($state['completed_cycles'] ?? 0) + 1,
                    'last_cycle_seconds' => $summary['seconds'],
                    'last_error' => null,
                ]);
            }

            $this->log('Cycle completed.', ['date' => $date, 'seconds' => $summary['seconds'], 'queries' => $queryCount, 'peak_memory_mb' => $summary['peak_memory_mb']]);

            return $summary;
        } catch (\Throwable $exception) {
            $this->log('Cycle failed; checkpoint not advanced.', [
                'date' => $date,
                'stage' => $stage,
                'error' => $exception->getMessage(),
                'seconds' => round(microtime(true) - $startedAt, 3),
            ]);

            if ($forcedDate === null && $date !== null) {
                $this->checkpoint->save([
                    'next_business_date' => $date,
                    'last_completed_date' => $state['last_completed_date'] ?? null,
                    'status' => 'failed',
                    'current_stage' => $stage,
                    'completed_cycles' => (int) ($state['completed_cycles'] ?? 0),
                    'last_error' => $exception->getMessage(),
                ]);
            }

            return ['status' => self::STATUS_FAILED, 'date' => $date, 'stage' => $stage, 'error' => $exception->getMessage(), 'stages' => $stageResults];
        } finally {
            $this->releaseLock();
        }
    }

    public function resolveNextDate(?array $state): string
    {
        if ($state !== null) {
            return CarbonImmutable::parse($state['next_business_date'], 'Asia/Kolkata')->toDateString();
        }

        if ($configured = config('financial.accelerated.start_date')) {
            return CarbonImmutable::parse($configured, 'Asia/Kolkata')->toDateString();
        }

        $first = Investment::query()->min('created_at');
        if ($first === null) {
            throw new RuntimeException('No investments exist; cannot resolve a simulated business date.');
        }

        return CarbonImmutable::parse($first, 'UTC')->setTimezone('Asia/Kolkata')->startOfDay()->addDay()->toDateString();
    }

    /**
     * Refuses to run unless accelerated mode is explicitly enabled outside production and every member is synthetic.
     */
    public function assertSafeToRun(): void
    {
        if (! config('financial.accelerated.enabled')) {
            throw new RuntimeException('Accelerated financial cycle is disabled (FINANCIAL_ACCELERATED_TESTING=false).');
        }

        if (app()->environment('production')) {
            throw new RuntimeException('Accelerated financial cycle is never allowed in production.');
        }

        $prefix = (string) config('financial.accelerated.synthetic_member_prefix');
        $escaped = addcslashes($prefix, '\\%_');
        if (Member::query()->where('member_id', 'not like', $escaped . '%')->exists()) {
            throw new RuntimeException("Refusing to run accelerated payouts: non-synthetic members exist (member_id without prefix '{$prefix}').");
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function verify(string $date): array
    {
        $startOfDay = CarbonImmutable::parse($date, 'Asia/Kolkata')->startOfDay()->setTimezone('UTC')->toDateTimeString();

        $missingRoi = (int) DB::table('investments')
            ->where('status', 'active')
            ->where('created_at', '<', $startOfDay)
            ->whereNotExists(function ($query) use ($date): void {
                $query->selectRaw('1')->from('roi_transactions')
                    ->whereColumn('roi_transactions.investment_id', 'investments.investment_id')
                    ->where('roi_transactions.roi_date', $date);
            })
            ->count();
        if ($missingRoi > 0) {
            throw new RuntimeException("Verification failed: {$missingRoi} eligible investments have no ROI for {$date}.");
        }

        $overCap = (int) DB::table('investments')
            ->whereRaw('(COALESCE((SELECT SUM(income_amount) FROM roi_transactions r WHERE r.investment_id = investments.investment_id), 0)'
                . ' + COALESCE((SELECT SUM(income_amount) FROM level_commission_transactions l WHERE l.investment_id = investments.investment_id), 0)) > amount * 3')
            ->count();
        if ($overCap > 0) {
            throw new RuntimeException("Verification failed: {$overCap} investments exceed the 300% combined income cap.");
        }

        $counts = [
            'roi_transactions_for_date' => DB::table('roi_transactions')->where('roi_date', $date)->count(),
            'commission_transactions_for_date' => DB::table('level_commission_transactions')->where('business_date', $date)->count(),
            'rank_achievements' => DB::table('rank_achievements')->count(),
        ];

        $walletsComparable = ! DB::table('investment_withdrawals')->exists() && ! DB::table('activation_wallet_transactions')->exists();
        if ($walletsComparable) {
            $roiWallet = (string) DB::table('members')->sum('roi_wallet_amount');
            $roiTotal = (string) DB::table('roi_transactions')->sum('income_amount');
            $workingWallet = (string) DB::table('members')->sum('working_wallet_amount');
            $commissionTotal = (string) DB::table('level_commission_transactions')->sum('income_amount');
            if (bccomp($roiWallet, $roiTotal, 4) !== 0 || bccomp($workingWallet, $commissionTotal, 4) !== 0) {
                throw new RuntimeException("Verification failed: wallet totals do not reconcile (roi {$roiWallet} vs {$roiTotal}, working {$workingWallet} vs {$commissionTotal}).");
            }
            $counts['wallets_reconciled'] = true;
        }

        return $counts;
    }

    private function saveState(?array $state, string $date, string $status, string $stage): void
    {
        $this->checkpoint->save([
            'next_business_date' => $date,
            'last_completed_date' => $state['last_completed_date'] ?? null,
            'status' => $status,
            'current_stage' => $stage,
            'completed_cycles' => (int) ($state['completed_cycles'] ?? 0),
            'last_error' => $state['last_error'] ?? null,
        ]);
    }

    private function acquireLock(): bool
    {
        $path = config('financial.accelerated.lock_path');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }

        $handle = fopen($path, 'c');
        if ($handle === false) {
            throw new RuntimeException('Cannot open the financial cycle lock file.');
        }

        // The OS releases this lock if the process dies, so a crashed cycle never leaves a stale lock.
        if (! flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);

            return false;
        }

        $this->lockHandle = $handle;

        return true;
    }

    private function releaseLock(): void
    {
        if ($this->lockHandle !== null) {
            flock($this->lockHandle, LOCK_UN);
            fclose($this->lockHandle);
            $this->lockHandle = null;
        }
    }

    private function log(string $message, array $context = []): void
    {
        $line = '[' . now('Asia/Kolkata')->format('Y-m-d H:i:s') . '] ' . $message . ($context === [] ? '' : ' ' . json_encode($context, JSON_UNESCAPED_SLASHES)) . PHP_EOL;
        @file_put_contents(config('financial.accelerated.log_path'), $line, FILE_APPEND | LOCK_EX);
    }
}
