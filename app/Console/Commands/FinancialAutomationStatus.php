<?php

namespace App\Console\Commands;

use App\Models\FinancialControl;
use App\Models\PackageIncomeRun;
use App\Services\FinancialActivationGuard;
use App\Services\FinancialControls;
use App\Services\ProductionDatabaseTarget;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FinancialAutomationStatus extends Command
{
    protected $signature = 'financial:status';

    protected $description = 'Show why automatic ROI posting will or will not credit wallets. This writes nothing.';

    public function handle(FinancialControls $controls, FinancialActivationGuard $guard): int
    {
        $timezone = (string) config('deltasupport.package_income.timezone', 'Asia/Kolkata');
        $scheduleTime = (string) config('deltasupport.package_income.schedule_time', '00:10');
        $now = CarbonImmutable::now($timezone);
        $db = (string) DB::connection()->getDatabaseName();
        $approved = trim((string) config('deltasupport.financial_approved_database', ''));
        $row = FinancialControl::current();
        $today = $now->toDateString();
        $last = PackageIncomeRun::query()->latest('id')->first();
        $markerCount = Schema::hasTable('production_initializations')
            ? (int) DB::table('production_initializations')->where('marker', FinancialActivationGuard::MARKER)->count()
            : 0;

        $this->table(['Check', 'Value'], [
            ['Connected database', $db],
            ['FINANCIAL_APPROVED_DATABASE', $approved === '' ? '(empty)' : $approved],
            ['Approved name matches', $approved !== '' && $approved === $db ? 'yes' : 'no'],
            ['Initialization marker rows', (string) $markerCount],
            ['Automation armed', $controls->automationArmed() ? 'yes' : 'no'],
            ['Emergency stop', $controls->emergencyStopped() ? 'yes' : 'no'],
            ['Posting allowed', $controls->automaticPostingAllowed() ? 'yes' : 'no'],
            ['Schedule time', $scheduleTime.' '.$timezone],
            ['Current business date', $today],
            ['financial_controls.armed column', ((int) $row->financial_automation_armed) === 1 ? '1' : '0'],
            ['Last income run', $last ? $last->business_date?->toDateString().' / '.$last->status.' / '.$last->message : 'none'],
            ['financial-daily.log', is_file(storage_path('logs/financial-daily.log')) ? 'present' : 'missing'],
        ]);

        if (! $controls->automaticPostingAllowed()) {
            $this->warn('Scheduler may be running, but wallets will not be credited until automation is armed and the emergency stop is off.');
        } else {
            $this->info('Posting is allowed. financial:daily at '.$scheduleTime.' '.$timezone.' can credit eligible purchases.');
        }

        $protected = app(ProductionDatabaseTarget::class)->isProtected($db);
        $this->line('Protected development database: '.($protected ? 'yes' : 'no'));

        $planIssues = $guard->inactivePlanIssues();
        if ($planIssues === []) {
            $this->info('Approved inactive plan: satisfied.');
        } else {
            $this->warn('Approved inactive plan mismatches:');
            foreach ($planIssues as $issue) {
                $this->line('- '.$issue);
            }
        }

        $armProbe = $guard->refusal($db);
        if ($armProbe !== null) {
            $this->line('financial:arm refusal for this database: '.$armProbe);
            if ($protected) {
                $this->warn('This connected database is protected and cannot be armed. Use the approved production database name only.');
            } elseif (str_contains($armProbe, 'already has business records')) {
                $this->line('If the only blocker is existing business records, use the audited override after approval:');
                $this->line('php artisan financial:arm --confirm=arm-financial-automation --database='.$db.' --allow-existing-business=allow-existing-business-records');
            }
        } else {
            $this->info('financial:arm would be accepted for this database right now.');
        }

        return self::SUCCESS;
    }
}
