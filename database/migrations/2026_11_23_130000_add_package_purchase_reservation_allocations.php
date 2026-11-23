<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Allows wallet-funded package purchases to reserve Working + ROI together.
 * Does not change existing single-wallet reservation rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('package_purchase_reservations')) {
            return;
        }

        Schema::table('package_purchase_reservations', function (Blueprint $table): void {
            if (! Schema::hasColumn('package_purchase_reservations', 'allocations')) {
                $table->json('allocations')->nullable()->after('reserved_amount');
            }
        });

        // Expand wallet identity beyond the original working-only enum (MySQL).
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE package_purchase_reservations MODIFY wallet VARCHAR(20) NOT NULL");
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('package_purchase_reservations')) {
            return;
        }

        Schema::table('package_purchase_reservations', function (Blueprint $table): void {
            if (Schema::hasColumn('package_purchase_reservations', 'allocations')) {
                $table->dropColumn('allocations');
            }
        });
    }
};
