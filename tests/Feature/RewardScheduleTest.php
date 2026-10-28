<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\RewardEvaluationRun;
use App\Services\FinancialScheduleRegistrar;
use App\Services\Rewards\RewardScheduleRegistrar;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class RewardScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_rewards_run_every_two_minutes_and_financial_schedules_stay_empty(): void
    {
        $financial = new Schedule;
        app(FinancialScheduleRegistrar::class)->register($financial);
        $this->assertSame([], $financial->events());
        $this->assertFalse(config('deltasupport.package_income.enabled'));

        $rewards = new Schedule;
        app(RewardScheduleRegistrar::class)->register($rewards);
        $event = $rewards->events()[0];

        $this->assertStringContainsString('rewards:evaluate', (string) $event->command);
        $this->assertSame('*/2 * * * *', $event->expression);
        $this->assertSame('Asia/Kolkata', $event->timezone);
        $this->assertTrue($event->withoutOverlapping);
        $this->assertTrue($event->onOneServer);
        $this->assertStringNotContainsString('package-income', (string) $event->command);
    }

    public function test_evaluation_logs_a_completed_run_and_a_locked_run_without_wallet_credits(): void
    {
        $member = Member::query()->create([
            'member_id' => 'STSCHED01',
            'sponsor_id' => 'ST666666',
            'sponsor_name' => 'Admin',
            'member_name' => 'Schedule Member',
            'mobile_no' => '9000000091',
            'email' => 'schedule@example.test',
            'status' => 'active',
            'roi_wallet_amount' => '1.2500',
            'working_wallet_amount' => '2.5000',
            'activation_wallet_amount' => '3.7500',
        ]);

        $this->artisan('rewards:evaluate')->assertSuccessful();
        $completed = RewardEvaluationRun::query()->firstOrFail();
        $this->assertSame(RewardEvaluationRun::COMPLETED, $completed->status);
        $this->assertSame(1, $completed->members_reviewed);
        $this->assertStringContainsString('No wallet was credited.', $completed->message);
        $this->assertSame('1.2500', $member->refresh()->roi_wallet_amount);
        $this->assertSame('2.5000', $member->working_wallet_amount);

        $lock = Cache::lock('rewards-evaluate', 600);
        $this->assertTrue($lock->get());
        $this->artisan('rewards:evaluate')->assertSuccessful();
        $lock->release();

        $skipped = RewardEvaluationRun::query()->orderByDesc('id')->firstOrFail();
        $this->assertSame(RewardEvaluationRun::SKIPPED, $skipped->status);
        $this->assertSame('1.2500', $member->refresh()->roi_wallet_amount);
    }
}
