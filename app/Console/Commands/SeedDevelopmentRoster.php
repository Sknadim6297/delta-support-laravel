<?php

namespace App\Console\Commands;

use App\Models\Member;
use App\Models\PackageLevelAccrual;
use App\Models\PackageRoiAccrual;
use App\Services\DevelopmentMemberRoster;
use App\Services\LocalDatabaseGuard;
use Illuminate\Console\Command;
use Throwable;

class SeedDevelopmentRoster extends Command
{
    protected $signature = 'development:roster
        {action : seed or status}
        {--confirm= : local-delta-support is required for seed}';

    protected $description = 'Seed 20 fictional local-test members. Existing non-local accounts are left unchanged and no wallet is credited.';

    public function handle(LocalDatabaseGuard $guard, DevelopmentMemberRoster $roster): int
    {
        $action = (string) $this->argument('action');
        if ($action === 'seed' && $this->option('confirm') !== LocalDatabaseGuard::CONFIRM) {
            $this->error('Refused. Repeat the command with --confirm=local-delta-support. No member was created and no wallet was credited.');

            return 1;
        }

        try {
            $guard->assertOriginalLocal();
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return 1;
        }

        try {
            return match ($action) {
                'seed' => $this->seed($roster),
                'status' => $this->status($roster),
                default => $this->unknown(),
            };
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return 1;
        }
    }

    private function seed(DevelopmentMemberRoster $roster): int
    {
        $result = $roster->seed();
        $this->info('Development roster is stored in the local database. No wallet was credited.');
        $this->line('Members: '.$result['members']);
        $this->line('Created this run: '.$result['created']);
        $this->line('Marked local-test purchases: '.$result['purchases']);
        $this->line('Previous local-test roster replaced: '.($result['replaced_local_test'] ? 'yes' : 'no'));
        $this->printRows($roster);

        return 0;
    }

    private function status(DevelopmentMemberRoster $roster): int
    {
        $this->printRows($roster);

        return 0;
    }

    private function printRows(DevelopmentMemberRoster $roster): void
    {
        $members = $roster->currentMembers();
        $ids = array_map(fn (Member $member): string => $member->member_id, $members);
        $this->line('Roster members found: '.count($members));
        foreach ($roster->eligibility($members) as $row) {
            $this->line(sprintf(
                '%s %s sponsor %s (%s) status %s package %s directs %d levels %d roi %s working %s',
                $row['name'],
                $row['member_id'],
                $row['sponsor_id'],
                $row['sponsor_name'],
                $row['status'],
                $row['package'] === '' ? 'none' : $row['package'],
                $row['qualifying_directs'],
                $row['unlocked_levels'],
                $row['roi_wallet'],
                $row['working_wallet'],
            ));
        }
        $roi = $ids === [] ? 0 : PackageRoiAccrual::query()->whereIn('member_id', $ids)->count();
        $level = $ids === [] ? 0 : PackageLevelAccrual::query()->whereIn('member_id', $ids)->count();
        $this->line('Roster ROI accrual rows: '.$roi);
        $this->line('Roster level accrual rows: '.$level);
    }

    private function unknown(): int
    {
        $this->error('Use seed or status. No wallet was credited.');

        return 1;
    }
}
