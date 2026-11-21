<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Corrects associate_v1 package-code mapping to verified production codes:
 * SILVER DST-TOUR-001, GOLD DST-ROI-002, PREMIUM DST-TOUR-003, VIP DST-TOUR-004.
 *
 * Does not create/rename packages, change ROI config, enable posting/snapshots,
 * or modify purchases/ledgers. Safe to run after 2026_11_21_180000.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('associate_commission_packages')
            || ! Schema::hasTable('associate_commission_versions')) {
            return;
        }

        $versionId = DB::table('associate_commission_versions')
            ->where('version_code', 'associate_v1')
            ->value('id');
        if ($versionId === null) {
            return;
        }

        $map = [
            'silver' => 'DST-TOUR-001',
            'gold' => 'DST-ROI-002',
            'premium' => 'DST-TOUR-003',
            'vip' => 'DST-TOUR-004',
        ];

        foreach ($map as $tier => $code) {
            DB::table('associate_commission_packages')
                ->where('associate_commission_version_id', $versionId)
                ->where('tier_code', $tier)
                ->update([
                    'mapped_package_code' => $code,
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('associate_commission_packages')
            || ! Schema::hasTable('associate_commission_versions')) {
            return;
        }

        $versionId = DB::table('associate_commission_versions')
            ->where('version_code', 'associate_v1')
            ->value('id');
        if ($versionId === null) {
            return;
        }

        // Restore the pre-correction proposed mapping from 2026_11_21_180000.
        $previous = [
            'silver' => 'DST-SILVER-050',
            'gold' => 'DST-ROI-002',
            'premium' => 'DST-PREMIUM-500',
            'vip' => 'DST-TOUR-003',
        ];

        foreach ($previous as $tier => $code) {
            DB::table('associate_commission_packages')
                ->where('associate_commission_version_id', $versionId)
                ->where('tier_code', $tier)
                ->update([
                    'mapped_package_code' => $code,
                    'updated_at' => now(),
                ]);
        }
    }
};
