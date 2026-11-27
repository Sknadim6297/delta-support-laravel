<?php

namespace App\Services;

use App\Models\LevelIncomeRuleAudit;
use App\Models\LevelIncomeRuleVersion;
use App\Models\LevelIncomeUnlockGrandfatherSnapshot;
use App\Models\Member;
use App\Models\PackageIncomeRun;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Versioned grandfathering of legacy Level Unlock entitlements.
 *
 * Formula (when enabled + snapshot exists):
 *   protected = max(proposed_unlock, min(snapshot_legacy_unlock, current_legacy_unlock))
 *
 * - Blocks reductions caused only by rule migration.
 * - Allows gains under new rules.
 * - If current legacy unlock falls (lost qualifying directs), protection shrinks — normal eligibility.
 * - Members without a snapshot after capture use proposed unlock only (preview and runtime).
 * - Provisional protection (treat live legacy as snapshot) is preview-only and only before capture.
 * - Does not bypass payment controls, ROI terms, package expiry, or income generation filters.
 */
class LevelIncomeUnlockGrandfathering
{
    public const CAPTURE_MODE_AS_OF_TRANSITION = 'as_of_transition_date';

    public const CAPTURE_MODE_PRE_CUTOVER_CURRENT = 'pre_cutover_current';

    public function __construct(
        private readonly LevelIncomeRuleResolver $resolver,
    ) {
    }

    private function eligibility(): ReferralLevelEligibility
    {
        return app(ReferralLevelEligibility::class);
    }

    public function isEnabled(?LevelIncomeRuleVersion $version): bool
    {
        return $version !== null && (bool) ($version->grandfather_existing_unlock ?? false);
    }

    public function hasCapture(?LevelIncomeRuleVersion $version): bool
    {
        return $version !== null && $version->grandfather_captured_at !== null;
    }

    /**
     * Apply protection floor. Safe no-op when disabled or no snapshot.
     * After capture, missing snapshots intentionally use proposed rules only.
     */
    public function apply(string $memberId, LevelIncomeRuleVersion $version, int $proposedUnlock): int
    {
        if (! $this->isEnabled($version)) {
            return max(0, min($proposedUnlock, ReferralLevelEligibility::MAX_LEVELS));
        }

        $proposedUnlock = max(0, min($proposedUnlock, ReferralLevelEligibility::MAX_LEVELS));
        $currentLegacy = $this->currentLegacyUnlock($memberId);
        $snapshotLevels = $this->snapshotLevels($version, $memberId);

        if ($snapshotLevels === null) {
            // Member joined after capture (or never snapshotted) — new rules only.
            return $proposedUnlock;
        }

        $floor = min($snapshotLevels, $currentLegacy);

        return max($proposedUnlock, $floor);
    }

    /**
     * Preview protection. Provisional live-legacy floor is allowed only before capture.
     * After capture, members without snapshots match runtime (proposed only).
     *
     * @return array{
     *   current_legacy: int,
     *   proposed: int,
     *   snapshot_levels: int|null,
     *   protected: int,
     *   floor: int,
     *   grandfather_enabled: bool,
     *   using_provisional_snapshot: bool,
     *   reason: string
     * }
     */
    public function previewMember(string $memberId, LevelIncomeRuleVersion $version, int $proposedUnlock): array
    {
        $currentLegacy = $this->currentLegacyUnlock($memberId);
        $enabled = $this->isEnabled($version);
        $storedSnapshot = $this->snapshotLevels($version, $memberId);
        $usingProvisional = false;
        $hasCapture = $this->hasCapture($version);
        $snapshotForFloor = $storedSnapshot;

        if ($enabled && $storedSnapshot === null && ! $hasCapture) {
            // Provisional preview only — treat today's legacy unlock as what would be captured.
            $snapshotForFloor = $currentLegacy;
            $usingProvisional = true;
        }

        $floor = $enabled && $snapshotForFloor !== null ? min($snapshotForFloor, $currentLegacy) : 0;
        $protected = $enabled
            ? max($proposedUnlock, $floor)
            : $proposedUnlock;

        $reason = match (true) {
            ! $enabled => 'Grandfathering disabled on this version — proposed unlock only.',
            $enabled && $hasCapture && $storedSnapshot === null => 'No grandfather snapshot for this member after capture — proposed unlock only (matches runtime).',
            $usingProvisional && $currentLegacy > $proposedUnlock => 'Provisional protection: capture would floor at min(live legacy, snapshot)=live legacy until directs change.',
            $enabled && ! $usingProvisional && $storedSnapshot !== null && $protected > $proposedUnlock => sprintf(
                'Protected floor %d from snapshot %d capped by current legacy %d.',
                $floor,
                $storedSnapshot,
                $currentLegacy
            ),
            $enabled && $proposedUnlock > $currentLegacy => 'New rules grant more than legacy — member gains levels.',
            default => 'Protected unlock equals proposed (no reduction to prevent).',
        };

        return [
            'current_legacy' => $currentLegacy,
            'proposed' => $proposedUnlock,
            'snapshot_levels' => $usingProvisional ? $currentLegacy : $storedSnapshot,
            'protected' => max(0, min($protected, ReferralLevelEligibility::MAX_LEVELS)),
            'floor' => $floor,
            'grandfather_enabled' => $enabled,
            'using_provisional_snapshot' => $usingProvisional,
            'reason' => $reason,
        ];
    }

    /**
     * Capture legacy unlock baseline for registered members.
     *
     * - transition_date <= today: eligibility reconstructed as-of that date (CAPTURE_MODE_AS_OF_TRANSITION).
     * - transition_date > today: freezes current eligibility for a future cutover
     *   (CAPTURE_MODE_PRE_CUTOVER_CURRENT). This is not an exact future-date snapshot.
     *
     * Replaces prior unauthorized baseline for the version (audited). Blocked after authorization.
     *
     * @return array{
     *   captured: int,
     *   with_unlock: int,
     *   transition_date: string,
     *   version_code: string,
     *   capture_mode: string,
     *   capture_note: string
     * }
     */
    public function captureBaseline(
        LevelIncomeRuleVersion $version,
        User $admin,
        string $transitionDate,
        ?string $ip = null
    ): array {
        if (! Schema::hasTable('level_income_unlock_grandfather_snapshots')) {
            throw ValidationException::withMessages([
                'grandfather' => 'Grandfather snapshot table is missing. Run migrations.',
            ]);
        }
        if ($version->posting_authorized) {
            throw ValidationException::withMessages([
                'grandfather' => 'Cannot recapture baseline while posting is authorized. Revoke first.',
            ]);
        }
        if (! $version->grandfather_existing_unlock) {
            throw ValidationException::withMessages([
                'grandfather_existing_unlock' => 'Enable grandfather_existing_unlock on this version before capturing.',
            ]);
        }

        $transitionDate = CarbonImmutable::parse($transitionDate)->toDateString();
        $today = CarbonImmutable::now()->toDateString();
        $captureMode = $transitionDate > $today
            ? self::CAPTURE_MODE_PRE_CUTOVER_CURRENT
            : self::CAPTURE_MODE_AS_OF_TRANSITION;
        $captureNote = $captureMode === self::CAPTURE_MODE_PRE_CUTOVER_CURRENT
            ? 'Pre-cutover freeze of current legacy eligibility for a future transition date. Not an exact future-date reconstruction. Recapture on/after the transition date before authorize when possible.'
            : 'Legacy eligibility reconstructed as of end-of-day transition date (members and approved purchases on/before that date).';

        $before = [
            'grandfather_captured_at' => optional($version->grandfather_captured_at)?->toIso8601String(),
            'grandfather_capture_mode' => $version->grandfather_capture_mode ?? null,
            'snapshot_count' => LevelIncomeUnlockGrandfatherSnapshot::query()
                ->where('level_income_rule_version_id', $version->id)
                ->count(),
        ];

        return DB::transaction(function () use (
            $version,
            $admin,
            $transitionDate,
            $ip,
            $before,
            $captureMode,
            $captureNote
        ): array {
            $locked = LevelIncomeRuleVersion::query()->whereKey($version->id)->lockForUpdate()->firstOrFail();
            if ($locked->posting_authorized) {
                throw ValidationException::withMessages([
                    'grandfather' => 'Cannot recapture baseline while posting is authorized.',
                ]);
            }

            LevelIncomeUnlockGrandfatherSnapshot::query()
                ->where('level_income_rule_version_id', $locked->id)
                ->delete();

            $now = now();
            $captured = 0;
            $withUnlock = 0;
            $buffer = [];
            $asOf = $captureMode === self::CAPTURE_MODE_AS_OF_TRANSITION ? $transitionDate : null;

            Member::query()->orderBy('id')->chunkById(200, function ($members) use (
                $locked,
                $admin,
                $transitionDate,
                $now,
                $asOf,
                &$captured,
                &$withUnlock,
                &$buffer
            ): void {
                foreach ($members as $member) {
                    $memberId = (string) $member->member_id;

                    if ($asOf !== null) {
                        // Member must exist as of transition date.
                        if ($member->created_at !== null
                            && CarbonImmutable::parse($member->created_at)->toDateString() > $asOf) {
                            continue;
                        }
                        $incomeEligible = $this->eligibility()->qualifyingDirectCountAsOf($memberId, $asOf);
                        $registered = $this->eligibility()->directCountAsOf($memberId, $asOf);
                        $activated = $this->eligibility()->isPersonallyActivatedAsOf($memberId, $asOf);
                    } else {
                        $incomeEligible = $this->eligibility()->qualifyingDirectCount($memberId);
                        $registered = $this->eligibility()->directCount($memberId);
                        $activated = $this->resolver->isPersonallyActivated($memberId);
                    }

                    $legacyUnlock = $this->resolver->legacyLevelsForCount($incomeEligible);
                    if ($legacyUnlock > 0) {
                        $withUnlock++;
                    }
                    $buffer[] = [
                        'level_income_rule_version_id' => $locked->id,
                        'member_id' => $memberId,
                        'legacy_unlocked_levels' => $legacyUnlock,
                        'income_eligible_direct_count' => $incomeEligible,
                        'registered_direct_count' => $registered,
                        'personally_activated_legacy' => $activated,
                        'transition_date' => $transitionDate,
                        'captured_at' => $now,
                        'captured_by' => $admin->id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                    $captured++;
                    if (count($buffer) >= 100) {
                        LevelIncomeUnlockGrandfatherSnapshot::query()->insert($buffer);
                        $buffer = [];
                    }
                }
            }, 'id');

            if ($buffer !== []) {
                LevelIncomeUnlockGrandfatherSnapshot::query()->insert($buffer);
            }

            $locked->forceFill([
                'grandfather_transition_date' => $transitionDate,
                'grandfather_captured_at' => $now,
                'grandfather_captured_by' => $admin->id,
                'grandfather_capture_mode' => $captureMode,
                'grandfather_capture_note' => $captureNote,
            ])->save();

            $fresh = $locked->fresh();
            LevelIncomeRuleAudit::query()->create([
                'level_income_rule_version_id' => $fresh->id,
                'action' => 'grandfather_baseline_captured',
                'actor_id' => $admin->id,
                'before_json' => $before,
                'after_json' => [
                    'transition_date' => $transitionDate,
                    'captured' => $captured,
                    'with_unlock' => $withUnlock,
                    'capture_mode' => $captureMode,
                    'capture_note' => $captureNote,
                    'grandfather_captured_at' => optional($fresh->grandfather_captured_at)?->toIso8601String(),
                    'replaced_prior_unauthorized_baseline' => ($before['snapshot_count'] ?? 0) > 0,
                ],
                'ip_address' => $ip,
            ]);

            $this->resolver->clearCache();

            return [
                'captured' => $captured,
                'with_unlock' => $withUnlock,
                'transition_date' => $transitionDate,
                'version_code' => $fresh->version_code,
                'capture_mode' => $captureMode,
                'capture_note' => $captureNote,
            ];
        });
    }

    /**
     * Compare captured baseline to current (or as-of) eligibility for cutover safety.
     *
     * @return array<string, mixed>
     */
    public function assessBaselineFreshness(LevelIncomeRuleVersion $version): array
    {
        $enabled = $this->isEnabled($version);
        $captured = $this->hasCapture($version);
        $transition = optional($version->grandfather_transition_date)?->toDateString();
        $mode = (string) ($version->grandfather_capture_mode ?? '');
        $today = CarbonImmutable::now()->toDateString();

        $reasons = [];
        $newMembers = 0;
        $changedLegacy = 0;
        $requiresRecapture = false;
        $requiresPreCutoverAck = false;

        if (! $enabled || ! $captured || $transition === null) {
            return [
                'enabled' => $enabled,
                'captured' => $captured,
                'is_fresh' => false,
                'requires_recapture' => $enabled && ! $captured,
                'requires_pre_cutover_ack' => false,
                'new_members_since_capture' => 0,
                'changed_legacy_count' => 0,
                'capture_mode' => $mode !== '' ? $mode : null,
                'transition_date' => $transition,
                'reasons' => $enabled && ! $captured
                    ? ['Grandfathering enabled but baseline not captured.']
                    : [],
                'post_authorize_cutover_policy' => $this->postAuthorizeCutoverPolicy(),
            ];
        }

        if ($mode === self::CAPTURE_MODE_PRE_CUTOVER_CURRENT && $today >= $transition) {
            $requiresRecapture = true;
            $reasons[] = 'Pre-cutover current baseline is stale: transition date has arrived. Recapture as-of the transition date before authorizing.';
        }

        $capturedAt = $version->grandfather_captured_at;
        $snapMemberIds = LevelIncomeUnlockGrandfatherSnapshot::query()
            ->where('level_income_rule_version_id', $version->id)
            ->pluck('legacy_unlocked_levels', 'member_id');

        $allMembers = Member::query()->orderBy('id')->pluck('created_at', 'member_id');
        foreach ($allMembers as $memberId => $createdAt) {
            $memberId = (string) $memberId;
            if (! $snapMemberIds->has($memberId)) {
                // New after capture, or excluded from as-of capture because created after transition.
                if ($capturedAt !== null && $createdAt !== null
                    && CarbonImmutable::parse($createdAt)->greaterThan($capturedAt)) {
                    $newMembers++;
                } elseif ($mode === self::CAPTURE_MODE_AS_OF_TRANSITION
                    && $createdAt !== null
                    && CarbonImmutable::parse($createdAt)->toDateString() <= $transition) {
                    // Should have been included in as-of capture — data drift.
                    $newMembers++;
                    $requiresRecapture = true;
                    $reasons[] = "Member {$memberId} existed on/before transition but has no snapshot.";
                }

                continue;
            }

            $snapshotLevels = (int) $snapMemberIds->get($memberId);
            $recomputed = $mode === self::CAPTURE_MODE_AS_OF_TRANSITION
                ? $this->resolver->legacyLevelsForCount(
                    $this->eligibility()->qualifyingDirectCountAsOf($memberId, $transition)
                )
                : $this->currentLegacyUnlock($memberId);

            if ($recomputed !== $snapshotLevels) {
                $changedLegacy++;
            }
        }

        if ($newMembers > 0 && $mode === self::CAPTURE_MODE_PRE_CUTOVER_CURRENT && $today < $transition) {
            $requiresPreCutoverAck = true;
            $reasons[] = sprintf(
                '%d member(s) registered after capture. They will use proposed rules only until a refreshed baseline includes them.',
                $newMembers
            );
        } elseif ($newMembers > 0 && $mode === self::CAPTURE_MODE_AS_OF_TRANSITION) {
            // New members after as-of transition date are expected (proposed-only). Only flag if many registered after capture but still before authorize.
            $postCaptureNew = Member::query()
                ->where('created_at', '>', $capturedAt)
                ->count();
            if ($postCaptureNew > 0 && $today <= $transition) {
                $requiresPreCutoverAck = true;
                $reasons[] = sprintf(
                    '%d member(s) registered after as-of capture. They use proposed rules only (no snapshot). Confirm before authorize or recapture.',
                    $postCaptureNew
                );
            }
        }

        if ($changedLegacy > 0) {
            if ($mode === self::CAPTURE_MODE_AS_OF_TRANSITION) {
                $requiresRecapture = true;
                $reasons[] = sprintf(
                    '%d snapshotted member(s) no longer match as-of transition legacy unlock. Recapture before authorize.',
                    $changedLegacy
                );
            } else {
                $requiresPreCutoverAck = true;
                $reasons[] = sprintf(
                    '%d snapshotted member(s) have current legacy unlock different from the frozen pre-cutover snapshot. Floor uses min(snapshot, current_legacy); recapture recommended.',
                    $changedLegacy
                );
            }
        }

        $isFresh = ! $requiresRecapture && $reasons === [];

        return [
            'enabled' => true,
            'captured' => true,
            'is_fresh' => $isFresh,
            'requires_recapture' => $requiresRecapture,
            'requires_pre_cutover_ack' => $requiresPreCutoverAck && ! $requiresRecapture,
            'new_members_since_capture' => $newMembers,
            'changed_legacy_count' => $changedLegacy,
            'capture_mode' => $mode !== '' ? $mode : null,
            'transition_date' => $transition,
            'reasons' => array_values(array_unique($reasons)),
            'post_authorize_cutover_policy' => $this->postAuthorizeCutoverPolicy(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function postAuthorizeCutoverPolicy(): array
    {
        return [
            'new_members_after_capture' => 'Use proposed Option B unlock only (no grandfather floor).',
            'legacy_eligibility_lost' => 'Protection shrinks via min(snapshot, current_legacy); never preserves unlock after legacy eligibility is lost.',
            'legacy_eligibility_gained' => 'Snapshot floor does not increase; proposed rules may grant higher unlock independently.',
            'between_authorize_and_effective' => 'Accrual stays on legacy for business dates before effective_from. No historical backfill. Do not recapture after authorization.',
            'after_effective' => 'existing_prospective uses authorized rules for business dates on/after effective_from with grandfather floor where snapshots exist.',
        ];
    }

    /**
     * Last successfully processed financial business date, if any.
     */
    public function lastSucceededBusinessDate(): ?string
    {
        if (! Schema::hasTable('package_income_runs')) {
            return null;
        }

        $date = PackageIncomeRun::query()
            ->where('status', PackageIncomeRun::SUCCEEDED)
            ->orderByDesc('business_date')
            ->value('business_date');

        return $date === null ? null : CarbonImmutable::parse($date)->toDateString();
    }

    /**
     * @throws ValidationException
     */
    public function assertReadyForAuthorization(
        LevelIncomeRuleVersion $version,
        bool $confirmPreCutoverBaseline = false
    ): void {
        if (! $this->isEnabled($version)) {
            $this->assertEffectiveDateNotRetroactive($version);

            return;
        }
        if (! $this->hasCapture($version)) {
            throw ValidationException::withMessages([
                'grandfather' => 'Grandfathering is enabled but no baseline has been captured. Capture legacy unlock at the transition date before authorizing posting.',
            ]);
        }
        if ($version->grandfather_transition_date === null) {
            throw ValidationException::withMessages([
                'grandfather_transition_date' => 'Set and capture a grandfather transition date before authorizing.',
            ]);
        }

        $transition = $version->grandfather_transition_date->toDateString();
        $effective = optional($version->effective_from)?->toDateString();

        if ($version->applicability === LevelIncomeRuleVersion::APPLICABILITY_EXISTING_PROSPECTIVE) {
            if ($effective === null) {
                throw ValidationException::withMessages([
                    'effective_from' => 'Set an effective date before authorizing existing_prospective.',
                ]);
            }
            if ($transition !== $effective) {
                throw ValidationException::withMessages([
                    'grandfather_transition_date' => sprintf(
                        'For existing_prospective, grandfather transition date (%s) must match effective_from (%s). Recapture with the effective date or republish with a matching effective date.',
                        $transition,
                        $effective
                    ),
                ]);
            }
        }

        $freshness = $this->assessBaselineFreshness($version);
        if ($freshness['requires_recapture'] ?? false) {
            throw ValidationException::withMessages([
                'grandfather' => 'Grandfather baseline requires a refreshed capture before authorization: '
                    .implode(' ', $freshness['reasons'] ?? ['Baseline is stale.']),
            ]);
        }
        if (($freshness['requires_pre_cutover_ack'] ?? false) && ! $confirmPreCutoverBaseline) {
            throw ValidationException::withMessages([
                'confirm_pre_cutover_baseline' => 'Baseline has post-capture membership or legacy eligibility drift. Recapture for a clean cutover, or explicitly confirm pre-cutover baseline acknowledgment. '
                    .implode(' ', $freshness['reasons'] ?? []),
            ]);
        }

        $this->assertEffectiveDateNotRetroactive($version);
    }

    /**
     * @throws ValidationException
     */
    public function assertEffectiveDateNotRetroactive(LevelIncomeRuleVersion $version): void
    {
        $effective = optional($version->effective_from)?->toDateString();
        if ($effective === null) {
            return;
        }

        $lastSucceeded = $this->lastSucceededBusinessDate();
        if ($lastSucceeded !== null && $effective <= $lastSucceeded) {
            throw ValidationException::withMessages([
                'effective_from' => sprintf(
                    'Effective date %s must be after the last succeeded financial business date (%s). Authorizing on or before a processed date would risk retroactive rule application. This system does not backfill history — choose a future unprocessed business date.',
                    $effective,
                    $lastSucceeded
                ),
            ]);
        }
    }

    public function currentLegacyUnlock(string $memberId): int
    {
        return $this->resolver->legacyLevelsForCount(
            $this->eligibility()->qualifyingDirectCount($memberId)
        );
    }

    public function snapshotLevels(LevelIncomeRuleVersion $version, string $memberId): ?int
    {
        if (! Schema::hasTable('level_income_unlock_grandfather_snapshots')) {
            return null;
        }

        $row = LevelIncomeUnlockGrandfatherSnapshot::query()
            ->where('level_income_rule_version_id', $version->id)
            ->where('member_id', $memberId)
            ->first();

        return $row === null ? null : (int) $row->legacy_unlocked_levels;
    }

    /**
     * @return array<string, mixed>
     */
    public function versionSummary(?LevelIncomeRuleVersion $version): array
    {
        if ($version === null) {
            return [
                'enabled' => false,
                'captured' => false,
                'snapshot_count' => 0,
                'with_unlock_count' => 0,
            ];
        }

        $count = 0;
        $withUnlock = 0;
        if (Schema::hasTable('level_income_unlock_grandfather_snapshots')) {
            $count = LevelIncomeUnlockGrandfatherSnapshot::query()
                ->where('level_income_rule_version_id', $version->id)
                ->count();
            $withUnlock = LevelIncomeUnlockGrandfatherSnapshot::query()
                ->where('level_income_rule_version_id', $version->id)
                ->where('legacy_unlocked_levels', '>', 0)
                ->count();
        }

        $freshness = $this->assessBaselineFreshness($version);

        return [
            'enabled' => $this->isEnabled($version),
            'captured' => $this->hasCapture($version),
            'transition_date' => optional($version->grandfather_transition_date)?->toDateString(),
            'captured_at' => optional($version->grandfather_captured_at)?->toIso8601String(),
            'capture_mode' => $version->grandfather_capture_mode ?? null,
            'capture_note' => $version->grandfather_capture_note ?? null,
            'snapshot_count' => $count,
            'with_unlock_count' => $withUnlock,
            'freshness' => $freshness,
            'formula' => 'protected = max(proposed, min(snapshot_legacy, current_legacy))',
            'note' => 'Protection does not bypass payment controls, ROI/package terms, or Tour Only income exclusion. No historical accrual backfill. Provisional preview protection applies only before capture.',
        ];
    }
}
