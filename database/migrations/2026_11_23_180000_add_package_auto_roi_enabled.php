<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Additive schema only. Does not rewrite purchases, wallets, or plan versions.
 * Defaults OFF. Safe production backfill lives in
 * 2026_11_23_190000_apply_safe_package_auto_roi_defaults.php
 * so currently earning GOLD/PREMIUM stay ON and SILVER/VIP/DIAMOND stay OFF.
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
