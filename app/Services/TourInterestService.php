<?php

namespace App\Services;

use App\Models\Member;
use App\Models\PackagePurchase;
use App\Models\SupportNotification;
use App\Models\TourInterestRequest;
use App\Models\TourInterestRequestEvent;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Tour enquiry / callback interest — not a booking and never credits wallets.
 */
class TourInterestService
{
    public const CONFIRMATION_MESSAGE = 'Thank you for your interest. Our travel team will contact you shortly to discuss your tour requirements.';

    public function activeForMember(string $memberId): ?TourInterestRequest
    {
        if (! Schema::hasTable('tour_interest_requests')) {
            return null;
        }

        return TourInterestRequest::query()
            ->where('member_id', $memberId)
            ->where('active_marker', '1')
            ->latest('id')
            ->first();
    }

    public function expressInterest(Member $member, ?string $ip = null): TourInterestRequest
    {
        unset($ip);

        return DB::transaction(function () use ($member): TourInterestRequest {
            Member::query()->where('member_id', $member->member_id)->lockForUpdate()->firstOrFail();

            $existing = TourInterestRequest::query()
                ->where('member_id', $member->member_id)
                ->where('active_marker', '1')
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $existing;
            }

            try {
                $request = TourInterestRequest::query()->create([
                    'member_id' => $member->member_id,
                    'status' => TourInterestRequest::STATUS_PENDING,
                    'active_marker' => '1',
                    'submitted_at' => now(),
                    'source' => 'member_dashboard',
                ]);
            } catch (UniqueConstraintViolationException) {
                return TourInterestRequest::query()
                    ->where('member_id', $member->member_id)
                    ->where('active_marker', '1')
                    ->firstOrFail();
            }

            $this->event($request, 'submitted', null, TourInterestRequest::STATUS_PENDING, 'Member expressed tour interest.', 'member', $member->member_id, null);
            $this->notifyAdmins($request, 'New tour interest from '.$member->member_id.' ('.$member->member_name.').');

            return $request->fresh();
        });
    }

    public function withdrawInterest(Member $member): TourInterestRequest
    {
        return DB::transaction(function () use ($member): TourInterestRequest {
            $request = TourInterestRequest::query()
                ->where('member_id', $member->member_id)
                ->where('active_marker', '1')
                ->lockForUpdate()
                ->first();

            if ($request === null) {
                throw ValidationException::withMessages([
                    'interest' => 'There is no active tour interest to withdraw.',
                ]);
            }

            $from = $request->status;
            $request->forceFill([
                'status' => TourInterestRequest::STATUS_CANCELLED,
                'active_marker' => null,
                'withdrawn_at' => now(),
                'closed_at' => now(),
            ])->save();

            $this->event($request, 'withdrawn', $from, TourInterestRequest::STATUS_CANCELLED, 'Member withdrew tour interest.', 'member', $member->member_id, null);

            return $request->fresh();
        });
    }

    /**
     * @param  array{status: string, admin_notes?: ?string, contacted_at?: ?string, assigned_admin_id?: ?int, note?: ?string}  $payload
     */
    public function updateStatus(TourInterestRequest $request, array $payload, User $admin): TourInterestRequest
    {
        $status = (string) ($payload['status'] ?? '');
        if (! in_array($status, [
            TourInterestRequest::STATUS_PENDING,
            TourInterestRequest::STATUS_CONTACTED,
            TourInterestRequest::STATUS_CONFIRMED,
            TourInterestRequest::STATUS_COMPLETED,
            TourInterestRequest::STATUS_CANCELLED,
        ], true)) {
            throw ValidationException::withMessages(['status' => 'Invalid tour request status.']);
        }

        return DB::transaction(function () use ($request, $payload, $admin, $status): TourInterestRequest {
            $locked = TourInterestRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();
            $from = $locked->status;

            $active = in_array($status, TourInterestRequest::ACTIVE_STATUSES, true);
            if ($active) {
                $conflict = TourInterestRequest::query()
                    ->where('member_id', $locked->member_id)
                    ->where('active_marker', '1')
                    ->where('id', '!=', $locked->id)
                    ->lockForUpdate()
                    ->exists();
                if ($conflict) {
                    throw ValidationException::withMessages([
                        'status' => 'This member already has another active tour request.',
                    ]);
                }
            }

            $locked->status = $status;
            $locked->active_marker = $active ? '1' : null;
            $locked->assigned_admin_id = array_key_exists('assigned_admin_id', $payload)
                ? $payload['assigned_admin_id']
                : ($locked->assigned_admin_id ?? $admin->id);

            if (array_key_exists('admin_notes', $payload)) {
                $locked->admin_notes = $payload['admin_notes'];
            }

            if ($status === TourInterestRequest::STATUS_CONTACTED) {
                $locked->contacted_at = ! empty($payload['contacted_at'])
                    ? $payload['contacted_at']
                    : ($locked->contacted_at ?? now());
            }

            if (in_array($status, TourInterestRequest::CLOSED_STATUSES, true)) {
                $locked->closed_at = $locked->closed_at ?? now();
            }

            $locked->save();

            $note = trim((string) ($payload['note'] ?? ''));
            $this->event(
                $locked,
                'status_updated',
                $from,
                $status,
                $note !== '' ? $note : 'Status updated by admin.',
                'admin',
                null,
                $admin->id
            );

            if ($from !== $status) {
                $this->notifyMember(
                    $locked,
                    'Your tour interest request is now '.$locked->statusLabel().'.'
                );
            }

            return $locked->fresh(['member', 'assignee', 'events']);
        });
    }

    /**
     * @return array{total: int, pending: int, contacted: int, confirmed: int, completed: int, cancelled: int}
     */
    public function kpis(): array
    {
        if (! Schema::hasTable('tour_interest_requests')) {
            return [
                'total' => 0,
                'pending' => 0,
                'contacted' => 0,
                'confirmed' => 0,
                'completed' => 0,
                'cancelled' => 0,
            ];
        }

        $counts = TourInterestRequest::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return [
            'total' => (int) TourInterestRequest::query()->count(),
            'pending' => (int) ($counts[TourInterestRequest::STATUS_PENDING] ?? 0),
            'contacted' => (int) ($counts[TourInterestRequest::STATUS_CONTACTED] ?? 0),
            'confirmed' => (int) ($counts[TourInterestRequest::STATUS_CONFIRMED] ?? 0),
            'completed' => (int) ($counts[TourInterestRequest::STATUS_COMPLETED] ?? 0),
            'cancelled' => (int) ($counts[TourInterestRequest::STATUS_CANCELLED] ?? 0),
        ];
    }

    public function pendingCount(): int
    {
        if (! Schema::hasTable('tour_interest_requests')) {
            return 0;
        }

        return (int) TourInterestRequest::query()
            ->where('status', TourInterestRequest::STATUS_PENDING)
            ->count();
    }

    /**
     * Approved packages snapshot for admin review (eligibility display only).
     *
     * @return list<array{code: string, name: string, reviewed_at: ?string}>
     */
    public function approvedPackagesFor(string $memberId): array
    {
        return PackagePurchase::query()
            ->where('member_id', $memberId)
            ->where('status', PackagePurchase::APPROVED)
            ->orderByDesc('reviewed_at')
            ->get(['package_code', 'package_name', 'reviewed_at'])
            ->map(fn (PackagePurchase $p): array => [
                'code' => (string) $p->package_code,
                'name' => (string) $p->package_name,
                'reviewed_at' => optional($p->reviewed_at)?->timezone('Asia/Kolkata')->format('d M Y'),
            ])
            ->all();
    }

    private function event(
        TourInterestRequest $request,
        string $action,
        ?string $from,
        ?string $to,
        ?string $note,
        string $actorType,
        ?string $actorMemberId,
        ?int $actorAdminId
    ): void {
        TourInterestRequestEvent::query()->create([
            'tour_interest_request_id' => $request->id,
            'action' => $action,
            'from_status' => $from,
            'to_status' => $to,
            'note' => $note,
            'actor_type' => $actorType,
            'actor_member_id' => $actorMemberId,
            'actor_admin_id' => $actorAdminId,
        ]);
    }

    private function notifyAdmins(TourInterestRequest $request, string $summary): void
    {
        if (! Schema::hasTable('support_notifications')) {
            return;
        }

        $summary = mb_substr(trim($summary), 0, 180);
        $admins = User::query()->where('is_admin', true)->get(['id']);
        if ($admins->isEmpty()) {
            SupportNotification::query()->create([
                'audience' => SupportNotification::AUDIENCE_ADMIN,
                'member_id' => null,
                'user_id' => null,
                'support_ticket_id' => null,
                'tour_interest_request_id' => $request->id,
                'summary' => $summary,
                'email_status' => SupportNotification::EMAIL_SKIPPED,
            ]);

            return;
        }

        foreach ($admins as $admin) {
            SupportNotification::query()->create([
                'audience' => SupportNotification::AUDIENCE_ADMIN,
                'member_id' => null,
                'user_id' => $admin->id,
                'support_ticket_id' => null,
                'tour_interest_request_id' => $request->id,
                'summary' => $summary,
                'email_status' => SupportNotification::EMAIL_SKIPPED,
            ]);
        }
    }

    private function notifyMember(TourInterestRequest $request, string $summary): void
    {
        if (! Schema::hasTable('support_notifications')) {
            return;
        }

        SupportNotification::query()->create([
            'audience' => SupportNotification::AUDIENCE_MEMBER,
            'member_id' => $request->member_id,
            'user_id' => null,
            'support_ticket_id' => null,
            'tour_interest_request_id' => $request->id,
            'summary' => mb_substr(trim($summary), 0, 180),
            'email_status' => SupportNotification::EMAIL_SKIPPED,
        ]);
    }
}
