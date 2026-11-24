<?php

namespace App\Console\Commands;

use App\Models\LevelIncomeRuleVersion;
use App\Models\Member;
use App\Models\Package;
use App\Models\PackagePurchase;
use App\Services\LevelIncomeDirectQualification;
use App\Services\PackageBusinessVolumeEligibility;
use App\Services\ReferralLevelEligibility;
use App\Services\Rewards\TeamBusinessCalculationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Read-only diagnostic for level unlock + Tour Only business volume.
 * Does not mutate wallets, commissions, or purchases.
 */
class DiagnoseLevelUnlockAndBusinessVolume extends Command
{
    protected $signature = 'deltasupport:diagnose-level-unlock-business
                            {--member= : Optional member_id focus}
                            {--limit=50 : Max member rows in detail tables}
                            {--json : Emit JSON}';

    protected $description = 'Read-only report: level unlock gaps, qualification previews, and Tour Only business volume (no financial writes).';

    public function handle(
        ReferralLevelEligibility $eligibility,
        LevelIncomeDirectQualification $qualification,
        TeamBusinessCalculationService $business,
        PackageBusinessVolumeEligibility $volume,
    ): int {
        $memberFocus = trim((string) $this->option('member'));
        $limit = max(1, min(500, (int) $this->option('limit')));

        $totalMembers = Member::query()->count();
        $withApproved = Member::query()
            ->whereExists(function ($q): void {
                $q->selectRaw('1')->from('package_purchases')
                    ->whereColumn('package_purchases.member_id', 'members.member_id')
                    ->where('status', PackagePurchase::APPROVED)
                    ->whereNotNull('reviewed_at');
            })
            ->count();

        $tourOnlyQuery = $volume->applyPurchaseConstraints(DB::table('package_purchases'))
            ->where('package_type', Package::TYPE_TOUR);
        $tourOnlyCount = (clone $tourOnlyQuery)->count();
        $tourOnlyAmount = bcadd((string) ((clone $tourOnlyQuery)->sum('price_usd') ?? '0'), '0', 4);

        $proposed = LevelIncomeRuleVersion::query()
            ->with('bands')
            ->where('version_code', 'proposed_activation_unlock_v1')
            ->first();

        $activatedNoUnlock = [];
        $currentVsProposed = [];
        $sampleIds = Member::query()
            ->orderBy('id')
            ->limit($limit)
            ->pluck('member_id')
            ->all();
        if ($memberFocus !== '') {
            $sampleIds = array_values(array_unique(array_merge([$memberFocus], $sampleIds)));
        }

        $summaries = $eligibility->summariesForMemberIds($sampleIds);
        $biz = $business->businessForMembers($sampleIds);

        foreach ($summaries as $memberId => $row) {
            $preview = $qualification->memberPreview($memberId, $proposed);
            $current = (int) $row['unlocked_levels'];
            $expected = (int) ($preview['candidate_expected']['expected_unlocked_levels'] ?? $preview['currently_unlocked_levels']);
            if ($current !== $expected) {
                $currentVsProposed[] = [
                    'member_id' => $memberId,
                    'registered_directs' => $preview['registered_direct_count'],
                    'approved_package_directs' => $preview['approved_package_direct_count'],
                    'approved_purchase_count' => $preview['approved_direct_package_purchase_count'],
                    'tour_only_directs' => $preview['tour_only_purchasing_direct_count'],
                    'current_unlocked' => $current,
                    'proposed_unlocked' => $expected,
                    'config_version' => $preview['candidate_expected']['version_code']
                        ?? $preview['applicable_version'],
                    'locked_reason' => $preview['locked_reason'],
                ];
            }
            if ($row['personally_activated'] && $current === 0) {
                $activatedNoUnlock[] = [
                    'member_id' => $memberId,
                    'registered_directs' => $row['registered_direct_count'],
                    'qualifying_directs' => $row['qualifying_direct_count'],
                    'unlocked_levels' => $row['unlocked_levels'],
                    'legacy_unlocked_levels' => $row['legacy_unlocked_levels'],
                    'proposed_unlocked' => $expected,
                    'rule_mode' => $row['rule_mode'],
                    'reason' => ((int) $row['qualifying_direct_count'] === 0)
                        ? 'No income-qualifying directs under live legacy rules. Proposed bands may unlock if activated + authorized.'
                        : 'Unexpected: has qualifying directs but 0 unlock — inspect authorized bands / activation flags.',
                ];
            }
        }

        $report = [
            'generated_at' => now()->toIso8601String(),
            'read_only' => true,
            'totals' => [
                'registered_members' => $totalMembers,
                'members_with_approved_packages' => $withApproved,
                'approved_tour_only_purchase_count' => $tourOnlyCount,
                'approved_tour_only_business_usdt' => $tourOnlyAmount,
                'sample_size' => count($sampleIds),
                'sample_current_vs_proposed_delta' => count($currentVsProposed),
            ],
            'proposed_version' => $proposed === null ? null : [
                'version_code' => $proposed->version_code,
                'status' => $proposed->status,
                'posting_authorized' => (bool) $proposed->posting_authorized,
                'direct_qualification_basis' => $proposed->direct_qualification_basis,
                'tour_only_qualifies_for_unlock' => (bool) $proposed->tour_only_qualifies_for_unlock,
            ],
            'policy_notes' => [
                'level_unlock_live' => 'Income-eligible unique directs; Tour Only excluded until posting-authorized version says otherwise.',
                'qualification_bases' => LevelIncomeDirectQualification::labels(),
                'registration_alone' => 'Does NOT unlock levels under live legacy rules.',
                'tour_only_bv' => 'Counts toward Total/Team Business (BV) but does NOT generate ROI/Level Income.',
                'tour_only_unlock' => 'Configurable per version via tour_only_qualifies_for_unlock; independent of ROI eligibility.',
                'proposed_bands' => 'Activated + 0 directs→1-4 … 4+→1-20 — requires Admin publish + posting authorization; not auto-enabled.',
                'no_backfill' => 'Diagnostics never authorize, backfill, or post Associate Commission.',
            ],
            'activated_with_zero_unlock_sample' => $activatedNoUnlock,
            'current_vs_proposed_sample' => array_slice($currentVsProposed, 0, $limit),
            'member_focus' => $memberFocus !== '' && isset($summaries[$memberFocus])
                ? [
                    'eligibility' => $eligibility->forMember($memberFocus),
                    'qualification_preview' => $qualification->memberPreview($memberFocus, $proposed),
                    'business' => $biz[$memberFocus] ?? null,
                ]
                : null,
        ];

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('Delta Support — Level Unlock & Business Volume Diagnostic (read-only)');
        $this->table(['Metric', 'Value'], [
            ['Registered members', (string) $totalMembers],
            ['Members with approved packages', (string) $withApproved],
            ['Approved Tour Only purchases', (string) $tourOnlyCount],
            ['Approved Tour Only BV (USDT)', $tourOnlyAmount],
            ['Sample activated with 0 unlock', (string) count($activatedNoUnlock)],
            ['Sample current≠proposed unlock', (string) count($currentVsProposed)],
            ['Proposed version', $proposed?->version_code ?? 'missing'],
            ['Proposed basis', (string) ($proposed?->direct_qualification_basis ?? 'n/a')],
            ['Proposed Tour Only unlock', ($proposed?->tour_only_qualifies_for_unlock ?? false) ? 'yes' : 'no'],
            ['Proposed posting_authorized', ($proposed?->posting_authorized ?? false) ? 'YES' : 'no'],
        ]);

        if ($currentVsProposed !== []) {
            $this->warn('Current vs proposed unlock (sample deltas — not applied):');
            $this->table(
                ['Member', 'Reg', 'Appr dirs', 'Purchases', 'Tour dirs', 'Current', 'Proposed', 'Reason'],
                array_map(fn (array $r): array => [
                    $r['member_id'],
                    (string) $r['registered_directs'],
                    (string) $r['approved_package_directs'],
                    (string) $r['approved_purchase_count'],
                    (string) $r['tour_only_directs'],
                    (string) $r['current_unlocked'],
                    (string) $r['proposed_unlocked'],
                    $r['locked_reason'],
                ], array_slice($currentVsProposed, 0, 20))
            );
        }

        if ($activatedNoUnlock !== []) {
            $this->warn('Activated members with 0 unlocked levels (sample):');
            $this->table(
                ['Member', 'Registered', 'Qualifying', 'Unlocked', 'Proposed', 'Reason'],
                array_map(fn (array $r): array => [
                    $r['member_id'],
                    (string) $r['registered_directs'],
                    (string) $r['qualifying_directs'],
                    (string) $r['unlocked_levels'],
                    (string) $r['proposed_unlocked'],
                    $r['reason'],
                ], array_slice($activatedNoUnlock, 0, 20))
            );
        }

        if ($report['member_focus'] !== null) {
            $this->info('Focus member '.$memberFocus.':');
            $this->line(json_encode($report['member_focus'], JSON_PRETTY_PRINT));
        }

        $this->comment('No wallets, commissions, or purchases were modified. Do not authorize from this command.');

        return self::SUCCESS;
    }
}
