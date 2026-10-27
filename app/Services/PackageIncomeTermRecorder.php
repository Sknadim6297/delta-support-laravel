<?php

namespace App\Services;

use App\Models\PackageIncomeTerm;
use App\Models\PackagePurchase;
use Illuminate\Support\Facades\DB;

class PackageIncomeTermRecorder
{
    /**
     * Inserts one immutable snapshot. Omitted rates and amounts stay null.
     * A repeated call returns the original row and does not change the purchase price.
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

            return PackageIncomeTerm::query()->create([
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
            ]);
        });
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
