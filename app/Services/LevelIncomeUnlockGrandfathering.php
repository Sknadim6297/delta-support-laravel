<?php

namespace App\Services;

use App\Models\LevelIncomeRuleAudit;
use App\Models\LevelIncomeRuleVersion;
use App\Models\LevelIncomeUnlockGrandfatherSnapshot;
use App\Models\Member;
use App\Models\User;
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
 * - Does not bypass payment controls, ROI terms, package expiry, or income generation filters.
 */
class LevelIncomeUnlockGrandfathering
{
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
     * Preview protection using a live provisional snapshot when capture has not run yet.
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
        $snapshot = $this->snapshotLevels($version, $memberId);
        $usingProvisional = false;

        if ($enabled && $snapshot === null) {
            // Provisional: treat today's legacy unlock as what would be captured.
            $snapshot = $currentLegacy;
            $usingProvisional = ! $this->hasCapture($version);
        }

        $floor = $enabled && $snapshot !== null ? min($snapshot, $currentLegacy) : 0;
        $protected = $enabled
            ? max($proposedUnlock, $floor)
            : $proposedUnlock;

        $reason = match (true) {
            ! $enabled => 'Grandfathering disabled on this version — proposed unlock only.',
            $usingProvisional && $currentLegacy > $proposedUnlock => 'Provisional protection: capture would floor at min(live legacy, snapshot)=live legacy until directs change.',
            $enabled && ! $usingProvisional && $snapshot !== null && $protected > $proposedUnlock => sprintf(
                'Protected floor %d from snapshot %d capped by current legacy %d.',
                $floor,
                $snapshot,
                $currentLegacy
            ),
            $enabled && $proposedUnlock > $currentLegacy => 'New rules grant more than legacy — member gains levels.',
            default => 'Protected unlock equals proposed (no reduction to prevent).',
        };

        return [
            'current_legacy' => $currentLegacy,
            'proposed' => $proposedUnlock,
            'snapshot_levels' => $snapshot,
            'protected' => max(0, min($protected, ReferralLevelEligibility::MAX_LEVELS)),
            'floor' => $floor,
            'grandfather_enabled' => $enabled,
            'using_provisional_snapshot' => $usingProvisional,
            'reason' => $reason,
        ];
    }

    /**
     * Capture legacy unlock for all registered members at the transition date.
     * Idempotent replace for the version. Does not authorize posting.
     *
     * @return array{captured: int, with_unlock: int, transition_date: string, version_code: string}
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

        $transitionDate = \Carbon\CarbonImmutable::parse($transitionDate)->toDateString();
        $before = [
            'grandfather_captured_at' => optional($version->grandfather_captured_at)?->toIso8601String(),
            'snapshot_count' => LevelIncomeUnlockGrandfatherSnapshot::query()
                ->where('level_income_rule_version_id', $version->id)
                ->count(),
        ];

        return DB::transaction(function () use ($version, $admin, $transitionDate, $ip, $before): array {
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

            Member::query()->orderBy('id')->chunkById(200, function ($members) use (
                $locked,
                $admin,
                $transitionDate,
                $now,
                &$captured,
                &$withUnlock,
                &$buffer
            ): void {
                foreach ($members as $member) {
                    $memberId = (string) $member->member_id;
                    $incomeEligible = $this->eligibility()->qualifyingDirectCount($memberId);
                    $legacyUnlock = $this->resolver->legacyLevelsForCount($incomeEligible);
                    $registered = $this->eligibility()->directCount($memberId);
                    $activated = $this->resolver->isPersonallyActivated($memberId);
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
                    'grandfather_captured_at' => optional($fresh->grandfather_captured_at)?->toIso8601String(),
                ],
                'ip_address' => $ip,
            ]);

            $this->resolver->clearCache();

            return [
                'captured' => $captured,
                'with_unlock' => $withUnlock,
                'transition_date' => $transitionDate,
                'version_code' => $fresh->version_code,
            ];
        });
    }

    /**
     * @throws ValidationException
     */
    public function assertReadyForAuthorization(LevelIncomeRuleVersion $version): void
    {
        if (! $this->isEnabled($version)) {
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

        return [
            'enabled' => $this->isEnabled($version),
            'captured' => $this->hasCapture($version),
            'transition_date' => optional($version->grandfather_transition_date)?->toDateString(),
            'captured_at' => optional($version->grandfather_captured_at)?->toIso8601String(),
            'snapshot_count' => $count,
            'with_unlock_count' => $withUnlock,
            'formula' => 'protected = max(proposed, min(snapshot_legacy, current_legacy))',
            'note' => 'Protection does not bypass payment controls, ROI/package terms, or Tour Only income exclusion. No historical accrual backfill.',
        ];
    }
}
