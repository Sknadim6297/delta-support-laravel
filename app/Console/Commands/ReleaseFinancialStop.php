<?php

namespace App\Console\Commands;

use App\Models\AdminActivityLog;
use App\Models\FinancialControl;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReleaseFinancialStop extends Command
{
    protected $signature = 'financial:release {--confirm= : Type release-financial-stop}';

    protected $description = 'Release the emergency stop. Posting resumes only if automation is already armed.';

    public function handle(): int
    {
        if ($this->option('confirm') !== 'release-financial-stop') {
            $this->error('Refused. Repeat with --confirm=release-financial-stop. The stop was not changed.');

            return self::FAILURE;
        }

        $row = FinancialControl::current();
        $row->financial_emergency_stop = false;
        $row->save();

        $adminId = User::query()->where('is_admin', true)->value('id');
        if ($adminId) {
            AdminActivityLog::query()->create([
                'user_id' => $adminId,
                'action' => 'financial_emergency_released',
                'subject' => 'financial_controls',
                'format' => null,
                'scope' => 'admin',
                'filters' => ['database' => DB::connection()->getDatabaseName(), 'stopped' => false],
                'row_count' => 1,
            ]);
        }

        $this->info('Emergency stop is off. Automatic posting runs only when it is armed.');

        return self::SUCCESS;
    }
}
