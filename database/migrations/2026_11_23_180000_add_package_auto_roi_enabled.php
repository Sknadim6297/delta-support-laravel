<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Additive only. Does not rewrite purchases, wallets, or plan versions.
 * Existing ROI-eligible catalog rows keep prior operational intent via backfill;
 * new packages start with Auto ROI OFF until Admin explicitly enables.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('packages')) {
            return;
        }

        if (! Schema::hasColumn('packages', 'auto_roi_enabled')) {
            Schema::table('packages', function (Blueprint $table): void {
                $table->boolean('auto_roi_enabled')->default(false)->after('roi_eligible');
            });
        }

        // Preserve prior ROI-eligible catalog intent for existing rows only.
        if (Schema::hasColumn('packages', 'roi_eligible')) {
            DB::table('packages')
                ->where('roi_eligible', true)
                ->update(['auto_roi_enabled' => true]);

            // Tour-only / DELTA SHARE style rows must stay OFF.
            DB::table('packages')
                ->where(function ($query): void {
                    $query->where('package_type', 'tour')
                        ->orWhere('roi_eligible', false);
                })
                ->update(['auto_roi_enabled' => false]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('packages') && Schema::hasColumn('packages', 'auto_roi_enabled')) {
            Schema::table('packages', function (Blueprint $table): void {
                $table->dropColumn('auto_roi_enabled');
            });
        }
    }
};
