<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\Package;
use App\Models\PackagePurchase;
use App\Models\RewardAssignment;
use App\Models\RewardCampaign;
use App\Models\RewardDelivery;
use App\Models\RewardDeliveryEvent;
use App\Models\RewardQualification;
use App\Models\User;
use App\Services\FinancialScheduleRegistrar;
use App\Services\Rewards\RewardEligibilityService;
use App\Services\Rewards\RewardFulfillmentService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

class RewardCampaignTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_campaign_and_invalid_dates_are_rejected(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('admin.rewards.store'), [
            'title' => 'October review',
            'gift_name' => 'Sample gift',
            'target_amount' => '0',
            'starts_on' => '2026-10-31',
            'ends_on' => '2026-10-01',
        ])->assertSessionHasErrors(['target_amount', 'ends_on']);
        $this->assertSame(0, RewardCampaign::query()->count());

        $this->actingAs($admin)->post(route('admin.rewards.store'), [
            'title' => 'October review',
            'description' => 'A local campaign',
            'gift_name' => 'Sample gift',
            'target_amount' => '10000.0000',
            'starts_on' => '2026-10-01',
            'ends_on' => '2026-12-31',
            'admin_notes' => 'INTERNAL-NOTE-SECRET',
            'image' => UploadedFile::fake()->create('gift.php', 20, 'application/x-php'),
        ])->assertSessionHasErrors('image');

        $this->actingAs($admin)->post(route('admin.rewards.store'), [
            'title' => 'October review',
            'gift_name' => 'Sample gift',
            'target_amount' => '10000',
            'starts_on' => '2026-10-01',
            'ends_on' => '2026-12-31',
            'admin_notes' => 'INTERNAL-NOTE-SECRET',
        ])->assertRedirect();

        $campaign = RewardCampaign::query()->firstOrFail();
        $this->assertSame(RewardCampaign::DRAFT, $campaign->status);
        $this->assertSame('10000.0000', $campaign->target_amount);

        $this->actingAs($admin)->post(route('admin.rewards.publish', $campaign))->assertRedirect();
        $this->assertSame(RewardCampaign::ACTIVE, $campaign->refresh()->status);

        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->get(route('admin.rewards.index'))
            ->assertForbidden();
        auth()->logout();
        $this->get(route('admin.rewards.index'))->assertRedirect(route('login'));
    }

    public function test_team_business_uses_approved_usdt_package_snapshots_and_the_full_downline(): void
    {
        $package = $this->package('DEMO-HILLS');
        $usdPackage = $this->package('DEMO-USD');
        $verify = $this->package('VERIFY-1', Package::TYPE_TOUR);
        $root = $this->member('STREWARD1', 'ST666666', 'Root Member');
        $child = $this->member('STREWARD2', $root->member_id, 'Child Member');
        $grand = $this->member('STREWARD3', $child->member_id, 'Grand Member');
        $local = $this->member('STREWARD4', $root->member_id, 'Local Member', 'local@local-test.invalid');

        $this->purchase($grand, $package, 'approved', '2026-10-15 06:00:00', '49.0000');
        $this->purchase($grand, $package, 'approved', '2026-10-16 06:00:00', '51.0000');
        $this->purchase($grand, $package, 'pending', '2026-10-16 06:00:00', '5000.0000');
        $this->purchase($grand, $package, 'rejected', '2026-10-16 06:00:00', '5000.0000');
        $this->purchase($grand, $usdPackage, 'approved', '2026-10-16 06:00:00', '80.0000', 'USD');
        $this->purchase($grand, $verify, 'approved', '2026-10-16 06:00:00', '100.0000', 'USD');
        $this->purchase($local, $package, 'approved', '2026-10-16 06:00:00', '50000.0000', 'USDT', 'LOCAL-TEST-STREWARD4');
        $outside = $this->purchase($grand, $package, 'approved', '2026-10-31 18:30:00', '9000.0000');
        $inside = $this->purchase($root, $package, 'approved', '2026-10-31 18:29:00', '10.0000');

        $campaign = $this->campaign('October', '100.0000', '2026-10-01', '2026-10-31', RewardCampaign::ACTIVE);
        app(RewardEligibilityService::class)->evaluate($campaign->id);

        $rootRow = RewardQualification::query()->where('member_id', $root->member_id)->firstOrFail();
        $this->assertSame('110.0000', $rootRow->achieved_amount);
        $this->assertSame(RewardQualification::SELECTED, $rootRow->status);
        $this->assertSame('49.0000', $inside->price_usd === '10.0000' ? PackagePurchase::query()->where('price_usd', '49.0000')->value('price_usd') : $inside->price_usd);
        $this->assertSame('10.0000', $inside->price_usd);
        $this->assertSame('9000.0000', $outside->price_usd);

        $draft = $this->campaign('Hidden', '1.0000', '2026-10-01', '2026-10-31', RewardCampaign::DRAFT);
        app(RewardEligibilityService::class)->evaluate($draft->id);
        $this->assertSame(0, RewardQualification::query()->where('campaign_id', $draft->id)->count());

        $this->assertFalse(config('deltasupport.package_income.enabled'));
        $this->assertSame('0.0000', $root->refresh()->roi_wallet_amount);
        $schedule = new Schedule;
        app(FinancialScheduleRegistrar::class)->register($schedule);
        $this->assertSame([], $schedule->events());
    }

    public function test_latest_campaign_wins_and_a_delivered_gift_is_not_revoked(): void
    {
        $package = $this->package('DEMO-HILLS');
        $member = $this->member('STLATEST1', 'ST666666', 'Rahul Reward');
        $this->purchase($member, $package, 'approved', '2026-10-10 04:00:00', '10000.0000');
        $this->purchase($member, $package, 'approved', '2026-11-10 04:00:00', '20000.0000');

        $october = $this->campaign('October window', '10000.0000', '2026-10-01', '2026-10-31', RewardCampaign::ACTIVE, 'Phone');
        $below = $this->member('STBELOW01', 'ST666666', 'Below Target');
        $this->purchase($below, $package, 'approved', '2026-10-10 04:00:00', '9999.0000');

        app(RewardEligibilityService::class)->evaluate($october->id);
        $this->assertSame(RewardQualification::IN_PROGRESS, RewardQualification::query()->where('member_id', $below->member_id)->value('status'));
        $this->assertSame(RewardQualification::SELECTED, RewardQualification::query()->where('member_id', $member->member_id)->value('status'));

        $delivery = RewardAssignment::query()->where('member_id', $member->member_id)->firstOrFail()->delivery;
        $admin = User::factory()->create(['is_admin' => true]);
        $fulfillment = app(RewardFulfillmentService::class);
        $fulfillment->transition($delivery, ['status' => RewardDelivery::APPROVED], $admin);
        $fulfillment->transition($delivery->refresh(), ['status' => RewardDelivery::PROCESSING], $admin);
        $fulfillment->transition($delivery->refresh(), [
            'status' => RewardDelivery::SHIPPED,
            'method' => 'Handed over',
            'dispatched_at' => '2026-10-20 10:00',
        ], $admin);
        $fulfillment->transition($delivery->refresh(), [
            'status' => RewardDelivery::DELIVERED,
            'delivered_at' => '2026-10-21 10:00',
            'recipient_confirmation' => 'Received by member',
            'internal_notes' => 'COURIER-NOTE-SECRET',
        ], $admin);

        $this->assertDatabaseHas('reward_delivery_events', [
            'delivery_id' => $delivery->id,
            'user_id' => $admin->id,
            'from_status' => RewardDelivery::SHIPPED,
            'to_status' => RewardDelivery::DELIVERED,
        ]);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $fulfillment->transition($delivery->refresh(), ['status' => RewardDelivery::PENDING], $admin);
    }

    public function test_a_later_campaign_supersedes_an_open_gift_and_same_day_ids_are_ordered(): void
    {
        $package = $this->package('DEMO-HILLS');
        $member = $this->member('STTIE0001', 'ST666666', 'Tie Member');
        $this->purchase($member, $package, 'approved', '2026-10-10 04:00:00', '20000.0000');
        $this->purchase($member, $package, 'approved', '2026-11-10 04:00:00', '20000.0000');

        $october = $this->campaign('October window', '10000.0000', '2026-10-01', '2026-10-31', RewardCampaign::ACTIVE, 'Phone');
        app(RewardEligibilityService::class)->evaluate($october->id);
        $november = $this->campaign('November window', '20000.0000', '2026-11-01', '2026-11-30', RewardCampaign::ACTIVE, 'Motorbike');
        app(RewardEligibilityService::class)->evaluate();

        $selected = RewardAssignment::query()->where('member_id', $member->member_id)->where('status', RewardAssignment::SELECTED)->get();
        $this->assertCount(1, $selected);
        $this->assertSame($november->id, $selected->first()->campaign_id);
        $this->assertSame('Motorbike', $selected->first()->gift_name);
        $this->assertSame(
            RewardQualification::SUPERSEDED,
            RewardQualification::query()->where('campaign_id', $october->id)->where('member_id', $member->member_id)->value('status'),
        );

        $first = $this->campaign('Same day A', '1000.0000', '2026-10-01', '2026-10-31', RewardCampaign::ACTIVE, 'Alpha');
        $second = $this->campaign('Same day B', '1000.0000', '2026-10-01', '2026-10-31', RewardCampaign::ACTIVE, 'Beta');
        $other = $this->member('STTIE0002', 'ST666666', 'Other Tie');
        $this->purchase($other, $package, 'approved', '2026-10-12 04:00:00', '1000.0000');
        app(RewardEligibilityService::class)->evaluate();
        $winner = RewardAssignment::query()->where('member_id', $other->member_id)->where('status', RewardAssignment::SELECTED)->firstOrFail();
        $this->assertSame($second->id, $winner->campaign_id);
        $this->assertTrue($second->id > $first->id);

        $again = app(RewardEligibilityService::class)->evaluate();
        $this->assertFalse($again['skipped']);
        $this->assertSame(1, RewardAssignment::query()->where('member_id', $other->member_id)->where('status', RewardAssignment::SELECTED)->count());

        $lock = Cache::lock('rewards-evaluate', 120);
        $this->assertTrue($lock->get());
        $skipped = app(RewardEligibilityService::class)->evaluate();
        $lock->release();
        $this->assertTrue($skipped['skipped']);
        $this->assertSame(1, RewardAssignment::query()->where('member_id', $other->member_id)->where('status', RewardAssignment::SELECTED)->count());
    }

    public function test_delivered_rewards_stay_in_place_when_a_later_campaign_also_qualifies(): void
    {
        $package = $this->package('DEMO-HILLS');
        $member = $this->member('STKEEP001', 'ST666666', 'Kept Member');
        $admin = User::factory()->create(['is_admin' => true]);
        $this->purchase($member, $package, 'approved', '2026-10-10 04:00:00', '10000.0000');
        $october = $this->campaign('October keep', '10000.0000', '2026-10-01', '2026-10-31', RewardCampaign::ACTIVE, 'Phone');
        app(RewardEligibilityService::class)->evaluate($october->id);
        $delivery = RewardAssignment::query()->where('member_id', $member->member_id)->firstOrFail()->delivery;
        $delivery->forceFill(['status' => RewardDelivery::DELIVERED, 'recipient_confirmation' => 'Signed'])->save();

        $this->purchase($member, $package, 'approved', '2026-11-10 04:00:00', '20000.0000');
        $november = $this->campaign('November keep', '20000.0000', '2026-11-01', '2026-11-30', RewardCampaign::ACTIVE, 'Motorbike');
        app(RewardEligibilityService::class)->evaluate();

        $delivery->refresh();
        $this->assertSame(RewardDelivery::DELIVERED, $delivery->status);
        $this->assertSame($november->id, RewardAssignment::query()->where('member_id', $member->member_id)->where('status', RewardAssignment::SELECTED)->value('conflict_campaign_id'));
        $this->assertSame(0, RewardAssignment::query()->where('campaign_id', $november->id)->where('status', RewardAssignment::SELECTED)->count());
        $this->assertSame(RewardQualification::SELECTED, RewardQualification::query()->where('campaign_id', $october->id)->value('status'));

        $this->actingAs($admin)->put(route('admin.rewards.update', $october), [
            'title' => 'October keep',
            'gift_name' => 'Phone',
            'target_amount' => '1',
            'starts_on' => '2026-10-01',
            'ends_on' => '2026-10-31',
        ])->assertSessionHasErrors('target_amount');
        $this->assertSame('10000.0000', $october->refresh()->target_amount);
    }

    public function test_member_pages_stay_private_and_exports_use_the_stored_rows(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $package = $this->package('DEMO-HILLS');
        $visible = $this->member('STSEE0001', 'ST666666', 'Visible Member');
        $hidden = $this->member('STHID0001', 'ST666666', 'Hidden Member');
        $this->purchase($visible, $package, 'approved', '2026-10-10 04:00:00', '10000.0000');
        $campaign = $this->campaign('Visible campaign', '10000.0000', '2026-10-01', '2026-10-31', RewardCampaign::ACTIVE, 'Phone');
        $campaign->forceFill(['admin_notes' => 'INTERNAL-NOTE-SECRET'])->save();
        app(RewardEligibilityService::class)->evaluate($campaign->id);
        $qualification = RewardQualification::query()->where('member_id', $visible->member_id)->firstOrFail();
        $qualification->assignment->delivery->forceFill([
            'tracking_reference' => 'TRACK-VISIBLE',
            'internal_notes' => 'COURIER-NOTE-SECRET',
        ])->save();

        $this->withSession(['member_context_id' => $visible->member_id])
            ->get(route('member.rewards.index'))
            ->assertOk()
            ->assertSeeText('Visible Member')
            ->assertSeeText('Phone')
            ->assertSeeText('TRACK-VISIBLE')
            ->assertSee('width=device-width', false)
            ->assertDontSeeText('Hidden Member')
            ->assertDontSeeText('INTERNAL-NOTE-SECRET')
            ->assertDontSeeText('COURIER-NOTE-SECRET');

        $this->withSession(['member_context_id' => null])
            ->get(route('member.rewards.index'))
            ->assertForbidden();

        $this->actingAs($admin)->post(route('admin.rewards.deliveries.update', $qualification), [
            'status' => RewardDelivery::DELIVERED,
        ])->assertSessionHasErrors('status');

        $csv = $this->actingAs($admin)->get(route('admin.rewards.export', [
            'format' => 'csv',
            'member_id' => $visible->member_id,
        ]));
        $csv->assertOk();
        $this->assertStringContainsString('STSEE0001', $csv->streamedContent());
        $this->assertStringNotContainsString('STHID0001', $csv->streamedContent());

        $this->actingAs($admin)->get(route('admin.rewards.export', [
            'format' => 'xlsx',
            'member_id' => $visible->member_id,
        ]))->assertOk();
        $pdf = $this->actingAs($admin)->get(route('admin.rewards.export', [
            'format' => 'pdf',
            'member_id' => $visible->member_id,
        ]));
        $pdf->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->streamedContent());

        $this->actingAs($admin)->get(route('admin.rewards.eligible'))
            ->assertOk()
            ->assertSeeText('STSEE0001')
            ->assertSee('width=device-width', false);
    }

    private function member(string $memberId, string $sponsorId, string $name, string $email = ''): Member
    {
        return Member::query()->create([
            'member_id' => $memberId,
            'sponsor_id' => $sponsorId,
            'sponsor_name' => 'Sponsor',
            'member_name' => $name,
            'mobile_no' => str_pad((string) random_int(1000000000, 1999999999), 10, '0', STR_PAD_LEFT),
            'email' => $email !== '' ? $email : strtolower($memberId).'@example.test',
            'status' => 'active',
            'roi_wallet_amount' => '0.0000',
            'working_wallet_amount' => '0.0000',
            'activation_wallet_amount' => '0.0000',
        ]);
    }

    private function package(string $code, string $type = Package::TYPE_TOUR_ROI): Package
    {
        return Package::query()->create([
            'code' => $code,
            'package_type' => $type,
            'name' => $code,
            'price_usd' => '1000.0000',
            'denomination' => 'USDT',
            'roi_eligible' => $type !== Package::TYPE_TOUR,
            'level_commission_eligible' => $type !== Package::TYPE_TOUR,
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }

    private function purchase(Member $member, Package $package, string $status, string $reviewedAt, string $price, string $denomination = 'USDT', string $reference = ''): PackagePurchase
    {
        $reviewed = $status === 'approved' ? CarbonImmutable::parse($reviewedAt, 'UTC') : null;

        return PackagePurchase::query()->create([
            'reference' => 'PUR-'.$member->member_id.'-'.Str::upper(Str::random(6)),
            'idempotency_key' => (string) Str::uuid(),
            'member_id' => $member->member_id,
            'package_id' => $package->id,
            'package_code' => $package->code,
            'package_name' => $package->name,
            'package_type' => $package->package_type,
            'price_usd' => $price,
            'denomination' => $denomination,
            'network' => 'TRC20',
            'payment_reference' => $reference !== '' ? $reference : 'TX'.Str::upper(Str::random(8)),
            'status' => $status,
            'submitted_at' => CarbonImmutable::parse('2026-09-01 00:00:00', 'UTC'),
            'reviewed_at' => $reviewed,
        ]);
    }

    private function campaign(string $title, string $target, string $start, string $end, string $status, string $gift = 'Gift'): RewardCampaign
    {
        return RewardCampaign::query()->create([
            'title' => $title,
            'gift_name' => $gift,
            'target_amount' => $target,
            'denomination' => 'USDT',
            'starts_on' => $start,
            'ends_on' => $end,
            'status' => $status,
        ]);
    }
}
