<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Catalog-only: keep the six existing production tour packages Active when present.
 *
 * - Does not create, rename, or reprice packages.
 * - Does not write package_income config or income terms.
 * - Does not enable Associate posting / snapshots / wallet ledger.
 * - Does not touch VERIFY-1 (verification catalog; not an Original Tour Plan income package).
 * - Preserves DELTA SHARE (DST-RY-006) roi_eligible=false and level_commission_eligible=false.
 * - Does not flip roi_eligible for SILVER/VIP/DIAMOND (Active ≠ income configured).
 */
return new class extends Migration
{
    private const PRODUCTION_CODES = [
        'DST-TOUR-001',
        'DST-ROI-002',
        'DST-TOUR-003',
        'DST-TOUR-004',
        'DST-TOUR-005',
        'DST-RY-006',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('packages') || ! Schema::hasColumn('packages', 'is_active')) {
            return;
        }

        $now = now();

        DB::table('packages')
            ->whereIn('code', self::PRODUCTION_CODES)
            ->update(['is_active' => true, 'updated_at' => $now]);

        if (Schema::hasColumn('packages', 'roi_eligible')
            && Schema::hasColumn('packages', 'level_commission_eligible')) {
            DB::table('packages')
                ->where('code', 'DST-RY-006')
                ->update([
                    'roi_eligible' => false,
                    'level_commission_eligible' => false,
                    'updated_at' => $now,
                ]);
        }
    }

    public function down(): void
    {
        // Intentionally empty: deactivating production catalog packages is a separate business action.
    }
};
