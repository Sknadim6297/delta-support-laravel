<?php

namespace Tests\Feature;

use App\Models\CommissionPlanLevel;
use App\Models\Member;
use App\Models\RoiTransaction;
use App\Models\User;
use App\Services\FinancialScheduleRegistrar;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class MenuAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_configuration_pages_are_read_only_and_rewards_stay_deferred(): void
    {
        $this->get(route('admin.finance.roi-plans'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->get(route('admin.finance.commission-settings'))
            ->assertForbidden();

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->get(route('admin.finance.roi-plans'))
            ->assertOk()
            ->assertSeeText('No ROI percentage is saved')
            ->assertSeeText('No packages have been saved.');

        $this->actingAs($admin)->get(route('admin.finance.roi-schedule'))
            ->assertOk()
            ->assertSeeText('Automatic ROI generation is disabled')
            ->assertSeeText('Disabled');

        $this->actingAs($admin)->get(route('admin.finance.commission-settings'))
            ->assertOk()
            ->assertSeeText('25.0000%')
            ->assertSeeText('100.0000%')
            ->assertSeeText('Inactive');

        $this->actingAs($admin)->get(route('admin.finance.level-unlock-rules'))
            ->assertOk()
            ->assertSeeText('5 or more')
            ->assertSeeText('20 of 20')
            ->assertSeeText('does not pay commission');

        $this->actingAs($admin)->get(route('admin.catalog-reports.show', ['report' => 'earnings']))
            ->assertOk()
            ->assertSeeText('not calculated')
            ->assertSeeText('Stored ROI records');

        $this->actingAs($admin)->get(route('admin.coming-soon', ['feature' => 'reward-plans']))
            ->assertOk()
            ->assertSeeText('Coming Soon');

        $this->actingAs($admin)->post(route('admin.finance.commission-settings'))->assertStatus(405);
        $this->assertFalse(CommissionPlanLevel::query()->where('is_active', true)->exists());
        $this->assertSame(0, RoiTransaction::query()->count());

        $names = collect(Route::getRoutes())->map(fn ($route) => $route->getName())->filter();
        $this->assertNotContains('admin.finance.commission-settings.update', $names->all());
        $this->assertNotContains('admin.finance.roi-plans.update', $names->all());

        $schedule = new Schedule;
        app(FinancialScheduleRegistrar::class)->register($schedule);
        $this->assertSame([], $schedule->events());
    }

    public function test_member_referral_tree_opens_and_rewards_stay_deferred(): void
    {
        $member = Member::query()->create([
            'member_id' => 'STMENU0001',
            'sponsor_id' => 'ST666666',
            'sponsor_name' => 'Admin',
            'member_name' => 'Menu Member',
            'mobile_no' => '9300000001',
            'email' => 'menu@example.test',
            'status' => 'active',
            'roi_wallet_amount' => '1.5000',
            'working_wallet_amount' => '2.5000',
            'activation_wallet_amount' => '3.5000',
        ]);

        $this->get(route('member.genealogy.tree-view'))->assertForbidden();

        $this->withSession(['member_context_id' => $member->member_id])
            ->get(route('member.genealogy.tree-view'))
            ->assertOk()
            ->assertSeeText('Referral Network')
            ->assertSeeText($member->member_id);

        $this->withSession(['member_context_id' => $member->member_id])
            ->get(route('member.coming-soon', ['feature' => 'my-rewards']))
            ->assertOk()
            ->assertSeeText('Coming Soon');

        $this->withSession(['member_context_id' => $member->member_id])
            ->get(route('admin.finance.roi-plans'))
            ->assertRedirect(route('login'));

        $member->refresh();
        $this->assertSame('1.5000', $member->roi_wallet_amount);
        $this->assertSame('2.5000', $member->working_wallet_amount);
        $this->assertSame('3.5000', $member->activation_wallet_amount);
    }
}
