<?php

namespace App\Services;

use App\Models\Member;
use App\Models\PackageIncomeRun;
use App\Models\PackageLevelAccrual;
use App\Models\PackageRoiAccrual;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PackageIncomeAdministration
{
    public function __construct(
        private readonly PackageIncomePolicy $policy,
        private readonly PackageIncomeAccrual $accrual,
        private readonly CommissionPlan $plan,
        private readonly FinancialScheduleRegistrar $scheduler,
        private readonly WalletBalanceReconciliation $reconciliation,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function report(): array
    {
        $timezone = (string) config('deltasupport.package_income.timezone', 'Asia/Kolkata');
        $businessDate = CarbonImmutable::now($timezone)->toDateString();
        $schedule = new Schedule;
        $this->scheduler->register($schedule);
        $prepared = new Schedule;
        $this->scheduler->preparePackageIncome($prepared);
        $preview = $this->accrual->preview($businessDate);
        $realPreview = $this->accrual->previewReal($businessDate);
        $demonstrationPreview = $this->accrual->previewDemonstration($businessDate);
        $controls = app(FinancialControls::class);
        $dailySchedule = new Schedule;
        app(DailyFinancialScheduleRegistrar::class)->register($dailySchedule);
        $reconciliation = $this->reconciliationSummary();
        $backup = $this->backup();
        $missingSponsors = $this->missingSponsors();
        $failedJobs = $this->failedJobs();

        $checklist = $this->checklist($schedule, $reconciliation, $backup, $missingSponsors, $failedJobs);

        return [
            'business_date' => $businessDate,
            'timezone' => $timezone,
            'enabled' => $this->policy->enabled(),
            'scheduled_tasks' => count($schedule->events()),
            'schedule_time' => (string) config('deltasupport.package_income.schedule_time', '00:10'),
            'chunk_size' => (int) config('deltasupport.package_income.chunk_size', 100),
            'roi_rows' => PackageRoiAccrual::query()->count(),
            'level_rows' => PackageLevelAccrual::query()->count(),
            'last_success' => $this->latest(PackageIncomeRun::SUCCEEDED),
            'last_failure' => $this->latest(PackageIncomeRun::FAILED),
            'last_blocked' => $this->latest(PackageIncomeRun::BLOCKED),
            'last_refusal' => $this->latest(PackageIncomeRun::REFUSED),
            'pending_roi' => count($preview['roi']),
            'pending_commissions' => count($preview['commissions']),
            'real_pending_roi' => count($realPreview['roi']),
            'real_pending_commissions' => count($realPreview['commissions']),
            'real_skipped' => array_slice($realPreview['skipped'], 0, 20),
            'demonstration_pending_roi' => count($demonstrationPreview['roi']),
            'demonstration_pending_commissions' => count($demonstrationPreview['commissions']),
            'daily_cycle_registered' => count($dailySchedule->events()) > 0,
            'roi_calculation_enabled' => $controls->enabled('roi_calculation_enabled'),
            'roi_wallet_crediting_enabled' => $controls->enabled('roi_wallet_crediting_enabled'),
            'level_calculation_enabled' => $controls->enabled('level_calculation_enabled'),
            'level_wallet_crediting_enabled' => $controls->enabled('level_wallet_crediting_enabled'),
            'skipped' => array_slice($preview['skipped'], 0, 20),
            'skipped_count' => count($preview['skipped']),
            'reconciliation' => $reconciliation,
            'failed_jobs' => $failedJobs,
            'backup' => $backup,
            'missing_sponsors' => $missingSponsors,
            'checklist' => $checklist,
            'blockers' => count(array_filter($checklist, fn (array $item): bool => $item['status'] === 'blocker')),
            'scheduler' => $this->schedulerHealth($schedule, $prepared),
            'ledger_rows' => $this->ledgerRows(),
        ];
    }

    /**
     * @return array{business_date: ?string, status: string, credited_roi: int, credited_commissions: int, message: string, recorded_at: string}|null
     */
    private function latest(string $status): ?array
    {
        $run = PackageIncomeRun::query()->where('status', $status)->orderByDesc('id')->first();
        if (! $run) {
            return null;
        }

        return [
            'business_date' => $run->business_date?->toDateString(),
            'status' => $run->status,
            'credited_roi' => (int) $run->credited_roi,
            'credited_commissions' => (int) $run->credited_commissions,
            'message' => $run->message,
            'recorded_at' => $run->created_at?->timezone(config('deltasupport.package_income.timezone'))->format('d M Y, H:i') ?? '',
        ];
    }

    /**
     * @return array{members: int, variance_members: int, negative_members: int, duplicate_references: int}
     */
    private function reconciliationSummary(): array
    {
        $variance = 0;
        Member::query()->orderBy('id')->chunkById(100, function ($members) use (&$variance): void {
            foreach ($members as $member) {
                $report = $this->reconciliation->inspect($member);
                if (bccomp($report['roi']['variance'], '0', 4) !== 0 || bccomp($report['working']['variance'], '0', 4) !== 0 || $report['roi']['negative'] || $report['working']['negative']) {
                    $variance++;
                }
            }
        });
        $duplicates = $this->reconciliation->duplicateReferenceCounts();

        return [
            'members' => Member::query()->count(),
            'variance_members' => $variance,
            'negative_members' => $this->reconciliation->negativeBalanceCount(),
            'duplicate_references' => array_sum($duplicates),
        ];
    }

    /**
     * @return array{package_income: int, total: int, queued: bool}
     */
    private function failedJobs(): array
    {
        if (! Schema::hasTable('failed_jobs')) {
            return ['package_income' => 0, 'total' => 0, 'queued' => false];
        }

        return [
            'package_income' => (int) DB::table('failed_jobs')->where('payload', 'like', '%package-income%')->count(),
            'total' => (int) DB::table('failed_jobs')->count(),
            'queued' => false,
        ];
    }

    /**
     * @return array{available: bool, detail: string}
     */
    private function backup(): array
    {
        $package = class_exists(\Spatie\Backup\BackupServiceProvider::class);
        $directories = array_values(array_filter([
            storage_path('app/backups'),
            storage_path('app/backup'),
            storage_path('backups'),
        ], fn (string $directory): bool => is_dir($directory) && glob($directory.'/*') !== [] && glob($directory.'/*') !== false));

        if ($package || $directories !== []) {
            $names = array_map(fn (string $directory): string => str_replace(storage_path(), 'storage', $directory), $directories);

            return [
                'available' => true,
                'detail' => 'A backup directory contains a file: '.implode(', ', $names).'. This page does not verify that the file is current or restorable.',
            ];
        }

        return ['available' => false, 'detail' => 'No backup package or stored backup directory was found.'];
    }

    /**
     * @return array{count: int, ids: list<string>}
     */
    private function missingSponsors(): array
    {
        $root = strtoupper($this->policy->adminRootId());
        $ids = Member::query()->whereNotNull('sponsor_id')->distinct()->pluck('sponsor_id');
        $existing = Member::query()->whereIn('member_id', $ids->all())->pluck('member_id');
        $missing = $ids->diff($existing)->reject(fn (mixed $id): bool => strtoupper((string) $id) === $root)->values();

        return [
            'count' => $missing->count(),
            'ids' => $missing->take(10)->all(),
        ];
    }

    /**
     * @param  array{members: int, variance_members: int, negative_members: int, duplicate_references: int}  $reconciliation
     * @param  array{available: bool, detail: string}  $backup
     * @param  array{count: int, ids: list<string>}  $missingSponsors
     * @param  array{package_income: int, total: int, queued: bool}  $failedJobs
     * @return list<array{label: string, status: string, detail: string}>
     */
    private function checklist($schedule, array $reconciliation, array $backup, array $missingSponsors, array $failedJobs): array
    {
        $coast = $this->policy->definition('DEMO-COAST');
        $hills = $this->policy->definition('DEMO-HILLS');
        $errors = $this->plan->integrityErrors();
        $items = [];

        $items[] = $this->item(
            'Package configuration',
            $coast !== null && $hills !== null,
            'Coastal Weekend and Hill Station Retreat definitions are stored.',
            'A required package definition is missing.'
        );
        $items[] = $this->item(
            'ROI configuration',
            $coast !== null && $hills !== null && $coast['roi_daily'] === '0.2500' && $coast['roi_days'] === 800 && $hills['roi_daily'] === '2.5000' && $hills['roi_days'] === 1000,
            'Coastal Weekend is 0.2500 for 800 days. Hill Station Retreat is 2.5000 for 1000 days.',
            'A daily ROI amount or duration does not match the approved rule.'
        );
        $items[] = $this->item(
            '20-level commission rates',
            $errors === [],
            'The stored 20-level chart matches the approved rates and stays inactive.',
            implode(' ', $errors)
        );
        $items[] = $this->item(
            'VIP 400-day duration',
            (int) config('deltasupport.package_income.vip_commission_days') === 400
                && $hills !== null
                && $hills['commission_days'] === 400
                && $coast !== null
                && $coast['commission_days'] === 400
                && $hills['commission_pool'] === '2.4000',
            'Both packages use a 400-day commission duration. The VIP pool is 2.4000.',
            'The VIP or Coastal commission duration is not 400 days.'
        );
        $items[] = $this->item(
            'Production generation',
            false,
            '',
            'Package income generation is disabled. This page cannot turn it on.'
        );
        $items[] = $this->item(
            'Scheduler registration',
            count($schedule->events()) > 0,
            'A financial task is registered.',
            'No financial task is registered. package-income:accrue is prepared for '.$this->reportTime().' '.$this->timezoneLabel().' and is not on the scheduler.'
        );
        $items[] = $this->item(
            'Sponsor records',
            $missingSponsors['count'] === 0,
            'Every other sponsor id matches a member. '.$this->policy->adminRootId().' is the configured admin root. It is a valid terminal sponsor, has no member record, and receives no commission.',
            $missingSponsors['count'].' sponsor '.($missingSponsors['count'] === 1 ? 'id has' : 'ids have').' no member record: '.implode(', ', $missingSponsors['ids']).'.'
        );
        $items[] = $this->item(
            'Wallet debit ledger',
            $reconciliation['variance_members'] === 0 && $reconciliation['negative_members'] === 0,
            'Stored ledger rows: '.$this->ledgerRows().'. ROI and Working debits in that table are included. No historical debit was created. Manual posting and withdrawals stay disabled.',
            'A stored balance differs from recorded credits minus stored ledger debits, or a balance is negative. No historical debit was created.'
        );
        $items[] = $this->item(
            'Wallet credit match',
            $reconciliation['variance_members'] === 0 && $reconciliation['negative_members'] === 0,
            $reconciliation['members'].' members match recorded ROI and Working credits minus stored ledger debits. No historical debit was created.',
            $reconciliation['variance_members'].' member balance'.($reconciliation['variance_members'] === 1 ? '' : 's').' differ from recorded credits or are negative.'
        );
        $items[] = $this->item(
            'Duplicate protection',
            $reconciliation['duplicate_references'] === 0 && $this->uniqueIndex('package_roi_accruals', 'package_roi_purchase_date_unique') && $this->uniqueIndex('package_level_accruals', 'package_level_purchase_member_date_unique'),
            'Accrual tables have unique purchase and date indexes, and no duplicate legacy references were found.',
            'A duplicate reference or a missing accrual unique index was found.'
        );
        $items[] = $this->item(
            'Backup availability',
            $backup['available'],
            $backup['detail'],
            $backup['detail']
        );
        $items[] = $this->item(
            'Failed package-income jobs',
            $failedJobs['package_income'] === 0,
            'Package income is not queued. Failed queue jobs that mention it: 0. Total failed jobs: '.$failedJobs['total'].'.',
            $failedJobs['package_income'].' failed queue jobs mention package income.'
        );
        $items[] = $this->item(
            'Automated test results',
            false,
            '',
            'Automated test results are not stored in the database.'
        );

        return $items;
    }

    /**
     * @return array{label: string, status: string, detail: string}
     */
    private function item(string $label, bool $ready, string $readyDetail, string $blockerDetail): array
    {
        return [
            'label' => $label,
            'status' => $ready ? 'ready' : 'blocker',
            'detail' => $ready ? $readyDetail : $blockerDetail,
        ];
    }

    private function uniqueIndex(string $table, string $name): bool
    {
        if (! Schema::hasTable($table)) {
            return false;
        }

        foreach (Schema::getIndexes($table) as $index) {
            if (($index['name'] ?? '') === $name && ($index['unique'] ?? false)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{registered_events: int, command: string, expression: string, timezone: string, without_overlapping: bool, on_one_server: bool, overlap_minutes: int, enabled: bool, one_date: bool, backfill: bool}
     */
    private function schedulerHealth(Schedule $registered, Schedule $prepared): array
    {
        $event = $prepared->events()[0] ?? null;

        return [
            'registered_events' => count($registered->events()),
            'command' => $event ? (string) $event->command : '',
            'expression' => (string) ($event->expression ?? ''),
            'timezone' => (string) ($event->timezone ?? ''),
            'without_overlapping' => (bool) ($event->withoutOverlapping ?? false),
            'on_one_server' => (bool) ($event->onOneServer ?? false),
            'overlap_minutes' => 120,
            'enabled' => $this->policy->enabled(),
            'one_date' => true,
            'backfill' => false,
        ];
    }

    private function ledgerRows(): int
    {
        if (! Schema::hasTable('wallet_ledger_entries')) {
            return 0;
        }

        return (int) DB::table('wallet_ledger_entries')->count();
    }

    private function reportTime(): string
    {
        return (string) config('deltasupport.package_income.schedule_time', '00:10');
    }

    private function timezoneLabel(): string
    {
        return (string) config('deltasupport.package_income.timezone', 'Asia/Kolkata');
    }
}
