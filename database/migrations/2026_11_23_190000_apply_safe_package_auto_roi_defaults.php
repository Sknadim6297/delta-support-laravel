<?php

use App\Services\PackageAutoRoiControl;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Production-safe Auto ROI defaults (idempotent).
 * Does not rewrite purchases, snapshots, wallets, or plan ROI amounts.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('packages') || ! Schema::hasColumn('packages', 'auto_roi_enabled')) {
            return;
        }

        app(PackageAutoRoiControl::class)->applySafeProductionDefaults();
    }

    public function down(): void
    {
        // Non-destructive. Schema rollback is owned by the additive column migration.
    }
};
