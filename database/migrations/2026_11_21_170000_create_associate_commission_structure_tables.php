<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Versioned Associate Commission Structure (20 ranks × 400 days).
 * Seeded inactive. Does not enable posting, change ROI, or alter wallets.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('associate_commission_versions', function (Blueprint $table) {
            $table->id();
            $table->string('version_code', 64)->unique();
            $table->string('label');
            $table->unsignedSmallInteger('commission_days')->default(400);
            $table->boolean('is_active')->default(false);
            $table->boolean('snapshot_on_approval')->default(false);
            $table->date('effective_from')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('associate_commission_packages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('associate_commission_version_id');
            $table->string('tier_code', 32);
            $table->string('label');
            $table->decimal('package_price', 16, 4);
            $table->string('mapped_package_code', 32)->nullable();
            $table->decimal('daily_total', 16, 4);
            $table->decimal('duration_total', 16, 4);
            $table->timestamps();

            $table->foreign('associate_commission_version_id', 'assoc_comm_pkg_version_fk')
                ->references('id')
                ->on('associate_commission_versions')
                ->cascadeOnDelete();
            $table->unique(['associate_commission_version_id', 'tier_code'], 'assoc_comm_pkg_version_tier_uq');
            $table->index('mapped_package_code');
        });

        Schema::create('associate_commission_ranks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('associate_commission_package_id');
            $table->unsignedTinyInteger('rank');
            $table->decimal('daily_amount', 16, 4);
            $table->timestamps();

            $table->foreign('associate_commission_package_id', 'assoc_comm_rank_pkg_fk')
                ->references('id')
                ->on('associate_commission_packages')
                ->cascadeOnDelete();
            $table->unique(['associate_commission_package_id', 'rank'], 'assoc_comm_rank_pkg_rank_uq');
        });

        if (Schema::hasTable('package_income_terms')) {
            Schema::table('package_income_terms', function (Blueprint $table): void {
                if (! Schema::hasColumn('package_income_terms', 'associate_commission_version')) {
                    $table->string('associate_commission_version', 64)->nullable()->after('business_timezone');
                }
                if (! Schema::hasColumn('package_income_terms', 'associate_tier_code')) {
                    $table->string('associate_tier_code', 32)->nullable()->after('associate_commission_version');
                }
                if (! Schema::hasColumn('package_income_terms', 'associate_commission_days')) {
                    $table->unsignedSmallInteger('associate_commission_days')->nullable()->after('associate_tier_code');
                }
                if (! Schema::hasColumn('package_income_terms', 'associate_daily_total')) {
                    $table->decimal('associate_daily_total', 16, 4)->nullable()->after('associate_commission_days');
                }
                if (! Schema::hasColumn('package_income_terms', 'associate_rank_amounts')) {
                    $table->json('associate_rank_amounts')->nullable()->after('associate_daily_total');
                }
            });
        }

        $now = now();
        $versionId = DB::table('associate_commission_versions')->insertGetId([
            'version_code' => 'associate_v1',
            'label' => 'Associate Commission Structure v1',
            'commission_days' => 400,
            'is_active' => false,
            'snapshot_on_approval' => false,
            'effective_from' => null,
            'notes' => 'Official 20-rank Associate Commission chart for Silver/Gold/Premium/VIP. Inactive until written activation. Does not define ROI.',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $tiers = [
            // Seed maps to verified production codes. If 2026_11_21_180000 already
            // ran with proposed DST-SILVER-050 / DST-PREMIUM-500 / VIP=DST-TOUR-003,
            // 2026_11_21_190000 corrects live rows; editing this seed alone does not.
            'silver' => [
                'label' => 'SILVER PACKAGE',
                'price' => '50.0000',
                'mapped' => 'DST-TOUR-001',
                'daily_total' => '0.1200',
                'duration_total' => '48.0000',
                'ranks' => [
                    1 => '0.0300', 2 => '0.0150', 3 => '0.0090', 4 => '0.0060',
                    5 => '0.0030', 6 => '0.0030', 7 => '0.0030', 8 => '0.0030',
                    9 => '0.0030', 10 => '0.0030', 11 => '0.0030', 12 => '0.0030',
                    13 => '0.0030', 14 => '0.0030', 15 => '0.0030', 16 => '0.0030',
                    17 => '0.0030', 18 => '0.0060', 19 => '0.0060', 20 => '0.0090',
                ],
            ],
            'gold' => [
                'label' => 'GOLD PACKAGE',
                'price' => '100.0000',
                'mapped' => 'DST-ROI-002',
                'daily_total' => '0.2400',
                'duration_total' => '96.0000',
                'ranks' => [
                    1 => '0.0600', 2 => '0.0300', 3 => '0.0180', 4 => '0.0120',
                    5 => '0.0060', 6 => '0.0060', 7 => '0.0060', 8 => '0.0060',
                    9 => '0.0060', 10 => '0.0060', 11 => '0.0060', 12 => '0.0060',
                    13 => '0.0060', 14 => '0.0060', 15 => '0.0060', 16 => '0.0060',
                    17 => '0.0060', 18 => '0.0120', 19 => '0.0120', 20 => '0.0180',
                ],
            ],
            'premium' => [
                'label' => 'PREMIUM PACKAGE',
                'price' => '500.0000',
                'mapped' => 'DST-TOUR-003',
                'daily_total' => '1.2000',
                'duration_total' => '480.0000',
                'ranks' => [
                    1 => '0.3000', 2 => '0.1500', 3 => '0.0900', 4 => '0.0600',
                    5 => '0.0300', 6 => '0.0300', 7 => '0.0300', 8 => '0.0300',
                    9 => '0.0300', 10 => '0.0300', 11 => '0.0300', 12 => '0.0300',
                    13 => '0.0300', 14 => '0.0300', 15 => '0.0300', 16 => '0.0300',
                    17 => '0.0300', 18 => '0.0600', 19 => '0.0600', 20 => '0.0900',
                ],
            ],
            'vip' => [
                'label' => 'VIP PACKAGE',
                'price' => '1000.0000',
                'mapped' => 'DST-TOUR-004',
                'daily_total' => '2.4000',
                'duration_total' => '960.0000',
                'ranks' => [
                    1 => '0.6000', 2 => '0.3000', 3 => '0.1800', 4 => '0.1200',
                    5 => '0.0600', 6 => '0.0600', 7 => '0.0600', 8 => '0.0600',
                    9 => '0.0600', 10 => '0.0600', 11 => '0.0600', 12 => '0.0600',
                    13 => '0.0600', 14 => '0.0600', 15 => '0.0600', 16 => '0.0600',
                    17 => '0.0600', 18 => '0.1200', 19 => '0.1200', 20 => '0.1800',
                ],
            ],
        ];

        foreach ($tiers as $tierCode => $tier) {
            $packageId = DB::table('associate_commission_packages')->insertGetId([
                'associate_commission_version_id' => $versionId,
                'tier_code' => $tierCode,
                'label' => $tier['label'],
                'package_price' => $tier['price'],
                'mapped_package_code' => $tier['mapped'],
                'daily_total' => $tier['daily_total'],
                'duration_total' => $tier['duration_total'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $rankRows = [];
            foreach ($tier['ranks'] as $rank => $amount) {
                $rankRows[] = [
                    'associate_commission_package_id' => $packageId,
                    'rank' => $rank,
                    'daily_amount' => $amount,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            DB::table('associate_commission_ranks')->insert($rankRows);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('package_income_terms')) {
            Schema::table('package_income_terms', function (Blueprint $table): void {
                foreach ([
                    'associate_rank_amounts',
                    'associate_daily_total',
                    'associate_commission_days',
                    'associate_tier_code',
                    'associate_commission_version',
                ] as $column) {
                    if (Schema::hasColumn('package_income_terms', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        Schema::dropIfExists('associate_commission_ranks');
        Schema::dropIfExists('associate_commission_packages');
        Schema::dropIfExists('associate_commission_versions');
    }
};
