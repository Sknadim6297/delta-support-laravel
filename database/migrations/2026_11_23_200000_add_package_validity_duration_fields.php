<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catalog + plan-version Package Validity (separate from ROI days / tour nights).
 * Additive only. Does not rewrite purchases, wallets, or ROI terms.
 * Expiry enforcement is intentionally not activated by this migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('packages')) {
            Schema::table('packages', function (Blueprint $table): void {
                if (! Schema::hasColumn('packages', 'validity_unlimited')) {
                    $table->boolean('validity_unlimited')->default(false)->after('sort_order');
                }
                if (! Schema::hasColumn('packages', 'validity_duration')) {
                    $table->unsignedInteger('validity_duration')->nullable()->after('validity_unlimited');
                }
                if (! Schema::hasColumn('packages', 'validity_unit')) {
                    $table->string('validity_unit', 16)->nullable()->after('validity_duration');
                }
            });
        }

        if (Schema::hasTable('package_plan_versions')) {
            Schema::table('package_plan_versions', function (Blueprint $table): void {
                if (! Schema::hasColumn('package_plan_versions', 'validity_unlimited')) {
                    $table->boolean('validity_unlimited')->default(false)->after('food_coupon_currency');
                }
                if (! Schema::hasColumn('package_plan_versions', 'validity_duration')) {
                    $table->unsignedInteger('validity_duration')->nullable()->after('validity_unlimited');
                }
                if (! Schema::hasColumn('package_plan_versions', 'validity_unit')) {
                    $table->string('validity_unit', 16)->nullable()->after('validity_duration');
                }
            });
        }

        if (Schema::hasTable('package_purchases')) {
            Schema::table('package_purchases', function (Blueprint $table): void {
                if (! Schema::hasColumn('package_purchases', 'validity_unlimited')) {
                    $table->boolean('validity_unlimited')->nullable()->after('package_plan_version_id');
                }
                if (! Schema::hasColumn('package_purchases', 'validity_duration')) {
                    $table->unsignedInteger('validity_duration')->nullable()->after('validity_unlimited');
                }
                if (! Schema::hasColumn('package_purchases', 'validity_unit')) {
                    $table->string('validity_unit', 16)->nullable()->after('validity_duration');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('package_purchases')) {
            Schema::table('package_purchases', function (Blueprint $table): void {
                foreach (['validity_unit', 'validity_duration', 'validity_unlimited'] as $column) {
                    if (Schema::hasColumn('package_purchases', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('package_plan_versions')) {
            Schema::table('package_plan_versions', function (Blueprint $table): void {
                foreach (['validity_unit', 'validity_duration', 'validity_unlimited'] as $column) {
                    if (Schema::hasColumn('package_plan_versions', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('packages')) {
            Schema::table('packages', function (Blueprint $table): void {
                foreach (['validity_unit', 'validity_duration', 'validity_unlimited'] as $column) {
                    if (Schema::hasColumn('packages', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
