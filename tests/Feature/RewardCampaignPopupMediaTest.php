<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\Package;
use App\Models\PackagePurchase;
use App\Models\RewardCampaign;
use App\Models\User;
use App\Services\Rewards\RewardCampaignMedia;
use App\Services\Rewards\RewardEligibilityService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class RewardCampaignPopupMediaTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_promotable_campaign_popup_after_login_and_not_on_refresh(): void
    {
        $this->freezeRewardClock('2026-11-15 10:00:00');

        $member = $this->memberWithPassword('STPOP001', 'popup-secret');
        $campaign = $this->campaign('SUNDARBAN TRIP', '500.0000', '2026-11-01', '2026-12-31', RewardCampaign::ACTIVE, [
            'promote_on_login' => true,
            'description' => 'Grow your team business and win an exclusive Sundarban Trip!',
        ]);
        $this->campaign('Draft Quiet', '100.0000', '2026-11-01', '2026-12-31', RewardCampaign::DRAFT, [
            'promote_on_login' => true,
        ]);
        $this->campaign('Expired Trip', '100.0000', '2026-01-01', '2026-01-31', RewardCampaign::ACTIVE, [
            'promote_on_login' => true,
        ]);
        $this->campaign('Paused Trip', '100.0000', '2026-11-01', '2026-12-31', RewardCampaign::PAUSED, [
            'promote_on_login' => true,
        ]);

        $this->post(route('member.login.store'), [
            'member_id' => $member->member_id,
            'password' => 'popup-secret',
        ])->assertRedirect(route('member.dashboard'));

        $this->assertTrue(session('reward_popup_pending'));

        $this->get(route('member.dashboard'))
            ->assertOk()
            ->assertSee('id="reward-campaign-popup"', false)
            ->assertSee('ds-reward-popup-media', false)
            ->assertSeeText('SUNDARBAN TRIP')
            ->assertSeeText('View Campaign Details')
            ->assertSeeText('Not Now')
            ->assertSee('width=device-width', false)
            ->assertDontSeeText('Draft Quiet')
            ->assertDontSeeText('Expired Trip');

        $this->assertFalse(session()->has('reward_popup_pending'));
        $this->assertContains($campaign->id, session('reward_popup_dismissed', []));

        $this->get(route('member.dashboard'))
            ->assertOk()
            ->assertDontSee('id="reward-campaign-popup"', false);
    }

    public function test_draft_inactive_and_unpromoted_campaigns_do_not_popup(): void
    {
        $this->freezeRewardClock('2026-11-15 10:00:00');
        $member = $this->memberWithPassword('STPOP002', 'popup-secret');

        $this->campaign('Hidden Draft', '100.0000', '2026-11-01', '2026-12-31', RewardCampaign::DRAFT, [
            'promote_on_login' => true,
        ]);
        $this->campaign('Active No Promo', '100.0000', '2026-11-01', '2026-12-31', RewardCampaign::ACTIVE, [
            'promote_on_login' => false,
        ]);

        $this->post(route('member.login.store'), [
            'member_id' => $member->member_id,
            'password' => 'popup-secret',
        ])->assertRedirect(route('member.dashboard'));

        $this->get(route('member.dashboard'))
            ->assertOk()
            ->assertDontSee('id="reward-campaign-popup"', false);
    }

    public function test_admin_banner_and_video_upload_replace_delete_and_member_markup(): void
    {
        Storage::fake('local');
        $this->freezeRewardClock('2026-11-15 10:00:00');

        $admin = User::factory()->create(['is_admin' => true]);
        $member = $this->memberWithPassword('STPOP003', 'popup-secret');

        $store = $this->actingAs($admin)->post(route('admin.rewards.store'), [
            'title' => 'SUNDARBAN TRIP Campaign',
            'gift_name' => 'SUNDARBAN TRIP',
            'target_amount' => '500',
            'starts_on' => '2026-11-01',
            'ends_on' => '2026-12-31',
            'promote_on_login' => '1',
            'image' => UploadedFile::fake()->image('sundarban-banner.jpg', 640, 360),
            'video' => $this->fakeVideo('sundarban-intro.mp4'),
        ]);
        $store->assertSessionDoesntHaveErrors();
        $store->assertRedirect();

        $campaign = RewardCampaign::query()->firstOrFail();
        $this->assertTrue($campaign->hasBanner());
        $this->assertTrue($campaign->hasVideo());
        $bannerPath = $campaign->gift_image_path;
        $videoPath = $campaign->video_path;
        Storage::disk('local')->assertExists($bannerPath);
        Storage::disk('local')->assertExists($videoPath);

        $this->actingAs($admin)->post(route('admin.rewards.publish', $campaign))->assertRedirect();

        $this->actingAs($admin)->get(route('admin.rewards.show', $campaign))
            ->assertOk()
            ->assertSee('ds-reward-video-preview', false)
            ->assertSee(route('admin.rewards.video', $campaign), false);

        $this->actingAs($admin)->get(route('admin.rewards.index'))
            ->assertOk()
            ->assertSee('ri-image-line', false)
            ->assertSee('ri-video-line', false);

        $this->withSession(['member_context_id' => $member->member_id])
            ->get(route('member.rewards.show', $campaign))
            ->assertOk()
            ->assertSeeText('SUNDARBAN TRIP')
            ->assertSee('ds-reward-detail-banner', false)
            ->assertSee('ds-reward-detail-video', false)
            ->assertSee(route('member.rewards.video', $campaign), false);

        $this->withSession(['member_context_id' => $member->member_id])
            ->get(route('member.rewards.image', $campaign))
            ->assertOk();
        $this->withSession(['member_context_id' => $member->member_id])
            ->get(route('member.rewards.video', $campaign))
            ->assertOk();

        $replacement = UploadedFile::fake()->image('replacement.png', 320, 180);
        $this->actingAs($admin)->put(route('admin.rewards.update', $campaign), [
            'title' => $campaign->title,
            'gift_name' => $campaign->gift_name,
            'target_amount' => $campaign->target_amount,
            'starts_on' => $campaign->starts_on->toDateString(),
            'ends_on' => $campaign->ends_on->toDateString(),
            'promote_on_login' => '1',
            'image' => $replacement,
            'video' => $this->fakeVideo('replacement.mp4'),
        ])->assertRedirect();

        $campaign->refresh();
        Storage::disk('local')->assertMissing($bannerPath);
        Storage::disk('local')->assertMissing($videoPath);
        Storage::disk('local')->assertExists($campaign->gift_image_path);
        Storage::disk('local')->assertExists($campaign->video_path);

        $keepBanner = $campaign->gift_image_path;
        $keepVideo = $campaign->video_path;
        $shared = $this->campaign('Shared Media', '100.0000', '2026-11-01', '2026-12-31', RewardCampaign::DRAFT);
        $shared->forceFill([
            'gift_image_path' => $keepBanner,
            'video_path' => $keepVideo,
        ])->save();

        $this->actingAs($admin)->put(route('admin.rewards.update', $campaign), [
            'title' => $campaign->title,
            'gift_name' => $campaign->gift_name,
            'target_amount' => $campaign->target_amount,
            'starts_on' => $campaign->starts_on->toDateString(),
            'ends_on' => $campaign->ends_on->toDateString(),
            'promote_on_login' => '1',
            'remove_image' => '1',
            'remove_video' => '1',
        ])->assertRedirect();

        $campaign->refresh();
        $this->assertNull($campaign->gift_image_path);
        $this->assertNull($campaign->video_path);
        Storage::disk('local')->assertExists($keepBanner);
        Storage::disk('local')->assertExists($keepVideo);

        $this->actingAs($admin)->delete(route('admin.rewards.destroy', $shared))->assertRedirect();
        Storage::disk('local')->assertMissing($keepBanner);
        Storage::disk('local')->assertMissing($keepVideo);
    }

    public function test_permanent_campaign_delete_cleans_owned_media_and_blocks_unauthorized(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['is_admin' => true]);
        $memberUser = User::factory()->create(['is_admin' => false]);

        $this->actingAs($admin)->post(route('admin.rewards.store'), [
            'title' => 'Delete Me',
            'gift_name' => 'Gift',
            'target_amount' => '50',
            'starts_on' => '2026-11-01',
            'ends_on' => '2026-12-31',
            'image' => UploadedFile::fake()->image('banner.png', 40, 40),
            'video' => $this->fakeVideo('clip.mp4'),
        ])->assertRedirect();

        $campaign = RewardCampaign::query()->firstOrFail();
        $banner = $campaign->gift_image_path;
        $video = $campaign->video_path;

        $this->actingAs($memberUser)
            ->delete(route('admin.rewards.destroy', $campaign))
            ->assertForbidden();
        Storage::disk('local')->assertExists($banner);

        $this->actingAs($admin)->post(route('admin.rewards.publish', $campaign))->assertRedirect();
        $this->actingAs($admin)
            ->delete(route('admin.rewards.destroy', $campaign))
            ->assertSessionHasErrors('status');
        Storage::disk('local')->assertExists($banner);

        $campaign->forceFill(['status' => RewardCampaign::CANCELLED])->save();
        $this->actingAs($admin)->delete(route('admin.rewards.destroy', $campaign))->assertRedirect(route('admin.rewards.index'));
        $this->assertSame(0, RewardCampaign::query()->count());
        Storage::disk('local')->assertMissing($banner);
        Storage::disk('local')->assertMissing($video);
    }

    public function test_invalid_and_oversized_uploads_are_rejected(): void
    {
        Storage::fake('local');
        config(['deltasupport.rewards.image_max_kb' => 100, 'deltasupport.rewards.video_max_kb' => 100]);
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('admin.rewards.store'), [
            'title' => 'Bad Media',
            'gift_name' => 'Gift',
            'target_amount' => '50',
            'starts_on' => '2026-11-01',
            'ends_on' => '2026-12-31',
            'image' => UploadedFile::fake()->create('shell.php', 20, 'application/x-php'),
        ])->assertSessionHasErrors('image');

        $this->actingAs($admin)->post(route('admin.rewards.store'), [
            'title' => 'Bad Media',
            'gift_name' => 'Gift',
            'target_amount' => '50',
            'starts_on' => '2026-11-01',
            'ends_on' => '2026-12-31',
            'image' => UploadedFile::fake()->image('huge.jpg', 40, 40)->size(500),
        ])->assertSessionHasErrors('image');

        $this->actingAs($admin)->post(route('admin.rewards.store'), [
            'title' => 'Bad Media',
            'gift_name' => 'Gift',
            'target_amount' => '50',
            'starts_on' => '2026-11-01',
            'ends_on' => '2026-12-31',
            'video' => UploadedFile::fake()->create('notes.txt', 20, 'text/plain'),
        ])->assertSessionHasErrors('video');

        $this->actingAs($admin)->post(route('admin.rewards.store'), [
            'title' => 'Bad Media',
            'gift_name' => 'Gift',
            'target_amount' => '50',
            'starts_on' => '2026-11-01',
            'ends_on' => '2026-12-31',
            'video' => $this->fakeVideo('big.mp4')->size(500),
        ])->assertSessionHasErrors('video');

        $this->assertSame(0, RewardCampaign::query()->count());
    }

    public function test_reward_eligibility_unchanged_by_popup_or_media(): void
    {
        Storage::fake('local');
        $this->freezeRewardClock('2026-11-20 12:00:00');

        $package = Package::query()->create([
            'code' => 'POP-PKG',
            'package_type' => Package::TYPE_TOUR_ROI,
            'name' => 'POP-PKG',
            'price_usd' => '1000.0000',
            'denomination' => 'USDT',
            'roi_eligible' => true,
            'level_commission_eligible' => true,
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $root = $this->memberWithPassword('STELIG01', 'popup-secret');
        PackagePurchase::query()->create([
            'reference' => 'PUR-POP-1',
            'idempotency_key' => (string) Str::uuid(),
            'member_id' => $root->member_id,
            'package_id' => $package->id,
            'package_code' => $package->code,
            'package_name' => $package->name,
            'package_type' => $package->package_type,
            'price_usd' => '500.0000',
            'denomination' => 'USDT',
            'network' => 'TRC20',
            'payment_reference' => 'TXPOP1',
            'status' => 'approved',
            'submitted_at' => CarbonImmutable::parse('2026-11-10 00:00:00', 'UTC'),
            'reviewed_at' => CarbonImmutable::parse('2026-11-10 00:00:00', 'UTC'),
        ]);

        $campaign = $this->campaign('Elig Media', '500.0000', '2026-11-01', '2026-12-31', RewardCampaign::ACTIVE, [
            'promote_on_login' => true,
            'gift_image_path' => null,
        ]);

        $beforeRoi = $root->roi_wallet_amount;
        $result = app(RewardEligibilityService::class)->evaluate($campaign->id);
        $this->assertFalse($result['skipped']);
        $this->assertSame(1, $result['selected']);
        $this->assertSame($beforeRoi, $root->refresh()->roi_wallet_amount);

        $this->post(route('member.login.store'), [
            'member_id' => $root->member_id,
            'password' => 'popup-secret',
        ]);
        $this->get(route('member.dashboard'))->assertOk()->assertSee('id="reward-campaign-popup"', false);
        $this->withSession(['member_context_id' => $root->member_id])
            ->get(route('member.rewards.show', $campaign))
            ->assertOk();

        $this->assertSame(1, $campaign->fresh()->assignments()->count());
        $this->assertSame($beforeRoi, $root->refresh()->roi_wallet_amount);
        $this->assertFalse(config('deltasupport.package_income.enabled'));
    }

    public function test_path_traversal_media_paths_are_rejected(): void
    {
        $media = app(RewardCampaignMedia::class);
        $this->assertFalse($media->isSafeImagePath('../etc/passwd'));
        $this->assertFalse($media->isSafeImagePath('rewards/../secret.jpg'));
        $this->assertFalse($media->isSafeImagePath('rewards/videos/clip.mp4'));
        $this->assertFalse($media->isSafeVideoPath('rewards/banner.jpg'));
        $this->assertTrue($media->isSafeImagePath('rewards/abc.jpg'));
        $this->assertTrue($media->isSafeVideoPath('rewards/videos/abc.mp4'));
    }

    public function test_existing_admin_and_member_reward_routes_remain_functional(): void
    {
        $this->freezeRewardClock('2026-11-15 10:00:00');
        $admin = User::factory()->create(['is_admin' => true]);
        $member = $this->memberWithPassword('STPOP099', 'popup-secret');
        $campaign = $this->campaign('Route Check', '100.0000', '2026-11-01', '2026-12-31', RewardCampaign::ACTIVE);

        $this->actingAs($admin)->get(route('admin.rewards.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.rewards.create'))->assertOk();
        $this->actingAs($admin)->get(route('admin.rewards.show', $campaign))->assertOk();
        $this->actingAs($admin)->get(route('admin.rewards.edit', $campaign))->assertOk();
        $this->actingAs($admin)->get(route('admin.rewards.eligible'))->assertOk();

        $this->withSession(['member_context_id' => $member->member_id])
            ->get(route('member.rewards.index'))
            ->assertOk()
            ->assertSeeText('Route Check');
        $this->withSession(['member_context_id' => $member->member_id])
            ->get(route('member.rewards.show', $campaign))
            ->assertOk();
    }

    private function memberWithPassword(string $memberId, string $password): Member
    {
        return Member::query()->create([
            'member_id' => $memberId,
            'sponsor_id' => 'ST666666',
            'sponsor_name' => 'Sponsor',
            'member_name' => 'Popup Member '.$memberId,
            'mobile_no' => str_pad((string) random_int(1000000000, 1999999999), 10, '0', STR_PAD_LEFT),
            'email' => strtolower($memberId).'@example.test',
            'password' => Hash::make($password),
            'status' => 'active',
            'roi_wallet_amount' => '0.0000',
            'working_wallet_amount' => '0.0000',
            'activation_wallet_amount' => '0.0000',
        ]);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function campaign(string $title, string $target, string $start, string $end, string $status, array $extra = []): RewardCampaign
    {
        return RewardCampaign::query()->create(array_merge([
            'title' => $title,
            'gift_name' => $title.' Gift',
            'target_amount' => $target,
            'denomination' => 'USDT',
            'starts_on' => $start,
            'ends_on' => $end,
            'status' => $status,
            'promote_on_login' => true,
        ], $extra));
    }

    private function fakeVideo(string $name): UploadedFile
    {
        $binary = hex2bin('00000018667479706d703432000000006d70343269736f6d') ?: 'ftypmp42';

        return UploadedFile::fake()->createWithContent($name, $binary);
    }

    private function freezeRewardClock(string $localTime): void
    {
        $now = CarbonImmutable::parse($localTime, 'Asia/Kolkata');
        Carbon::setTestNow($now);
        CarbonImmutable::setTestNow($now);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }
}
