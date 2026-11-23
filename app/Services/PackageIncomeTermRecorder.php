<?php

namespace App\Services;

use App\Models\PackageIncomeTerm;
use App\Models\PackagePurchase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PackageIncomeTermRecorder
{
    public function __construct(
        private readonly AssociateCommissionStructure $associate,
        private readonly PackageIncomeDefinitionResolver $resolver,
    ) {
    }

    /**
     * Inserts one immutable snapshot. A repeated call returns the original row.
     * When $terms omit ROI fields, resolves current definition for NEW snapshots only.
     * Associate ranks fill only when explicitly enabled. Never credits wallets.
     *
     * @param  array<string, mixed>  $terms
     */
    public function record(PackagePurchase $purchase, array $terms = []): PackageIncomeTerm
    {
        return DB::transaction(function () use ($purchase, $terms): PackageIncomeTerm {
            $locked = PackagePurchase::query()->whereKey($purchase->id)->lockForUpdate()->firstOrFail();
            $existing = PackageIncomeTerm::query()->where('package_purchase_id', $locked->id)->first();
            if ($existing) {
                return $existing;
            }

            // New approvals only: resolve from linked plan version or authorized catalog.
            // Never upgrades an existing empty historical term (early return above).
            $resolved = $this->resolver->forNewApprovalSnapshot($locked);

            $payload = [
                'package_purchase_id' => $locked->id,
                'reference' => 'PIT-'.$locked->reference,
                'income_eligible' => $this->nullableBool($terms, 'income_eligible'),
                'monthly_rate_percentage' => $this->nullableDecimal($terms, 'monthly_rate_percentage'),
                'monthly_amount' => $this->nullableDecimal($terms, 'monthly_amount'),
                'duration_months' => $this->nullableInteger($terms, 'duration_months'),
                'commission_base' => $this->nullableString($terms, 'commission_base'),
                'first_accrual_rule' => $this->nullableString($terms, 'first_accrual_rule'),
                'business_timezone' => $this->nullableString($terms, 'business_timezone'),
                'snapshotted_at' => now(),
            ];

            // Fill ROI snapshot from resolved definition only when caller omitted those keys.
            if ($resolved !== null) {
                if (! array_key_exists('income_eligible', $terms)) {
                    $payload['income_eligible'] = true;
                }
                if (! array_key_exists('monthly_amount', $terms)) {
                    $payload['monthly_amount'] = $resolved['roi_daily'];
                }
                if (! array_key_exists('duration_months', $terms)) {
                    $payload['duration_months'] = $resolved['roi_days'];
                }
                if (! array_key_exists('commission_base', $terms) && ($resolved['commission_pool'] ?? null) !== null) {
                    $payload['commission_base'] = $resolved['commission_pool'];
                }
                if (! array_key_exists('first_accrual_rule', $terms)) {
                    $payload['first_accrual_rule'] = 'approval_plus_wait_days';
                }
                if (! array_key_exists('business_timezone', $terms)) {
                    $payload['business_timezone'] = (string) config('deltasupport.package_income.timezone', 'Asia/Kolkata');
                }
            }

            if (Schema::hasColumn('package_income_terms', 'package_plan_version_id')) {
                $payload['package_plan_version_id'] = $locked->package_plan_version_id
                    ?? ($resolved['package_plan_version_id'] ?? null);
            }
            if (Schema::hasColumn('package_income_terms', 'income_definition_source')) {
                $payload['income_definition_source'] = $this->nullableString($terms, 'income_definition_source')
                    ?? ($resolved['source'] ?? null);
            }

            if (Schema::hasColumn('package_income_terms', 'associate_commission_version')) {
                $payload = array_merge($payload, $this->associateSnapshot($locked, $terms));
            }

            return PackageIncomeTerm::query()->create($payload);
        });
    }

    /**
     * @param  array<string, mixed>  $terms
     * @return array<string, mixed>
     */
    private function associateSnapshot(PackagePurchase $purchase, array $terms): array
    {
        $empty = [
            'associate_commission_version' => null,
            'associate_tier_code' => null,
            'associate_commission_days' => null,
            'associate_daily_total' => null,
            'associate_rank_amounts' => null,
        ];

        if (array_key_exists('associate_rank_amounts', $terms)) {
            return [
                'associate_commission_version' => $this->nullableString($terms, 'associate_commission_version'),
                'associate_tier_code' => $this->nullableString($terms, 'associate_tier_code'),
                'associate_commission_days' => $this->nullableInteger($terms, 'associate_commission_days'),
                'associate_daily_total' => $this->nullableDecimal($terms, 'associate_daily_total'),
                'associate_rank_amounts' => is_array($terms['associate_rank_amounts'] ?? null)
                    ? $terms['associate_rank_amounts']
                    : null,
            ];
        }

        if (! $this->associate->snapshotOnApprovalEnabled()) {
            return $empty;
        }

        $snapshot = $this->associate->snapshotPayloadForPurchase($purchase);
        if ($snapshot === null) {
            return $empty;
        }

        return [
            'associate_commission_version' => $snapshot['version_code'],
            'associate_tier_code' => $snapshot['tier_code'],
            'associate_commission_days' => $snapshot['commission_days'],
            'associate_daily_total' => $snapshot['daily_total'],
            'associate_rank_amounts' => $snapshot['ranks'],
        ];
    }

    /**
     * @param  array<string, mixed>  $terms
     */
    private function nullableDecimal(array $terms, string $key): ?string
    {
        if (! array_key_exists($key, $terms) || $terms[$key] === null || $terms[$key] === '') {
            return null;
        }

        return bcadd((string) $terms[$key], '0', 4);
    }

    /**
     * @param  array<string, mixed>  $terms
     */
    private function nullableString(array $terms, string $key): ?string
    {
        if (! array_key_exists($key, $terms) || $terms[$key] === null) {
            return null;
        }

        $value = trim((string) $terms[$key]);

        return $value === '' ? null : $value;
    }

    /**
     * @param  array<string, mixed>  $terms
     */
    private function nullableInteger(array $terms, string $key): ?int
    {
        if (! array_key_exists($key, $terms) || $terms[$key] === null || $terms[$key] === '') {
            return null;
        }

        return (int) $terms[$key];
    }

    /**
     * @param  array<string, mixed>  $terms
     */
    private function nullableBool(array $terms, string $key): ?bool
    {
        if (! array_key_exists($key, $terms) || $terms[$key] === null || $terms[$key] === '') {
            return null;
        }

        return (bool) $terms[$key];
    }
}
