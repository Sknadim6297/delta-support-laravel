<?php

namespace App\Console\Commands;

use App\Services\DemoMemberSetup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class SetupDemoMembers extends Command
{
    protected $signature = 'demo:members:setup {--confirm= : setup-demo-members is required before any row is inserted}';

    protected $description = 'Insert the 20 demonstration members when their IDs, emails, phones, and sponsors are free. Existing rows are not changed.';

    public function handle(DemoMemberSetup $setup): int
    {
        $this->line('Environment: '.app()->environment());
        $this->line('Database: '.DB::connection()->getDatabaseName());

        try {
            $inspection = $setup->inspect();
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return 1;
        }

        foreach ($inspection['conflicts'] as $conflict) {
            $this->error($conflict);
        }
        if ($inspection['conflicts'] !== []) {
            $this->error('No member, purchase, or wallet was changed.');

            return 1;
        }

        $this->line('Missing demonstration members: '.$inspection['missing_members']);
        $this->line('Missing demonstration purchases: '.$inspection['missing_purchases']);
        if ($this->option('confirm') !== DemoMemberSetup::CONFIRM) {
            $this->warn('No row was written. Repeat with --confirm='.DemoMemberSetup::CONFIRM.' to insert only the missing demonstration records.');

            return 0;
        }

        try {
            $result = $setup->setup();
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return 1;
        }

        $this->info('Demonstration members are stored. No wallet was credited and no payout switch was enabled.');
        $this->line('Created members: '.$result['created_members']);
        $this->line('Created purchases: '.$result['created_purchases']);
        foreach ($setup->tree() as $row) {
            $this->line(sprintf(
                '%s %s sponsor %s (%s) status %s package %s directs %d levels %d',
                $row['name'],
                $row['member_id'],
                $row['sponsor_id'],
                $row['sponsor_name'],
                $row['status'],
                $row['package'] === '' ? 'none' : $row['package'],
                $row['qualifying_directs'],
                $row['unlocked_levels'],
            ));
        }

        return 0;
    }
}
