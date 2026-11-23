<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Keeps verified production catalog packages Active for admin/member listing.
 * Does not create, rename, or reprice packages. Does not enable Associate posting,
 * package_income posting, or wallet ledger flags.
 *
 * VERIFY-1 is membership verification only (roi_eligible=false). Including it here
 * does NOT configure package_income and must not be confused with Original Tour Plan
 * income packages. Prefer 2026_11_23_150000 for the six production tour codes
 * (adds DST-TOUR-005 / DST-RY-006 without activating VERIFY-1 as income).
 */
return new class extends Migration
{
    private const CODES = [
        'DST-TOUR-001',
        'DST-ROI-002',
        'DST-TOUR-003',
        'DST-TOUR-004',
        'VERIFY-1',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('packages') || ! Schema::hasColumn('packages', 'is_active')) {
            return;
        }

        DB::table('packages')
            ->whereIn('code', self::CODES)
            ->update(['is_active' => true, 'updated_at' => now()]);
    }

    public function down(): void
    {
        // Intentionally empty: deactivating production catalog packages is a separate business action.
    }
};
