<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\PackagePlanAdministration;
use Illuminate\Console\Command;

/**
 * Seeds inactive Original Tour Plan draft versions for existing catalog rows.
 * Does not enable accrual authority, associate posting, or wallet posting.
 */
class SeedOriginalTourPlanDrafts extends Command
{
    protected $signature = 'packages:seed-original-tour-drafts {--force-admin= : Admin user id}';

    protected $description = 'Create draft original_tour_plan_v1 versions without activating financial terms.';

    public function handle(PackagePlanAdministration $plans): int
    {
        $admin = null;
        if ($this->option('force-admin')) {
            $admin = User::query()->whereKey((int) $this->option('force-admin'))->first();
        }
        $admin ??= User::query()->where('is_admin', true)->orderBy('id')->first();
        if ($admin === null) {
            $this->error('No admin user available.');

            return self::FAILURE;
        }

        $candidates = (array) config('deltasupport.original_tour_plan.candidate_package_income', []);
        $plansByCode = [];
        foreach ($candidates as $code => $row) {
            $plansByCode[$code] = [
                'has_daily_roi' => true,
                'roi_daily' => $row['roi_daily'],
                'roi_days' => $row['roi_days'],
                'wait_days' => (int) config('deltasupport.package_income.wait_days', 10),
                'commission_pool' => $row['commission_pool'] ?? null,
                'commission_days' => $row['commission_days'] ?? null,
                'level_commission_eligible' => ($row['commission_days'] ?? null) !== null,
                'label' => 'original_tour_plan_v1_draft',
                'change_note' => 'Inactive draft. Do not use for accrual until written activation.',
                'accrual_authority' => false,
            ];
        }
        $plansByCode['DST-RY-006'] = [
            'has_daily_roi' => false,
            'level_commission_eligible' => false,
            'label' => 'original_tour_plan_v1_draft_share',
            'change_note' => 'DELTA SHARE — no daily ROI.',
            'accrual_authority' => false,
            'tour_eligible_after_days' => 100,
            'tour_nights' => 1,
            'tour_days' => 2,
            'tour_adults' => 2,
            'tour_children' => 1,
            'food_coupon_amount' => '1000.0000',
            'food_coupon_currency' => 'INR',
        ];

        $count = $plans->seedInactivePlanDrafts($plansByCode, $admin);
        $this->info("Seeded {$count} draft plan version(s). Accrual authority remains off. Associate posting unchanged.");

        return self::SUCCESS;
    }
}
