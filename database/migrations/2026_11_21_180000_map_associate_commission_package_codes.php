<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Maps associate_v1 tiers to stable package codes.
 * Does not create packages, change ROI, or enable posting/snapshots.
 * Safe on populated MySQL: updates mapped_package_code only when null or outdated proposal.
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
            'silver' => 'DST-SILVER-050',
            'gold' => 'DST-ROI-002',
            'premium' => 'DST-PREMIUM-500',
            'vip' => 'DST-TOUR-003',
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

        // Only clear proposed Silver/Premium codes; keep Gold/VIP production mappings.
        DB::table('associate_commission_packages')
            ->where('associate_commission_version_id', $versionId)
            ->whereIn('tier_code', ['silver', 'premium'])
            ->update([
                'mapped_package_code' => null,
                'updated_at' => now(),
            ]);
    }
};
