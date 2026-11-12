<?php

namespace App\Console\Commands;

use App\Models\AdminActivityLog;
use App\Models\FinancialControl;
use App\Models\User;
use App\Services\FinancialActivationGuard;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ArmFinancialAutomation extends Command
{
    protected $signature = 'financial:arm
        {--confirm= : Type arm-financial-automation}
        {--database= : Exact approved database name}
        {--allow-existing-business= : Type allow-existing-business-records to arm after members or purchases already exist}';

    protected $description = 'Arm automatic ROI and 20-level commission posting on the one approved initialized database. Withdrawals and external payouts stay off.';

    public function handle(FinancialActivationGuard $guard): int
    {
        if ($this->option('confirm') !== 'arm-financial-automation') {
            $this->error('Refused. Repeat with --confirm=arm-financial-automation. No posting was armed.');

            return self::FAILURE;
        }

        $requested = trim((string) $this->option('database'));
        $allowExisting = $this->option('allow-existing-business') === 'allow-existing-business-records';
        if ($this->option('allow-existing-business') && ! $allowExisting) {
            $this->error('Refused. To arm with existing business records, pass --allow-existing-business=allow-existing-business-records. No posting was armed.');

            return self::FAILURE;
        }

        $refusal = $guard->refusal($requested, $allowExisting);
        if ($refusal !== null) {
            $this->audit($requested, 'financial_automation_arm_refused', $refusal, $allowExisting);
            $this->error($refusal);

            return self::FAILURE;
        }

        $row = FinancialControl::current();
        $row->financial_automation_armed = true;
        $row->save();

        $message = $allowExisting
            ? 'Automatic posting was armed with an explicit existing-business override.'
            : 'Automatic posting was armed.';
        $this->audit($requested, 'financial_automation_armed', $message, $allowExisting);
        $this->info('Automatic ROI and level-commission posting is armed on '.$requested.'. External payouts stay off.');
        if ($allowExisting) {
            $this->warn('Existing business records were present. Only future scheduled dates post automatically. Use financial:missed and an approved recovery plan for earlier gaps.');
        }

        return self::SUCCESS;
    }

    private function audit(string $database, string $action, string $reason, bool $allowExisting): void
    {
        $adminId = User::query()->where('is_admin', true)->value('id');
        if (! $adminId) {
            return;
        }

        AdminActivityLog::query()->create([
            'user_id' => $adminId,
            'action' => $action,
            'subject' => 'financial_controls',
            'format' => null,
            'scope' => 'admin',
            'filters' => [
                'database' => $database,
                'connected' => (string) DB::connection()->getDatabaseName(),
                'reason' => $reason,
                'allow_existing_business' => $allowExisting,
            ],
            'row_count' => 1,
        ]);
    }
}
