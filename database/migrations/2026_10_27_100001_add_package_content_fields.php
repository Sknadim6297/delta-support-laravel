<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table): void {
            if (! Schema::hasColumn('packages', 'full_description')) {
                $table->text('full_description')->nullable()->after('description');
            }
            if (! Schema::hasColumn('packages', 'destinations')) {
                $table->text('destinations')->nullable()->after('tour_details');
            }
            if (! Schema::hasColumn('packages', 'inclusions')) {
                $table->text('inclusions')->nullable()->after('benefits');
            }
            if (! Schema::hasColumn('packages', 'exclusions')) {
                $table->text('exclusions')->nullable()->after('inclusions');
            }
            if (! Schema::hasColumn('packages', 'package_type')) {
                $table->string('package_type')->default('tour')->after('code');
                $table->index('package_type');
            }
            if (! Schema::hasColumn('packages', 'level_commission_eligible')) {
                $table->boolean('level_commission_eligible')->default(false)->after('roi_eligible');
            }
        });

        Schema::table('package_purchases', function (Blueprint $table): void {
            if (! Schema::hasColumn('package_purchases', 'package_type')) {
                $table->string('package_type')->nullable()->after('package_name');
            }
            if (! Schema::hasColumn('package_purchases', 'description')) {
                $table->text('description')->nullable()->after('benefits');
            }
            if (! Schema::hasColumn('package_purchases', 'full_description')) {
                $table->text('full_description')->nullable()->after('description');
            }
            if (! Schema::hasColumn('package_purchases', 'destinations')) {
                $table->text('destinations')->nullable()->after('full_description');
            }
            if (! Schema::hasColumn('package_purchases', 'inclusions')) {
                $table->text('inclusions')->nullable()->after('destinations');
            }
            if (! Schema::hasColumn('package_purchases', 'exclusions')) {
                $table->text('exclusions')->nullable()->after('inclusions');
            }
        });
    }

    public function down(): void
    {
        Schema::table('package_purchases', function (Blueprint $table): void {
            $table->dropColumn([
                'package_type',
                'description',
                'full_description',
                'destinations',
                'inclusions',
                'exclusions',
            ]);
        });

        Schema::table('packages', function (Blueprint $table): void {
            $table->dropIndex(['package_type']);
            $table->dropColumn([
                'full_description',
                'destinations',
                'inclusions',
                'exclusions',
                'package_type',
                'level_commission_eligible',
            ]);
        });
    }
};
