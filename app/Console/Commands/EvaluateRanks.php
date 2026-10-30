<?php

namespace App\Console\Commands;

use App\Models\Member;
use App\Services\FinancialControls;
use App\Services\RankService;
use Illuminate\Console\Command;

class EvaluateRanks extends Command
{
    protected $signature = 'ranks:evaluate';

    protected $description = 'Evaluate stored rank rules. This does not credit a wallet or send a reward.';

    public function handle(FinancialControls $controls, RankService $ranks): int
    {
        if (! $controls->automaticPostingAllowed()) {
            $this->warn('Rank evaluation is not armed. No rank was changed and no wallet was credited.');

            return self::SUCCESS;
        }

        $ids = Member::query()->orderBy('id')->pluck('member_id')->all();
        $ranks->syncMemberRankAchievements($ids);
        $this->info('Rank evaluation completed for '.count($ids).' members. No wallet was credited.');

        return self::SUCCESS;
    }
}
