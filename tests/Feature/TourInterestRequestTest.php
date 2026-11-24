<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\SupportNotification;
use App\Models\TourInterestRequest;
use App\Models\User;
use App\Services\TourInterestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TourInterestRequestTest extends TestCase
{
    use RefreshDatabase;

    private function member(string $id = 'ST880001'): Member
    {
        return Member::query()->create([
            'member_id' => $id,
            'sponsor_id' => 'ST666666',
            'sponsor_name' => 'Admin',
            'member_name' => 'Tour Member '.$id,
            'wallet_address' => '0x'.str_pad(substr($id, -6), 40, '0'),
            'activation_wallet_amount' => '0.0000',
            'working_wallet_amount' => '0.0000',
            'roi_wallet_amount' => '0.0000',
            'mobile_no' => '9876543210',
            'email' => strtolower($id).'@example.test',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);
    }

    public function test_member_can_express_and_withdraw_interest_without_duplicate_active(): void
    {
        $member = $this->member();
        $service = app(TourInterestService::class);

        $first = $service->expressInterest($member);
        $second = $service->expressInterest($member);
        $this->assertSame($first->id, $second->id);
        $this->assertSame(TourInterestRequest::STATUS_PENDING, $first->status);
        $this->assertSame(1, TourInterestRequest::query()->where('member_id', $member->member_id)->where('active_marker', '1')->count());
        $this->assertTrue(
            SupportNotification::query()
                ->where('audience', SupportNotification::AUDIENCE_ADMIN)
                ->where('tour_interest_request_id', $first->id)
                ->exists()
        );

        $this->withSession(['member_context_id' => $member->member_id])
            ->get(route('member.dashboard'))
            ->assertOk()
            ->assertSeeText('Interested in a Tour?')
            ->assertSeeText('Pending')
            ->assertSeeText(TourInterestService::CONFIRMATION_MESSAGE);

        $withdrawn = $service->withdrawInterest($member);
        $this->assertSame(TourInterestRequest::STATUS_CANCELLED, $withdrawn->status);
        $this->assertNull($withdrawn->active_marker);

        $again = $service->expressInterest($member);
        $this->assertNotSame($first->id, $again->id);
        $this->assertSame(2, TourInterestRequest::query()->where('member_id', $member->member_id)->count());
    }

    public function test_admin_can_update_status_and_member_is_notified_without_seeing_internal_notes(): void
    {
        $member = $this->member('ST880002');
        $admin = User::factory()->create(['is_admin' => true]);
        $service = app(TourInterestService::class);
        $request = $service->expressInterest($member);

        $updated = $service->updateStatus($request, [
            'status' => TourInterestRequest::STATUS_CONTACTED,
            'admin_notes' => 'SECRET_INTERNAL_NOTE_XYZ',
            'note' => 'Called customer',
            'assigned_admin_id' => $admin->id,
        ], $admin);

        $this->assertSame(TourInterestRequest::STATUS_CONTACTED, $updated->status);
        $this->assertNotNull($updated->contacted_at);
        $this->assertTrue(
            SupportNotification::query()
                ->where('audience', SupportNotification::AUDIENCE_MEMBER)
                ->where('member_id', $member->member_id)
                ->where('tour_interest_request_id', $request->id)
                ->exists()
        );

        $this->withSession(['member_context_id' => $member->member_id])
            ->get(route('member.dashboard'))
            ->assertOk()
            ->assertSeeText('Contacted')
            ->assertDontSeeText('SECRET_INTERNAL_NOTE_XYZ');

        $this->actingAs($admin)
            ->get(route('admin.tour-requests.show', $request))
            ->assertOk()
            ->assertSeeText('SECRET_INTERNAL_NOTE_XYZ')
            ->assertSeeText('Called customer');
    }

    public function test_admin_index_kpis_export_and_guest_forbidden(): void
    {
        $member = $this->member('ST880003');
        $admin = User::factory()->create(['is_admin' => true]);
        app(TourInterestService::class)->expressInterest($member);

        $this->actingAs($admin)
            ->get(route('admin.tour-requests.index'))
            ->assertOk()
            ->assertSeeText('Tour Requests')
            ->assertSeeText('Pending Requests')
            ->assertSeeText($member->member_id);

        $this->actingAs($admin)
            ->get(route('admin.tour-requests.export'))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->get(route('admin.tour-requests.index'))
            ->assertForbidden();
    }

    public function test_member_http_toggle_routes(): void
    {
        $member = $this->member('ST880004');

        $this->withSession(['member_context_id' => $member->member_id])
            ->post(route('member.tour-interest.enable'))
            ->assertRedirect(route('member.dashboard'));

        $this->assertDatabaseHas('tour_interest_requests', [
            'member_id' => $member->member_id,
            'status' => TourInterestRequest::STATUS_PENDING,
            'active_marker' => '1',
        ]);

        $this->withSession(['member_context_id' => $member->member_id])
            ->post(route('member.tour-interest.enable'))
            ->assertRedirect(route('member.dashboard'));

        $this->assertSame(1, TourInterestRequest::query()->where('member_id', $member->member_id)->count());

        $this->withSession(['member_context_id' => $member->member_id])
            ->post(route('member.tour-interest.withdraw'))
            ->assertRedirect(route('member.dashboard'));
    }
}
