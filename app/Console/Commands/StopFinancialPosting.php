<?php

namespace App\Console\Commands;

use App\Models\AdminActivityLog;
use App\Models\FinancialControl;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class StopFinancialPosting extends Command
{
    protected $signature = 'financial:stop {--confirm= : Type stop-financial-posting}';

    protected $description = 'Turn on the emergency stop. Armed automation does not post until the stop is released.';

    public function handle(): int
    {
        if ($this->option('confirm') !== 'stop-financial-posting') {
            $this->error('Refused. Repeat with --confirm=stop-financial-posting. The stop was not changed.');

            return self::FAILURE;
        }

        $row = FinancialControl::current();
        $row->financial_emergency_stop = true;
        $row->save();

        $adminId = User::query()->where('is_admin', true)->value('id');
        if ($adminId) {
            AdminActivityLog::query()->create([
                'user_id' => $adminId,
                'action' => 'financial_emergency_stop',
                'subject' => 'financial_controls',
                'format' => null,
                'scope' => 'admin',
                'filters' => ['database' => DB::connection()->getDatabaseName(), 'stopped' => true],
                'row_count' => 1,
            ]);
        }

        $this->warn('Emergency stop is on. No automatic wallet credit will be posted.');

        return self::SUCCESS;
    }
}
