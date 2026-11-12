<?php

namespace App\Console\Commands;

use App\Models\AdminActivityLog;
use App\Models\User;
use App\Services\FinancialActivationGuard;
use App\Services\ProductionDatabaseTarget;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class MarkFinancialInitialization extends Command
{
    protected $signature = 'financial:mark-initialized
        {--confirm= : Type mark-financial-initialization}
        {--database= : Exact connected database name that will receive the marker}';

    protected $description = 'Write the production initialization marker on an existing database without migrating, seeding, deleting, or arming posting.';

    public function handle(FinancialActivationGuard $guard, ProductionDatabaseTarget $targets): int
    {
        if ($this->option('confirm') !== 'mark-financial-initialization') {
            $this->error('Refused. Repeat with --confirm=mark-financial-initialization. No marker was written.');

            return self::FAILURE;
        }

        $requested = trim((string) $this->option('database'));
        $connected = (string) DB::connection()->getDatabaseName();
        $approved = trim((string) config('deltasupport.financial_approved_database', ''));

        if ($requested === '' || $requested !== $connected) {
            $this->error('Refused. --database must exactly match the connected database ('.$connected.'). No marker was written.');

            return self::FAILURE;
        }

        if ($approved === '' || $approved !== $connected) {
            $this->error('Refused. Set FINANCIAL_APPROVED_DATABASE to '.$connected.' first. No marker was written.');

            return self::FAILURE;
        }

        if ($targets->isProtected($connected)) {
            $this->error('Refused. '.$connected.' is a protected development database. No marker was written.');

            return self::FAILURE;
        }

        if (! Schema::hasTable('production_initializations') || ! Schema::hasTable('financial_controls')) {
            $this->error('Refused. Required tables are missing. No marker was written.');

            return self::FAILURE;
        }

        $existing = (int) DB::table('production_initializations')
            ->where('marker', FinancialActivationGuard::MARKER)
            ->count();
        if ($existing > 1) {
            $this->error('Refused. Multiple initialization markers already exist. No marker was written.');

            return self::FAILURE;
        }

        try {
            $guard->recordInitialization((string) DB::getDefaultConnection());
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->audit($connected, 'financial_initialization_marked', 'Initialization marker recorded without arming posting.');
        $this->info('Initialization marker recorded on '.$connected.'.');
        $this->line('No business table was deleted or modified. Posting is still not armed.');
        $this->line('Next approved step is financial:arm with the audited confirmations.');

        return self::SUCCESS;
    }

    private function audit(string $database, string $action, string $reason): void
    {
        $adminId = User::query()->where('is_admin', true)->value('id');
        if (! $adminId) {
            return;
        }

        AdminActivityLog::query()->create([
            'user_id' => $adminId,
            'action' => $action,
            'subject' => 'production_initializations',
            'format' => null,
            'scope' => 'admin',
            'filters' => [
                'database' => $database,
                'connected' => (string) DB::connection()->getDatabaseName(),
                'reason' => $reason,
            ],
            'row_count' => 1,
        ]);
    }
}
