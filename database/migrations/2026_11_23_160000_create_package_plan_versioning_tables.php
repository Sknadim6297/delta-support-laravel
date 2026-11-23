<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Additive package plan versioning for Admin-managed income/tour terms.
 * Does not rewrite package_income config, wallets, accruals, or historical terms.
 * Does not enable associate posting or financial wallet posting.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('packages')) {
            Schema::table('packages', function (Blueprint $table): void {
                if (! Schema::hasColumn('packages', 'purchase_available')) {
                    $table->boolean('purchase_available')->default(true)->after('is_active');
                }
                if (! Schema::hasColumn('packages', 'income_source')) {
                    $table->string('income_source', 32)->default('legacy_config')->after('purchase_available');
                }
                if (! Schema::hasColumn('packages', 'active_plan_version_id')) {
                    $table->unsignedBigInteger('active_plan_version_id')->nullable()->after('income_source');
                }
            });

            // Existing codes that already have package_income config stay on legacy_config.
            if (Schema::hasColumn('packages', 'income_source')) {
                DB::table('packages')->whereIn('code', ['DST-ROI-002', 'DST-TOUR-003', 'DEMO-COAST', 'DEMO-HILLS'])
                    ->update(['income_source' => 'legacy_config']);
            }
        }

        if (! Schema::hasTable('package_plan_versions')) {
            Schema::create('package_plan_versions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('package_id')->constrained('packages')->cascadeOnDelete();
                $table->unsignedInteger('version_number');
                $table->string('status', 24)->default('draft'); // draft|published|superseded
                $table->boolean('has_daily_roi')->default(false);
                $table->decimal('roi_daily', 18, 4)->nullable();
                $table->unsignedInteger('roi_days')->nullable();
                $table->unsignedInteger('wait_days')->nullable();
                $table->string('roi_start_rule', 64)->nullable();
                $table->string('roi_end_rule', 64)->nullable();
                $table->decimal('commission_pool', 18, 4)->nullable();
                $table->unsignedInteger('commission_days')->nullable();
                $table->boolean('level_commission_eligible')->default(false);
                $table->unsignedInteger('tour_eligible_after_days')->nullable();
                $table->unsignedInteger('tour_nights')->nullable();
                $table->unsignedInteger('tour_days')->nullable();
                $table->unsignedInteger('tour_adults')->nullable();
                $table->unsignedInteger('tour_children')->nullable();
                $table->decimal('food_coupon_amount', 18, 4)->nullable();
                $table->string('food_coupon_currency', 16)->nullable();
                $table->text('tour_description')->nullable();
                $table->string('label', 120)->nullable();
                $table->text('change_note')->nullable();
                $table->boolean('accrual_authority')->default(false);
                $table->timestamp('published_at')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['package_id', 'version_number']);
                $table->index(['package_id', 'status']);
            });
        }

        if (! Schema::hasTable('package_plan_audits')) {
            Schema::create('package_plan_audits', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('package_id')->constrained('packages')->cascadeOnDelete();
                $table->foreignId('package_plan_version_id')->nullable()->constrained('package_plan_versions')->nullOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('action', 64);
                $table->json('before_json')->nullable();
                $table->json('after_json')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->timestamps();

                $table->index(['package_id', 'created_at']);
            });
        }

        if (Schema::hasTable('package_purchases') && ! Schema::hasColumn('package_purchases', 'package_plan_version_id')) {
            Schema::table('package_purchases', function (Blueprint $table): void {
                $table->foreignId('package_plan_version_id')->nullable()->after('package_id')
                    ->constrained('package_plan_versions')->nullOnDelete();
            });
        }

        if (Schema::hasTable('package_income_terms')) {
            Schema::table('package_income_terms', function (Blueprint $table): void {
                if (! Schema::hasColumn('package_income_terms', 'package_plan_version_id')) {
                    $table->foreignId('package_plan_version_id')->nullable()->after('package_purchase_id')
                        ->constrained('package_plan_versions')->nullOnDelete();
                }
                if (! Schema::hasColumn('package_income_terms', 'income_definition_source')) {
                    $table->string('income_definition_source', 40)->nullable()->after('package_plan_version_id');
                }
            });
        }

    }

    public function down(): void
    {
        // Additive financial schema — down is intentionally conservative.
        if (Schema::hasTable('package_income_terms')) {
            Schema::table('package_income_terms', function (Blueprint $table): void {
                if (Schema::hasColumn('package_income_terms', 'package_plan_version_id')) {
                    $table->dropConstrainedForeignId('package_plan_version_id');
                }
                if (Schema::hasColumn('package_income_terms', 'income_definition_source')) {
                    $table->dropColumn('income_definition_source');
                }
            });
        }

        if (Schema::hasTable('package_purchases') && Schema::hasColumn('package_purchases', 'package_plan_version_id')) {
            Schema::table('package_purchases', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('package_plan_version_id');
            });
        }

        Schema::dropIfExists('package_plan_audits');
        Schema::dropIfExists('package_plan_versions');

        if (Schema::hasTable('packages')) {
            Schema::table('packages', function (Blueprint $table): void {
                foreach (['active_plan_version_id', 'income_source', 'purchase_available'] as $column) {
                    if (Schema::hasColumn('packages', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
