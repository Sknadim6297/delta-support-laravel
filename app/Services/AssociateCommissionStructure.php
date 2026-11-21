<?php

namespace App\Services;

use App\Models\AssociateCommissionPackage;
use App\Models\AssociateCommissionVersion;
use App\Models\Package;
use App\Models\PackagePurchase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only Associate Commission Structure (20 ranks × 400 days).
 * Does not credit wallets. Posting remains disabled until separate approval.
 */
class AssociateCommissionStructure
{
    public const VERSION = AssociateCommissionVersion::VERSION_V1;

    /**
     * Canonical chart from the approved Associate Commission image.
     * Amounts are USDT/day. ROI is intentionally absent.
     *
     * @var array<string, array{label: string, price: string, mapped_package_code: ?string, ranks: array<int, string>, daily_total: string, duration_total: string}>
     */
    public const TIERS = [
        'silver' => [
            'label' => 'SILVER PACKAGE',
            'price' => '50.0000',
            'mapped_package_code' => 'DST-TOUR-001',
            'daily_total' => '0.1200',
            'duration_total' => '48.0000',
            'ranks' => [
                1 => '0.0300', 2 => '0.0150', 3 => '0.0090', 4 => '0.0060',
                5 => '0.0030', 6 => '0.0030', 7 => '0.0030', 8 => '0.0030',
                9 => '0.0030', 10 => '0.0030', 11 => '0.0030', 12 => '0.0030',
                13 => '0.0030', 14 => '0.0030', 15 => '0.0030', 16 => '0.0030',
                17 => '0.0030', 18 => '0.0060', 19 => '0.0060', 20 => '0.0090',
            ],
        ],
        'gold' => [
            'label' => 'GOLD PACKAGE',
            'price' => '100.0000',
            'mapped_package_code' => 'DST-ROI-002',
            'daily_total' => '0.2400',
            'duration_total' => '96.0000',
            'ranks' => [
                1 => '0.0600', 2 => '0.0300', 3 => '0.0180', 4 => '0.0120',
                5 => '0.0060', 6 => '0.0060', 7 => '0.0060', 8 => '0.0060',
                9 => '0.0060', 10 => '0.0060', 11 => '0.0060', 12 => '0.0060',
                13 => '0.0060', 14 => '0.0060', 15 => '0.0060', 16 => '0.0060',
                17 => '0.0060', 18 => '0.0120', 19 => '0.0120', 20 => '0.0180',
            ],
        ],
        'premium' => [
            'label' => 'PREMIUM PACKAGE',
            'price' => '500.0000',
            'mapped_package_code' => 'DST-TOUR-003',
            'daily_total' => '1.2000',
            'duration_total' => '480.0000',
            'ranks' => [
                1 => '0.3000', 2 => '0.1500', 3 => '0.0900', 4 => '0.0600',
                5 => '0.0300', 6 => '0.0300', 7 => '0.0300', 8 => '0.0300',
                9 => '0.0300', 10 => '0.0300', 11 => '0.0300', 12 => '0.0300',
                13 => '0.0300', 14 => '0.0300', 15 => '0.0300', 16 => '0.0300',
                17 => '0.0300', 18 => '0.0600', 19 => '0.0600', 20 => '0.0900',
            ],
        ],
        'vip' => [
            'label' => 'VIP PACKAGE',
            'price' => '1000.0000',
            'mapped_package_code' => 'DST-TOUR-004',
            'daily_total' => '2.4000',
            'duration_total' => '960.0000',
            'ranks' => [
                1 => '0.6000', 2 => '0.3000', 3 => '0.1800', 4 => '0.1200',
                5 => '0.0600', 6 => '0.0600', 7 => '0.0600', 8 => '0.0600',
                9 => '0.0600', 10 => '0.0600', 11 => '0.0600', 12 => '0.0600',
                13 => '0.0600', 14 => '0.0600', 15 => '0.0600', 16 => '0.0600',
                17 => '0.0600', 18 => '0.1200', 19 => '0.1200', 20 => '0.1800',
            ],
        ],
    ];

    public function __construct(private readonly PackageIncomePolicy $policy)
    {
    }

    public function postingEnabled(): bool
    {
        return config('deltasupport.associate_commission.posting_enabled') === true;
    }

    /**
     * Legacy PackageIncomeAccrual must remain the only posting path while this is false.
     */
    public function accrualUsesAssociatePostingPath(): bool
    {
        return $this->postingEnabled();
    }

    public function snapshotOnApprovalEnabled(): bool
    {
        if (config('deltasupport.associate_commission.snapshot_on_approval') === true) {
            return true;
        }

        $version = $this->storedVersion();

        return $version !== null && $version->snapshot_on_approval;
    }

    public function mappedPackageCode(string $tierCode): ?string
    {
        $configured = config('deltasupport.associate_commission.tier_package_codes.'.$tierCode);
        if (is_string($configured) && trim($configured) !== '') {
            return trim($configured);
        }

        $stored = $this->storedVersion()?->packages->firstWhere('tier_code', $tierCode)?->mapped_package_code;
        if (is_string($stored) && trim($stored) !== '') {
            return trim($stored);
        }

        $fallback = self::TIERS[$tierCode]['mapped_package_code'] ?? null;

        return is_string($fallback) && $fallback !== '' ? $fallback : null;
    }

    public function storedVersion(?string $versionCode = null): ?AssociateCommissionVersion
    {
        if (! Schema::hasTable('associate_commission_versions')) {
            return null;
        }

        $code = $versionCode ?: (string) config('deltasupport.associate_commission.active_version', self::VERSION);

        return AssociateCommissionVersion::query()
            ->with(['packages.ranks'])
            ->where('version_code', $code)
            ->first();
    }

    /**
     * @return list<string>
     */
    public function integrityErrors(?string $versionCode = null): array
    {
        $errors = [];
        $version = $this->storedVersion($versionCode);
        if ($version === null) {
            return ['Associate commission version is missing. Run migrations.'];
        }

        if ((int) $version->commission_days !== 400) {
            $errors[] = 'Associate commission duration must be 400 days.';
        }

        $packages = $version->packages;
        if ($packages->count() !== 4) {
            $errors[] = 'Associate commission version must contain exactly 4 packages.';
        }

        foreach (self::TIERS as $tierCode => $expected) {
            $row = $packages->firstWhere('tier_code', $tierCode);
            if ($row === null) {
                $errors[] = 'Missing associate tier '.$tierCode.'.';
                continue;
            }
            if (bccomp((string) $row->package_price, $expected['price'], 4) !== 0) {
                $errors[] = $tierCode.' price must be '.$expected['price'].'.';
            }
            if (bccomp((string) $row->daily_total, $expected['daily_total'], 4) !== 0) {
                $errors[] = $tierCode.' daily total must be '.$expected['daily_total'].'.';
            }
            if (bccomp((string) $row->duration_total, $expected['duration_total'], 4) !== 0) {
                $errors[] = $tierCode.' 400-day total must be '.$expected['duration_total'].'.';
            }

            $ranks = $row->ranks->keyBy('rank');
            if ($ranks->count() !== 20) {
                $errors[] = $tierCode.' must have exactly 20 ranks.';
            }
            $sum = '0.0000';
            foreach ($expected['ranks'] as $rank => $amount) {
                $stored = $ranks->get($rank);
                if ($stored === null || bccomp((string) $stored->daily_amount, $amount, 4) !== 0) {
                    $errors[] = $tierCode.' rank '.$rank.' must be '.$amount.' USDT/day.';
                } else {
                    $sum = bcadd($sum, (string) $stored->daily_amount, 4);
                }
            }
            if (bccomp($sum, $expected['daily_total'], 4) !== 0) {
                $errors[] = $tierCode.' rank sum '.$sum.' does not equal daily total '.$expected['daily_total'].'.';
            }
            $duration = bcmul($sum, '400', 4);
            if (bccomp($duration, $expected['duration_total'], 4) !== 0) {
                $errors[] = $tierCode.' 400-day calculation '.$duration.' is incorrect.';
            }
        }

        if ($this->postingEnabled()) {
            $errors[] = 'Associate commission posting_enabled is true. Keep false until written activation approval.';
        }

        return $errors;
    }

    /**
     * Full admin/read-only preview. Never writes.
     *
     * @return array<string, mixed>
     */
    public function preview(?string $versionCode = null): array
    {
        $version = $this->storedVersion($versionCode);
        $tiers = [];
        foreach (self::TIERS as $tierCode => $canonical) {
            $mappedCode = $this->mappedPackageCode($tierCode);
            $catalog = $mappedCode ? Package::query()->where('code', $mappedCode)->first() : null;
            $income = $mappedCode ? $this->policy->definition($mappedCode) : null;
            $priceMatches = Package::query()
                ->orderBy('id')
                ->get(['id', 'code', 'name', 'price_usd'])
                ->filter(fn (Package $row): bool => bccomp(bcadd((string) $row->price_usd, '0', 4), $canonical['price'], 4) === 0)
                ->values();

            $ranks = [];
            foreach ($canonical['ranks'] as $rank => $amount) {
                $ranks[] = [
                    'rank' => $rank,
                    'daily_amount' => $amount,
                    'duration_amount' => bcmul($amount, '400', 4),
                ];
            }

            $mappingStatus = match (true) {
                $mappedCode === null => 'unmapped',
                $catalog === null => 'code_pending_catalog',
                default => 'mapped',
            };

            $tiers[] = [
                'tier_code' => $tierCode,
                'label' => $canonical['label'],
                'package_price' => $canonical['price'],
                'mapped_package_code' => $mappedCode,
                'mapping_status' => $mappingStatus,
                'catalog_package_id' => $catalog?->id,
                'catalog_package_name' => $catalog?->name,
                'catalog_package_found' => $catalog !== null,
                'price_match_codes' => $priceMatches->pluck('code')->values()->all(),
                'price_match_ambiguous' => $priceMatches->count() > 1,
                'roi_daily' => $income['roi_daily'] ?? null,
                'roi_days' => $income['roi_days'] ?? null,
                'roi_configured' => $income !== null,
                'roi_display' => $income === null ? 'ROI Not Configured' : ($income['roi_daily'].' USDT / '.$income['roi_days'].' days'),
                'legacy_commission_pool' => $income['commission_pool'] ?? null,
                'commission_days' => 400,
                'ranks' => $ranks,
                'daily_total' => $canonical['daily_total'],
                'duration_total' => $canonical['duration_total'],
                'pool_matches_legacy' => $income !== null
                    && bccomp((string) $income['commission_pool'], $canonical['daily_total'], 4) === 0,
                'snapshot_status' => $this->snapshotOnApprovalEnabled() ? 'pending_approval_flag_on' : 'disabled',
                'posting_status' => $this->postingEnabled() ? 'enabled_unsafe' : 'disabled',
                'warnings' => $this->tierWarnings($tierCode, $canonical, $mappedCode, $catalog, $income, $priceMatches),
            ];
        }

        return [
            'version_code' => $version?->version_code ?? self::VERSION,
            'label' => $version?->label ?? 'Associate Commission Structure v1',
            'commission_days' => (int) ($version?->commission_days ?? 400),
            'is_active' => (bool) ($version?->is_active ?? false),
            'snapshot_on_approval' => $this->snapshotOnApprovalEnabled(),
            'posting_enabled' => $this->postingEnabled(),
            'accrual_uses_associate_posting' => $this->accrualUsesAssociatePostingPath(),
            'effective_from' => $version?->effective_from?->toDateString(),
            'notes' => $version?->notes,
            'integrity_errors' => $this->integrityErrors($versionCode),
            'tiers' => $tiers,
            'catalog_finding' => $this->catalogFinding(),
            'writes' => false,
            'interpretation' => 'Commission Pool in the legacy engine is the theoretical daily total across ranks 1–20. Rank amounts equal pool × percentage / 100 using the inactive delta_support_v1 chart. Associate amounts are the approved fixed USDT/day values from the client chart. Live accrual still uses legacy pool×percentage while associate posting stays disabled.',
        ];
    }

    /**
     * @return array{summary: string, details: list<string>}
     */
    public function catalogFinding(): array
    {
        $details = [
            'Verified production associate codes: DST-TOUR-001 (SILVER 50), DST-ROI-002 (GOLD 100), DST-TOUR-003 (PREMIUM 500), DST-TOUR-004 (VIP 1000).',
            'ProductionCatalogSeeder still seeds a legacy DST-TOUR-003 row at 1000 USDT for demo/activation tests; production DB uses DST-TOUR-003 as PREMIUM 500.',
            'package_income ROI for DST-TOUR-003 remains 2.5000×1000 in config and is NOT automatically moved to DST-TOUR-004.',
            'Associate commission posting/snapshot flags stay disabled; this service does not create or rename package rows.',
        ];

        return [
            'summary' => 'Associate tiers map to verified production codes DST-TOUR-001 / DST-ROI-002 / DST-TOUR-003 / DST-TOUR-004. ROI for DST-TOUR-003 vs VIP remains a separate business decision.',
            'details' => $details,
        ];
    }

    /**
     * @return array{tier_code: string, version_code: string, commission_days: int, daily_total: string, ranks: array<int, string>}|null
     */
    public function snapshotPayloadForPurchase(PackagePurchase $purchase): ?array
    {
        // Code-only resolution — never bind by price alone (ambiguous when multiple packages share a price).
        $tier = $this->resolveTierForPackageCode((string) $purchase->package_code);
        if ($tier === null) {
            return null;
        }

        $canonical = self::TIERS[$tier];

        return [
            'tier_code' => $tier,
            'version_code' => self::VERSION,
            'commission_days' => 400,
            'daily_total' => $canonical['daily_total'],
            'ranks' => $canonical['ranks'],
        ];
    }

    public function resolveTierForPackageCode(string $packageCode): ?string
    {
        foreach (array_keys(self::TIERS) as $tier) {
            if ($this->mappedPackageCode($tier) === $packageCode) {
                return $tier;
            }
        }

        return null;
    }

    /**
     * Price hint only — never used for snapshots or posting identity.
     * Returns null when more than one catalog package shares the price.
     */
    public function resolveTierForPrice(string $price): ?string
    {
        $normalized = bcadd($price, '0', 4);
        $matches = [];
        foreach (self::TIERS as $tier => $definition) {
            if (bccomp($normalized, $definition['price'], 4) === 0) {
                $matches[] = $tier;
            }
        }
        if (count($matches) !== 1) {
            return null;
        }

        $catalogMatches = Package::query()
            ->get(['id', 'price_usd'])
            ->filter(fn (Package $row): bool => bccomp(bcadd((string) $row->price_usd, '0', 4), $normalized, 4) === 0)
            ->count();

        return $catalogMatches > 1 ? null : $matches[0];
    }

    /**
     * @param  array<string, mixed>  $canonical
     * @param  Collection<int, Package>  $priceMatches
     * @return list<string>
     */
    private function tierWarnings(
        string $tierCode,
        array $canonical,
        ?string $mappedCode,
        ?Package $catalog,
        ?array $income,
        Collection $priceMatches,
    ): array {
        $warnings = [];
        if ($mappedCode === null || $mappedCode === '') {
            $warnings[] = 'No catalog package code is mapped yet.';
        } elseif ($catalog === null) {
            $warnings[] = 'Mapped package code '.$mappedCode.' was not found in this database packages table. If production uses a different code, set DELTASUPPORT_ASSOC_CODE_'.strtoupper($tierCode).'.';
        }
        if ($income === null) {
            $warnings[] = 'ROI Not Configured for this tier. Associate Commission does not define ROI.';
        }
        if ($income !== null && bccomp((string) $income['commission_pool'], $canonical['daily_total'], 4) !== 0) {
            $warnings[] = 'Legacy commission_pool '.$income['commission_pool'].' differs from associate daily total '.$canonical['daily_total'].'. Associate mapping does not rewrite ROI/pool config.';
        }
        if ($tierCode === 'premium' && $mappedCode === 'DST-TOUR-003' && $income !== null
            && bccomp((string) ($income['roi_daily'] ?? '0'), '2.5000', 4) === 0) {
            $warnings[] = 'DST-TOUR-003 still has legacy ROI 2.5000×1000 in package_income while associate tier is PREMIUM 500 / pool 1.2000. ROI was not moved to VIP.';
        }
        if ($tierCode === 'vip' && $mappedCode === 'DST-TOUR-004' && $income === null) {
            $warnings[] = 'VIP DST-TOUR-004 has no package_income ROI entry. Do not auto-copy PREMIUM/Tour ROI here.';
        }
        if ($priceMatches->count() > 1) {
            $warnings[] = 'Multiple catalog packages share price '.$canonical['price'].' ('.$priceMatches->pluck('code')->implode(', ').'). Identity must use package code, not price.';
        }

        return $warnings;
    }

    /**
     * @return Collection<int, AssociateCommissionPackage>
     */
    public function packages(?string $versionCode = null): Collection
    {
        return $this->storedVersion($versionCode)?->packages ?? collect();
    }
}
