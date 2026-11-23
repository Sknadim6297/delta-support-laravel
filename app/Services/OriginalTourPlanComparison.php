<?php

namespace App\Services;

/**
 * Read-only comparison of the proposed original Tour & Travels plan
 * against the active package_income ROI configuration.
 * Never writes wallets, accruals, or income terms.
 */
class OriginalTourPlanComparison
{
    public const VERSION = 'original_tour_plan_v1';

    /** @var list<string> */
    public const PRODUCTION_PACKAGE_CODES = [
        'DST-TOUR-001',
        'DST-ROI-002',
        'DST-TOUR-003',
        'DST-TOUR-004',
        'DST-TOUR-005',
        'DST-RY-006',
    ];

    public function __construct(private readonly PackageIncomePolicy $policy)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function preview(): array
    {
        $config = config('deltasupport.original_tour_plan', []);
        $conversion = is_array($config['conversion'] ?? null) ? $config['conversion'] : [];
        $inrPerUsdt = bcadd((string) ($conversion['inr_per_usdt'] ?? '104'), '0', 0);
        $packages = [];

        foreach ((array) ($config['packages'] ?? []) as $tier => $row) {
            if (! is_array($row)) {
                continue;
            }
            $packages[] = $this->packageRow((string) $tier, $row, $inrPerUsdt);
        }

        $candidates = [];
        foreach ((array) ($config['candidate_package_income'] ?? []) as $code => $row) {
            if (! is_array($row)) {
                continue;
            }
            $candidates[(string) $code] = [
                'roi_daily' => isset($row['roi_daily']) ? bcadd((string) $row['roi_daily'], '0', 4) : null,
                'roi_days' => isset($row['roi_days']) ? (int) $row['roi_days'] : null,
                'commission_pool' => array_key_exists('commission_pool', $row) && $row['commission_pool'] !== null
                    ? bcadd((string) $row['commission_pool'], '0', 4)
                    : null,
                'commission_days' => array_key_exists('commission_days', $row) && $row['commission_days'] !== null
                    ? (int) $row['commission_days']
                    : null,
            ];
        }

        return [
            'version_code' => (string) ($config['version_code'] ?? self::VERSION),
            'status' => (string) ($config['status'] ?? 'proposed'),
            'active' => (bool) ($config['active'] ?? false),
            'applies_to_new_purchases' => (bool) ($config['applies_to_new_purchases'] ?? false),
            'label' => 'PROPOSED — not active / not payable',
            'mutates' => false,
            'production_package_codes' => self::PRODUCTION_PACKAGE_CODES,
            'candidate_package_income' => $candidates,
            'candidate_merged_into_package_income' => false,
            'conversion' => [
                'basis_label' => (string) ($conversion['basis_label'] ?? 'Proposed fixed business ratio'),
                'inr_per_usdt' => $inrPerUsdt,
                'rounding_policy' => (string) ($conversion['rounding_policy'] ?? 'unapproved'),
                'notes' => (string) ($conversion['notes'] ?? ''),
            ],
            'packages' => $packages,
            'associate_reminder' => [
                'version' => 'associate_v1',
                'posting_enabled' => (bool) config('deltasupport.associate_commission.posting_enabled'),
                'snapshot_on_approval' => (bool) config('deltasupport.associate_commission.snapshot_on_approval'),
                'diamond' => 'Not configured — keep four-package associate_v1 unchanged',
                'pools' => [
                    'SILVER' => '0.1200',
                    'GOLD' => '0.2400',
                    'PREMIUM' => '1.2000',
                    'VIP' => '2.4000',
                ],
                'commission_days' => 400,
            ],
            'approval_questions' => [
                'Approve INR 104 = 1 USDT as the fixed conversion basis?',
                'Confirm four-decimal round-half-up candidate daily rates (0.1058 / 0.2404 / 1.3654 / 3.2019 / 16.8654) and any final-day adjustment.',
                'Confirm treatment of already-approved purchases: keep legacy snapshots forever; apply original_tour_plan_v1 to future approvals only.',
                'Confirm PREMIUM DST-TOUR-003 moves from legacy 2.5000×1000 to candidate 1.3654×700 for new purchases only.',
                'Confirm GOLD DST-ROI-002 moves from legacy 0.2500×800 to candidate 0.2404×800 for new purchases only.',
                'Confirm SILVER / VIP / DIAMOND receive package_income entries and roi_eligible=true only after written activation (catalog Active ≠ income configured).',
                'Confirm DELTA SHARE DST-RY-006 stays no-ROI / no level income; maturity/withdrawal wording remains unapproved.',
                'Confirm DIAMOND DST-TOUR-005 remains outside associate_v1 (do not invent DIAMOND associate rates).',
                'Do not merge candidate_package_income into package_income.packages until written activation approval.',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function packageRow(string $tier, array $row, string $inrPerUsdt): array
    {
        $code = (string) ($row['proposed_package_code'] ?? '');
        $existing = $code !== '' ? $this->policy->definition($code) : null;
        $hasRoi = (bool) ($row['has_daily_roi'] ?? false);
        $inrDaily = $row['original_inr_daily'] ?? null;
        $roiDays = $row['roi_days'] ?? null;
        $tour = is_array($row['tour'] ?? null) ? $row['tour'] : [];
        $proposedDaily = $row['proposed_usdt_daily'] ?? null;

        $conversion = null;
        if ($hasRoi && $inrDaily !== null && $roiDays !== null) {
            $conversion = $this->conversionMath((string) $inrDaily, (int) $roiDays, $inrPerUsdt, $proposedDaily);
        }

        return [
            'tier' => $tier,
            'proposed_package_code' => $code,
            'existing_usdt_price' => bcadd((string) ($row['existing_usdt_price'] ?? '0'), '0', 4),
            'original_inr_price' => $row['original_inr_price'] ?? null,
            'original_inr_daily' => $inrDaily,
            'proposed_usdt_daily' => $proposedDaily === null ? null : bcadd((string) $proposedDaily, '0', 4),
            'proposed_roi_days' => $roiDays,
            'has_daily_roi' => $hasRoi,
            'has_monthly_roi' => (bool) ($row['has_monthly_roi'] ?? false),
            'status_label' => 'PROPOSED',
            'tour' => [
                'eligible_after_days' => $tour['eligible_after_days'] ?? null,
                'nights' => $tour['nights'] ?? null,
                'days' => $tour['days'] ?? null,
                'adults' => $tour['adults'] ?? null,
                'children' => $tour['children'] ?? null,
                'food_coupon_inr' => $tour['food_coupon_inr'] ?? null,
                'summary' => $this->tourSummary($tour),
            ],
            'existing_roi' => $existing === null ? null : [
                'roi_daily' => $existing['roi_daily'],
                'roi_days' => $existing['roi_days'],
                'commission_pool' => $existing['commission_pool'],
                'commission_days' => $existing['commission_days'],
                'duration_total' => bcmul($existing['roi_daily'], (string) $existing['roi_days'], 4),
            ],
            'proposed_conversion' => $conversion,
            'differences' => $this->differences($existing, $conversion, $roiDays, $hasRoi, $proposedDaily),
            'notes' => $row['notes'] ?? null,
            'chart_notes' => $row['chart_notes'] ?? [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function conversionMath(string $inrDaily, int $roiDays, string $inrPerUsdt, mixed $proposedDaily): array
    {
        $exactDaily = bcdiv($inrDaily, $inrPerUsdt, 12);
        $fourFloor = bcdiv($inrDaily, $inrPerUsdt, 4);
        $fourRound = $this->roundHalfUp4($exactDaily);
        $candidateDaily = $proposedDaily === null || $proposedDaily === ''
            ? $fourRound
            : bcadd((string) $proposedDaily, '0', 4);
        $advertisedInrTotal = bcmul($inrDaily, (string) $roiDays, 0);
        $advertisedUsdtTotalExact = bcdiv($advertisedInrTotal, $inrPerUsdt, 12);
        $mathFloorTotal = bcmul($fourFloor, (string) $roiDays, 4);
        $mathRoundTotal = bcmul($fourRound, (string) $roiDays, 4);
        $mathCandidateTotal = bcmul($candidateDaily, (string) $roiDays, 4);
        $exactDurationTotal = bcmul($exactDaily, (string) $roiDays, 12);

        return [
            'exact_usdt_daily' => rtrim(rtrim($exactDaily, '0'), '.') === '' ? '0' : rtrim(rtrim($exactDaily, '0'), '.'),
            'exact_usdt_daily_12dp' => $exactDaily,
            'proposed_usdt_daily_4dp_floor' => $fourFloor,
            'proposed_usdt_daily_4dp_round' => $fourRound,
            'proposed_usdt_daily_candidate' => $candidateDaily,
            'floor_vs_round_diff' => bcsub($fourRound, $fourFloor, 4),
            'advertised_inr_total' => $advertisedInrTotal,
            'advertised_usdt_total_exact' => $advertisedUsdtTotalExact,
            'math_daily_x_days_floor' => $mathFloorTotal,
            'math_daily_x_days_round' => $mathRoundTotal,
            'math_daily_x_days_candidate' => $mathCandidateTotal,
            'exact_daily_x_days' => $exactDurationTotal,
            'final_day_adjustment_floor' => bcsub($advertisedUsdtTotalExact, $mathFloorTotal, 12),
            'final_day_adjustment_round' => bcsub($advertisedUsdtTotalExact, $mathRoundTotal, 12),
            'final_day_adjustment_candidate' => bcsub($advertisedUsdtTotalExact, $mathCandidateTotal, 12),
            'rounding_policy' => 'candidate_only_not_activated',
        ];
    }

    private function roundHalfUp4(string $exact12): string
    {
        // half-up to 4 dp using bcmath only
        $scaled = bcadd(bcmul($exact12, '10000', 8), '0.5', 0);

        return bcdiv($scaled, '10000', 4);
    }

    /**
     * @param  array<string, mixed>  $tour
     */
    private function tourSummary(array $tour): string
    {
        $after = $tour['eligible_after_days'] ?? '?';
        $nights = $tour['nights'] ?? '?';
        $days = $tour['days'] ?? '?';
        $adults = $tour['adults'] ?? '?';
        $children = $tour['children'] ?? '?';
        $coupon = $tour['food_coupon_inr'] ?? null;
        $base = "After {$after} days · {$nights} Night(s) / {$days} Day(s) · {$adults} Adults + {$children} Child";
        if ($coupon !== null && $coupon !== '') {
            $base .= ' · food coupon INR '.$coupon.' (PROPOSED, not issued)';
        }

        return $base;
    }

    /**
     * @param  array{roi_daily: string, roi_days: int, commission_pool: ?string, commission_days: ?int}|null  $existing
     * @param  array<string, mixed>|null  $conversion
     * @return list<string>
     */
    private function differences(?array $existing, ?array $conversion, mixed $proposedDays, bool $hasRoi, mixed $proposedDaily): array
    {
        $diffs = [];
        if (! $hasRoi) {
            $diffs[] = 'PROPOSED: no daily ROI and no monthly ROI.';
            if ($existing !== null) {
                $diffs[] = 'Existing package_income still defines ROI '.$existing['roi_daily'].' × '.$existing['roi_days'].' for this mapped code — historical terms stay unchanged.';
            }

            return $diffs;
        }

        if ($existing === null) {
            $diffs[] = 'No active package_income ROI entry for this proposed code (Admin shows Not configured).';
        } else {
            $compareDaily = $proposedDaily !== null && $proposedDaily !== ''
                ? bcadd((string) $proposedDaily, '0', 4)
                : ($conversion['proposed_usdt_daily_4dp_round'] ?? null);
            if ($compareDaily !== null && bccomp($existing['roi_daily'], $compareDaily, 4) !== 0) {
                $diffs[] = 'Existing daily ROI '.$existing['roi_daily'].' differs from proposed candidate '.$compareDaily.'.';
            }
            if ($proposedDays !== null && (int) $existing['roi_days'] !== (int) $proposedDays) {
                $diffs[] = 'Existing duration '.$existing['roi_days'].' days differs from proposed '.$proposedDays.' days.';
            }
        }
        $diffs[] = 'Rounding / final-day adjustment REQUIRES BUSINESS APPROVAL before any activation.';
        $diffs[] = 'Candidate rates are not merged into package_income.packages.';

        return $diffs;
    }
}
