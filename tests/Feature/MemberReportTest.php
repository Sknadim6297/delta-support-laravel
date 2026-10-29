<?php

namespace Tests\Feature;

use App\Models\LevelCommissionTransaction;
use App\Models\Investment;
use App\Models\Member;
use App\Models\Rank;
use App\Models\RankAchievement;
use App\Models\RoiTransaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_view_only_own_roi_and_total(): void
    {
        $member = $this->member('ST100001');
        $otherMember = $this->member('ST100002');
        $this->roi($member, 'ROI-OWN-1', 'INV-OWN', '4.0083', '2026-09-21');
        $this->roi($otherMember, 'ROI-OTHER-1', 'INV-OTHER', '99.0000', '2026-09-21');
        $this->memberSession($member);

        $this->get(route('member.reports.roi', ['member_id' => $otherMember->member_id]))
            ->assertOk()
            ->assertSeeText('INV-OWN')
            ->assertSeeText('4.0083')
            ->assertSeeText('4.0083')
            ->assertDontSeeText('INV-OTHER')
            ->assertDontSeeText('99.0000')
            ->assertDontSeeText('USDT 99');
    }

    public function test_member_roi_date_filter_and_total_are_scoped_to_own_records(): void
    {
        $member = $this->member('ST100003');
        $otherMember = $this->member('ST100004');
        $this->roi($member, 'ROI-OWN-2', 'INV-OWN-2', '4.0000', '2026-09-19');
        $this->roi($member, 'ROI-OWN-3', 'INV-OWN-3', '8.0000', '2026-09-21');
        $this->roi($otherMember, 'ROI-OTHER-2', 'INV-OTHER-2', '100.0000', '2026-09-21');
        $this->memberSession($member);

        $this->get(route('member.reports.roi', [
            'from_date' => '2026-09-20',
            'to_date' => '2026-09-21',
        ]))->assertOk()
            ->assertSeeText('INV-OWN-3')
            ->assertDontSeeText('INV-OWN-2')
            ->assertDontSeeText('INV-OTHER-2')
            ->assertSeeText('8');
    }

    public function test_member_can_view_only_own_level_commission_with_level_and_date_filters(): void
    {
        $member = $this->member('ST100005');
        $otherMember = $this->member('ST100006');
        $this->commission($member, 'LC-OWN-1', 'INV-LC-1', 1, '3.0000', '2026-09-20');
        $this->commission($member, 'LC-OWN-2', 'INV-LC-2', 2, '7.0000', '2026-09-21');
        $this->commission($otherMember, 'LC-OTHER-1', 'INV-LC-3', 1, '100.0000', '2026-09-21');
        $this->memberSession($member);

        $this->get(route('member.reports.level-income', [
            'member_id' => $otherMember->member_id,
            'level' => 2,
            'from_date' => '2026-09-21',
            'to_date' => '2026-09-21',
        ]))->assertOk()
            ->assertSeeText('7');
    }

    public function test_admin_can_see_rank_achievements_for_multiple_members(): void
    {
        $this->seed(\Database\Seeders\RankSeeder::class);
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        $first = $this->member('ST200001');
        $second = $this->member('ST200002');
        $this->rankAchievementFor($first, 'Silver', '1200.0000', '2026-09-12');
        $this->rankAchievementFor($second, 'Gold', '3500.0000', '2026-09-15');

        $this->get(route('admin.report.rank-achievement'))
            ->assertOk()
            ->assertSeeText($first->member_id)
            ->assertSeeText($second->member_id)
            ->assertSeeText('Silver')
            ->assertSeeText('Gold');
    }

    public function test_member_can_view_only_own_rank_achievements(): void
    {
        $this->seed(\Database\Seeders\RankSeeder::class);
        $member = $this->member('ST200003');
        $otherMember = $this->member('ST200004');
        $this->rankAchievementFor($member, 'Silver', '1800.0000', '2026-09-16');
        $this->rankAchievementFor($otherMember, 'Gold', '5000.0000', '2026-09-17');
        $this->memberSession($member);

        $this->get(route('member.reports.rank-achievement'))
            ->assertOk()
            ->assertSeeText($member->member_id)
            ->assertSeeText('Silver')
            ->assertDontSeeText($otherMember->member_id);
    }

    public function test_member_rank_achievement_report_ignores_manipulated_member_id_query_parameter(): void
    {
        $this->seed(\Database\Seeders\RankSeeder::class);
        $member = $this->member('ST200005');
        $otherMember = $this->member('ST200006');
        $this->rankAchievementFor($member, 'Silver', '1500.0000', '2026-09-18');
        $this->rankAchievementFor($otherMember, 'Gold', '6000.0000', '2026-09-19');
        $this->memberSession($member);

        $this->get(route('member.reports.rank-achievement', ['member_id' => $otherMember->member_id]))
            ->assertOk()
            ->assertSeeText($member->member_id)
            ->assertDontSeeText($otherMember->member_id);
    }

    public function test_member_rank_achievement_report_shows_rank_name_and_achieving_date(): void
    {
        $this->seed(\Database\Seeders\RankSeeder::class);
        $member = $this->member('ST200007');
        $this->rankAchievementFor($member, 'Blue Diamond', '3000.0000', '2026-09-20');
        $this->memberSession($member);

        $this->get(route('member.reports.rank-achievement'))
            ->assertOk()
            ->assertSeeText('Blue Diamond')
            ->assertSeeText('20-09-2026');
    }

    public function test_member_rank_achievement_report_empty_state_when_no_achievements_exist(): void
    {
        $this->seed(\Database\Seeders\RankSeeder::class);
        $member = $this->member('ST200008');
        $this->memberSession($member);

        $this->get(route('member.reports.rank-achievement'))
            ->assertOk()
            ->assertSeeText('No rank achievements found.');
    }

    public function test_member_rank_achievement_total_uses_downline_business_and_excludes_outside_members(): void
    {
        $this->seed(\Database\Seeders\RankSeeder::class);
        $member = $this->member('ST200009');
        $insideOne = $this->member('ST200010');
        $insideTwo = $this->member('ST200011');
        $outside = $this->member('ST200012');
        $insideTwoChild = $this->member('ST200013');

        $insideOne->forceFill(['sponsor_id' => $member->member_id])->saveQuietly();
        $insideTwo->forceFill(['sponsor_id' => $member->member_id])->saveQuietly();
        $insideTwoChild->forceFill(['sponsor_id' => $insideTwo->member_id])->saveQuietly();
        $outside->forceFill(['sponsor_id' => 'ST999999'])->saveQuietly();

        $this->createInvestment($insideOne, '5000.0000', '2026-09-01');
        $this->createInvestment($insideTwo, '8000.0000', '2026-09-02');
        $this->createInvestment($insideTwoChild, '10100.0000', '2026-09-03');
        $this->createInvestment($outside, '99999.0000', '2026-09-04');

        $this->rankAchievementFor($member, 'Silver', '1500.0000', '2026-09-10');
        $this->memberSession($member);

        $this->get(route('member.reports.rank-achievement'))
            ->assertOk()
            ->assertSeeText('23100')
            ->assertSeeText($member->member_id)
            ->assertDontSeeText($outside->member_id);
    }

    public function test_member_rank_achievement_total_respects_date_filters_and_ignores_query_member_id(): void
    {
        $this->seed(\Database\Seeders\RankSeeder::class);
        $member = $this->member('ST200014');
        $insideOne = $this->member('ST200015');
        $insideTwo = $this->member('ST200016');
        $otherMember = $this->member('ST200017');

        $insideOne->forceFill(['sponsor_id' => $member->member_id])->saveQuietly();
        $insideTwo->forceFill(['sponsor_id' => $member->member_id])->saveQuietly();
        $otherMember->forceFill(['sponsor_id' => 'ST777777'])->saveQuietly();

        $this->createInvestment($insideOne, '5000.0000', '2026-09-01');
        $this->createInvestment($insideTwo, '8000.0000', '2026-09-20');
        $this->createInvestment($otherMember, '15000.0000', '2026-09-25');

        $this->rankAchievementFor($member, 'Gold', '5000.0000', '2026-09-21');
        $this->memberSession($member);

        $this->get(route('member.reports.rank-achievement', [
            'from_date' => '2026-09-15',
            'to_date' => '2026-09-20',
            'member_id' => $otherMember->member_id,
        ]))->assertOk()->assertSeeText('8000');

        $this->get(route('member.reports.rank-achievement', [
            'from_date' => '2026-09-01',
            'to_date' => '2026-09-30',
            'member_id' => $otherMember->member_id,
        ]))->assertOk()->assertSeeText('13000');
    }

    public function test_member_rank_achievement_total_uses_own_active_business_and_configured_next_threshold(): void
    {
        $this->seed(\Database\Seeders\RankSeeder::class);
        $member = $this->member('ST200018');

        $this->createInvestment($member, '23500.0000', '2026-09-01');
        $this->createInvestment($member, '2000.0000', '2026-09-03');

        $rankData = app(\App\Services\RankService::class)->calculateForMember($member);

        $this->assertSame('25500.0000', $rankData['full_team_business']);
        $this->assertSame('Platinum', $rankData['current_rank']->name);
        $this->assertSame(Rank::where('name', 'Ruby')->value('required_full_team_business'), $rankData['next_rank']->required_full_team_business);

        $this->memberSession($member);
        $this->get(route('member.reports.rank-achievement'))->assertOk()->assertSeeText('25500');
    }

    public function test_member_report_routes_require_member_context(): void
    {
        $this->get(route('member.reports.roi'))->assertForbidden();
        $this->get(route('member.reports.level-income'))->assertForbidden();
        $this->get(route('member.reports.rank-achievement'))->assertForbidden();
    }

    public function test_member_level_income_total_stays_correct_on_paginated_pages(): void
    {
        $member = $this->member('ST100008');

        foreach (range(1, 11) as $index) {
            $this->commission(
                $member,
                'LC-PAGE-TOTAL-' . $index,
                'INV-LC-PAGE-TOTAL-' . $index,
                1,
                (string) $index . '.0000',
                '2026-09-' . str_pad((string) $index, 2, '0', STR_PAD_LEFT)
            );
        }

        $this->memberSession($member);

        $this->get(route('member.reports.level-income', [
            'level' => 1,
            'page' => 2,
        ]))->assertOk()
            ->assertSeeText('66')
            ->assertSeeText('Total Amount (USDT)');
    }

    public function test_all_paginated_member_reports_use_shared_pagination_and_preserve_filters(): void
    {
        $member = $this->member('ST100007');

        foreach (range(1, 11) as $index) {
            $this->roi(
                $member,
                'ROI-PAGE-' . $index,
                'INV-PAGE-' . $index,
                '1.0000',
                '2026-09-' . str_pad((string) (($index % 9) + 1), 2, '0', STR_PAD_LEFT)
            );
            $this->commission(
                $member,
                'LC-PAGE-' . $index,
                'INV-LC-PAGE-' . $index,
                1,
                '2.0000',
                '2026-09-' . str_pad((string) (($index % 9) + 1), 2, '0', STR_PAD_LEFT)
            );
        }

        $this->memberSession($member);

        $this->get(route('member.reports.roi', [
            'from_date' => '2026-09-01',
            'page' => 2,
        ]))->assertOk()
            ->assertSee('Showing', false)
            ->assertSee('Previous', false)
            ->assertSee('Next', false)
            ->assertSee('page-item active', false)
            ->assertSee('from_date=2026-09-01', false)
            ->assertDontSee('« Previous', false);

        $this->get(route('member.reports.level-income', [
            'level' => 1,
            'to_date' => '2026-09-30',
            'page' => 2,
        ]))->assertOk()
            ->assertSee('Showing', false)
            ->assertSee('Previous', false)
            ->assertSee('Next', false)
            ->assertSee('page-item active', false)
            ->assertSee('level=1', false)
            ->assertSee('to_date=2026-09-30', false)
            ->assertDontSee('« Previous', false);
    }

    private function member(string $memberId): Member
    {
        return Member::create([
            'member_id' => $memberId,
            'sponsor_id' => 'ST666666',
            'sponsor_name' => 'Admin',
            'member_name' => 'Member ' . $memberId,
            'mobile_no' => '98765' . substr($memberId, -5),
            'pan_card_no' => 'ABCDE' . substr($memberId, -5),
            'email' => strtolower($memberId) . '@example.test',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);
    }

    private function createInvestment(Member $member, string $amount, string $date): void
    {
        $investment = Investment::create([
            'investment_id' => 'INV-' . $member->member_id . '-' . now()->timestamp . random_int(1000, 9999),
            'member_id' => $member->member_id,
            'member_name' => $member->member_name,
            'amount' => $amount,
            'status' => 'active',
        ]);

        $investment->forceFill([
            'created_at' => CarbonImmutable::parse($date),
            'updated_at' => CarbonImmutable::parse($date),
        ])->save();
    }

    private function rankAchievementFor(Member $member, string $rankName, string $businessAmount, ?string $achievedAt = null): void
    {
        $rank = Rank::where('name', $rankName)->firstOrFail();

        RankAchievement::query()->updateOrCreate(
            ['member_id' => $member->member_id, 'rank_id' => $rank->id],
            [
                'member_name' => $member->member_name,
                'qualifying_business_amount' => $businessAmount,
                'achieved_at' => $achievedAt ? CarbonImmutable::parse($achievedAt) : CarbonImmutable::now('Asia/Kolkata'),
            ]
        );
    }

    private function roi(Member $member, string $reference, string $investmentId, string $income, string $date): void
    {
        Investment::create([
            'investment_id' => $investmentId,
            'member_id' => $member->member_id,
            'member_name' => $member->member_name,
            'amount' => '2405.0000',
            'status' => 'active',
        ]);

        $transaction = RoiTransaction::create([
            'reference' => $reference,
            'investment_id' => $investmentId,
            'member_id' => $member->member_id,
            'member_name' => $member->member_name,
            'on_amount' => '2405.0000',
            'rate_percentage' => '5.000',
            'income_amount' => $income,
            'roi_date' => $date,
            'status' => 'generated',
            'withdrawable_on' => '2026-10-01',
        ]);

        $transaction->forceFill([
            'created_at' => CarbonImmutable::parse($date),
            'updated_at' => CarbonImmutable::parse($date),
        ])->save();
    }

    private function commission(Member $member, string $reference, string $investmentId, int $level, string $income, string $date): void
    {
        $transaction = LevelCommissionTransaction::create([
            'reference' => $reference,
            'investment_id' => $investmentId,
            'member_id' => $member->member_id,
            'member_name' => $member->member_name,
            'from_member_id' => 'ST999999',
            'from_member_name' => 'Source Member',
            'level' => $level,
            'business_date' => $date,
            'on_amount' => '1000.0000',
            'rate_percentage' => '1.000',
            'income_amount' => $income,
        ]);

        $transaction->forceFill([
            'created_at' => CarbonImmutable::parse($date),
            'updated_at' => CarbonImmutable::parse($date),
        ])->save();
    }

    private function memberSession(Member $member): void
    {
        $this->withSession(['member_context_id' => $member->member_id]);
    }
}