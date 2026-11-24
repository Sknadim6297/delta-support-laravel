<?php

namespace App\Services;

use App\Models\Member;
use App\Models\MemberPaymentBlock;
use App\Models\MemberPaymentControl;
use App\Models\MemberPaymentControlEvent;
use App\Models\PackageIncomeRun;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Member-specific ROI / Level Income payment stop-resume.
 * Does not credit wallets. Does not invent Agent commission.
 * Financial:daily honors blocks only when financialIntegrationAuthorized().
 */
class MemberPaymentControlService
{
    public function financialIntegrationAuthorized(): bool
    {
        return config('deltasupport.member_payment_controls.financial_integration_authorized') === true;
    }

    public function timezone(): string
    {
        return (string) config('deltasupport.package_income.timezone', 'Asia/Kolkata');
    }

    /**
     * Absent control row means all payments enabled (preserve legacy behavior).
     */
    public function controlFor(string $memberId): MemberPaymentControl
    {
        if (! Schema::hasTable('member_payment_controls')) {
            return new MemberPaymentControl([
                'member_id' => $memberId,
                'master_enabled' => true,
                'roi_preference' => true,
                'level_preference' => true,
                'status' => MemberPaymentControl::STATUS_ACTIVE,
            ]);
        }

        return MemberPaymentControl::query()->firstOrNew(
            ['member_id' => $memberId],
            [
                'master_enabled' => true,
                'roi_preference' => true,
                'level_preference' => true,
                'status' => MemberPaymentControl::STATUS_ACTIVE,
            ]
        );
    }

    /**
     * Runtime gate used by PackageIncomeAccrual when integration is authorized.
     * When unauthorized, always returns true (current production-safe default).
     */
    public function allows(string $memberId, string $incomeType, string $businessDate): bool
    {
        if (! $this->financialIntegrationAuthorized()) {
            return true;
        }
        if (! Schema::hasTable('member_payment_blocks')) {
            return true;
        }
        if (! in_array($incomeType, [MemberPaymentBlock::TYPE_ROI, MemberPaymentBlock::TYPE_LEVEL], true)) {
            return true;
        }

        $date = CarbonImmutable::parse($businessDate, $this->timezone())->toDateString();

        $blocked = MemberPaymentBlock::query()
            ->where('member_id', $memberId)
            ->where('income_type', $incomeType)
            ->whereDate('start_date', '<=', $date)
            ->where(function ($query) use ($date): void {
                $query->whereNull('end_date')
                    ->orWhereDate('end_date', '>', $date);
            })
            ->exists();

        return ! $blocked;
    }

    public function allowsRoi(string $memberId, string $businessDate): bool
    {
        return $this->allows($memberId, MemberPaymentBlock::TYPE_ROI, $businessDate);
    }

    public function allowsLevel(string $memberId, string $businessDate): bool
    {
        return $this->allows($memberId, MemberPaymentBlock::TYPE_LEVEL, $businessDate);
    }

    /**
     * Next safe effective business date: today, or tomorrow if today's cycle already succeeded.
     */
    public function suggestEffectiveFrom(): string
    {
        $today = CarbonImmutable::now($this->timezone())->toDateString();
        if ($this->dailyCycleCompleted($today)) {
            return CarbonImmutable::parse($today, $this->timezone())->addDay()->toDateString();
        }

        return $today;
    }

    public function dailyCycleCompleted(string $businessDate): bool
    {
        if (! Schema::hasTable('package_income_runs')) {
            return false;
        }

        return PackageIncomeRun::query()
            ->whereDate('business_date', $businessDate)
            ->where('status', PackageIncomeRun::SUCCEEDED)
            ->exists();
    }

    /**
     * @param  array{
     *   master_enabled?: bool,
     *   roi_preference?: bool,
     *   level_preference?: bool,
     *   reason: string,
     *   effective_from?: string,
     *   confirm: bool
     * }  $payload
     */
    public function apply(string $memberId, array $payload, User $admin, ?string $ip = null): MemberPaymentControl
    {
        Member::query()->where('member_id', $memberId)->firstOrFail();

        $reason = trim((string) ($payload['reason'] ?? ''));
        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'A reason is required for every payment control change.']);
        }
        if (! ($payload['confirm'] ?? false)) {
            throw ValidationException::withMessages(['confirm' => 'Confirm the payment control change.']);
        }

        $requestedEffective = (string) ($payload['effective_from'] ?? $this->suggestEffectiveFrom());
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $requestedEffective)) {
            throw ValidationException::withMessages(['effective_from' => 'Effective business date must be YYYY-MM-DD.']);
        }

        $effectiveFrom = $this->normalizeEffectiveFrom($requestedEffective);

        return DB::transaction(function () use ($memberId, $payload, $admin, $ip, $reason, $effectiveFrom): MemberPaymentControl {
            $lockedMember = Member::query()->where('member_id', $memberId)->lockForUpdate()->firstOrFail();

            $control = MemberPaymentControl::query()
                ->where('member_id', $lockedMember->member_id)
                ->lockForUpdate()
                ->first();

            if ($control === null) {
                $control = MemberPaymentControl::query()->create([
                    'member_id' => $lockedMember->member_id,
                    'master_enabled' => true,
                    'roi_preference' => true,
                    'level_preference' => true,
                    'status' => MemberPaymentControl::STATUS_ACTIVE,
                ]);
                $control = MemberPaymentControl::query()->whereKey($control->id)->lockForUpdate()->firstOrFail();
            }

            $before = $this->snapshot($control);

            $master = array_key_exists('master_enabled', $payload)
                ? (bool) $payload['master_enabled']
                : (bool) $control->master_enabled;
            $roiPref = array_key_exists('roi_preference', $payload)
                ? (bool) $payload['roi_preference']
                : (bool) $control->roi_preference;
            $levelPref = array_key_exists('level_preference', $payload)
                ? (bool) $payload['level_preference']
                : (bool) $control->level_preference;

            // Master OFF does not destroy individual preferences.
            $control->master_enabled = $master;
            $control->roi_preference = $roiPref;
            $control->level_preference = $levelPref;
            $control->status = $control->computeStatus();
            $control->last_reason = $reason;
            $control->last_effective_from = $effectiveFrom;
            $control->updated_by = $admin->id;
            $control->save();

            $after = $this->snapshot($control);
            $action = $this->actionLabel($before, $after);

            $event = MemberPaymentControlEvent::query()->create([
                'member_id' => $control->member_id,
                'action' => $action,
                'reason' => $reason,
                'effective_from' => $effectiveFrom,
                'before_json' => $before,
                'after_json' => $after,
                'actor_id' => $admin->id,
                'ip_address' => $ip,
            ]);

            $this->syncBlocks(
                $control->member_id,
                (bool) $before['effective_roi'],
                (bool) $before['effective_level'],
                (bool) $after['effective_roi'],
                (bool) $after['effective_level'],
                $effectiveFrom,
                (int) $event->id
            );

            return $control->fresh(['updater']);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(MemberPaymentControl $control): array
    {
        return [
            'master_enabled' => (bool) $control->master_enabled,
            'roi_preference' => (bool) $control->roi_preference,
            'level_preference' => (bool) $control->level_preference,
            'effective_roi' => $control->effectiveRoiEnabled(),
            'effective_level' => $control->effectiveLevelEnabled(),
            'status' => $control->computeStatus(),
        ];
    }

    public function statusBadge(string $memberId): array
    {
        $control = $this->controlFor($memberId);
        if (! $control->exists) {
            return [
                'status' => MemberPaymentControl::STATUS_ACTIVE,
                'label' => 'Active',
                'tone' => 'success',
            ];
        }

        return [
            'status' => $control->status,
            'label' => $control->statusLabel(),
            'tone' => match ($control->status) {
                MemberPaymentControl::STATUS_STOPPED => 'danger',
                MemberPaymentControl::STATUS_PARTIAL => 'warning',
                default => 'success',
            },
        ];
    }

    /**
     * @param  list<string>  $memberIds
     * @return array<string, array{status: string, label: string, tone: string}>
     */
    public function badgesForMemberIds(array $memberIds): array
    {
        $memberIds = array_values(array_unique(array_filter($memberIds)));
        $badges = [];
        foreach ($memberIds as $id) {
            $badges[$id] = [
                'status' => MemberPaymentControl::STATUS_ACTIVE,
                'label' => 'Active',
                'tone' => 'success',
            ];
        }
        if ($memberIds === [] || ! Schema::hasTable('member_payment_controls')) {
            return $badges;
        }

        $rows = MemberPaymentControl::query()->whereIn('member_id', $memberIds)->get()->keyBy('member_id');
        foreach ($rows as $memberId => $control) {
            $badges[$memberId] = [
                'status' => $control->status,
                'label' => $control->statusLabel(),
                'tone' => match ($control->status) {
                    MemberPaymentControl::STATUS_STOPPED => 'danger',
                    MemberPaymentControl::STATUS_PARTIAL => 'warning',
                    default => 'success',
                },
            ];
        }

        return $badges;
    }

    private function normalizeEffectiveFrom(string $requested): string
    {
        $suggested = $this->suggestEffectiveFrom();
        $req = CarbonImmutable::parse($requested, $this->timezone())->startOfDay();
        $min = CarbonImmutable::parse($suggested, $this->timezone())->startOfDay();
        if ($req->lessThan($min)) {
            throw ValidationException::withMessages([
                'effective_from' => 'Effective date cannot be earlier than '.$suggested.' (today’s cycle may already be complete).',
            ]);
        }

        return $req->toDateString();
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    private function actionLabel(array $before, array $after): string
    {
        if ($before['status'] !== $after['status']) {
            return 'status_'.$before['status'].'_to_'.$after['status'];
        }
        if ($before['master_enabled'] !== $after['master_enabled']) {
            return $after['master_enabled'] ? 'master_resumed' : 'master_stopped';
        }
        if ($before['roi_preference'] !== $after['roi_preference']) {
            return $after['roi_preference'] ? 'roi_preference_on' : 'roi_preference_off';
        }
        if ($before['level_preference'] !== $after['level_preference']) {
            return $after['level_preference'] ? 'level_preference_on' : 'level_preference_off';
        }

        return 'payment_control_updated';
    }

    private function syncBlocks(
        string $memberId,
        bool $beforeRoi,
        bool $beforeLevel,
        bool $afterRoi,
        bool $afterLevel,
        string $effectiveFrom,
        int $eventId
    ): void {
        $this->syncOneBlock($memberId, MemberPaymentBlock::TYPE_ROI, $beforeRoi, $afterRoi, $effectiveFrom, $eventId);
        $this->syncOneBlock($memberId, MemberPaymentBlock::TYPE_LEVEL, $beforeLevel, $afterLevel, $effectiveFrom, $eventId);
    }

    private function syncOneBlock(
        string $memberId,
        string $type,
        bool $beforeAllowed,
        bool $afterAllowed,
        string $effectiveFrom,
        int $eventId
    ): void {
        $open = MemberPaymentBlock::query()
            ->where('member_id', $memberId)
            ->where('income_type', $type)
            ->whereNull('end_date')
            ->lockForUpdate()
            ->orderByDesc('id')
            ->first();

        if ($beforeAllowed && ! $afterAllowed) {
            // Newly blocked: close contradictory future opens, open from effectiveFrom.
            if ($open) {
                if ($open->start_date->toDateString() >= $effectiveFrom) {
                    // Replace overlapping future/open block starting on/after effective date.
                    $open->delete();
                } else {
                    $open->forceFill([
                        'end_date' => $effectiveFrom,
                        'closed_by_event_id' => $eventId,
                    ])->save();
                }
            }
            // Avoid duplicate identical open block.
            $exists = MemberPaymentBlock::query()
                ->where('member_id', $memberId)
                ->where('income_type', $type)
                ->whereDate('start_date', $effectiveFrom)
                ->whereNull('end_date')
                ->exists();
            if (! $exists) {
                MemberPaymentBlock::query()->create([
                    'member_id' => $memberId,
                    'income_type' => $type,
                    'start_date' => $effectiveFrom,
                    'end_date' => null,
                    'opened_by_event_id' => $eventId,
                ]);
            }

            return;
        }

        if (! $beforeAllowed && $afterAllowed) {
            // Resume: close open block at effectiveFrom (exclusive end).
            if ($open) {
                if ($open->start_date->toDateString() >= $effectiveFrom) {
                    // Block never applied to a processed day yet — remove.
                    $open->delete();
                } else {
                    $open->forceFill([
                        'end_date' => $effectiveFrom,
                        'closed_by_event_id' => $eventId,
                    ])->save();
                }
            }
        }
    }
}
