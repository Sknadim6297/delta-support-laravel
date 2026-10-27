<?php

namespace Tests\Feature;

use App\Models\Investment;
use App\Models\Member;
use App\Services\RoiGenerationService;
use App\Support\LegacyFinancialActions;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LegacyFinancialCommandGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_direct_legacy_financial_commands_refuse_before_any_mutation(): void
    {
        $checkpoint = storage_path('framework/testing/legacy-financial-checkpoint.json');
        $lock = storage_path('framework/testing/legacy-financial-cycle.lock');
        @unlink($checkpoint);
        @unlink($lock);

        config([
            'deltasupport.legacy_financial_actions' => false,
            'financial.accelerated.enabled' => true,
            'financial.accelerated.checkpoint_path' => $checkpoint,
            'financial.accelerated.lock_path' => $lock,
        ]);

        $member = Member::create([
            'member_id' => 'ST100001',
            'sponsor_id' => 'ST666666',
            'sponsor_name' => 'Admin',
            'member_name' => 'Guard Member',
            'mobile_no' => '9000000001',
            'pan_card_no' => 'PAN000001',
            'email' => 'guard-member@example.test',
            'status' => 'active',
            'rank_id' => null,
            'activation_wallet_amount' => '10.0000',
            'working_wallet_amount' => '20.0000',
            'roi_wallet_amount' => '30.0000',
        ]);

        $investment = Investment::create([
            'investment_id' => 'INVGUARD001',
            'member_id' => $member->member_id,
            'member_name' => $member->member_name,
            'amount' => '100.0000',
            'status' => 'active',
        ]);
        $createdAt = CarbonImmutable::parse('2026-01-31', RoiGenerationService::TIMEZONE)->setTimezone('UTC');
        $investment->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->save();

        $tables = [
            'roi_transactions',
            'level_commission_transactions',
            'rank_achievements',
            'investment_withdrawals',
            'activation_wallet_transactions',
            'level_commissions',
        ];
        $counts = [];
        foreach ($tables as $table) {
            $counts[$table] = DB::table($table)->count();
        }

        foreach ([
            'roi:generate',
            'commission:generate-level',
            'rank:advance',
            'financial:cycle',
        ] as $command) {
            $this->artisan($command)
                ->expectsOutputToContain(LegacyFinancialActions::DISABLED_MESSAGE)
                ->assertExitCode(1);
        }

        foreach ($tables as $table) {
            $this->assertSame($counts[$table], DB::table($table)->count(), $table);
        }

        $member->refresh();
        $investment->refresh();
        $this->assertNull($member->rank_id);
        $this->assertSame('10.0000', $member->activation_wallet_amount);
        $this->assertSame('20.0000', $member->working_wallet_amount);
        $this->assertSame('30.0000', $member->roi_wallet_amount);
        $this->assertSame('active', $investment->status);
        $this->assertSame('100.0000', $investment->amount);
        $this->assertFalse(is_file($checkpoint));
        $this->assertFalse(is_file($lock));
    }
}
