<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Combined Direct Joining + Package Purchase Level Unlock qualification settings.
 * Versioned / draft-safe. Does not authorize posting or change live legacy unlock.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('level_income_rule_versions')) {
            return;
        }

        Schema::table('level_income_rule_versions', function (Blueprint $table): void {
            if (! Schema::hasColumn('level_income_rule_versions', 'combined_joining_enabled')) {
                $table->boolean('combined_joining_enabled')->default(false)->after('tour_only_qualifies_for_unlock');
            }
            if (! Schema::hasColumn('level_income_rule_versions', 'combined_purchase_enabled')) {
                $table->boolean('combined_purchase_enabled')->default(false)->after('combined_joining_enabled');
            }
            if (! Schema::hasColumn('level_income_rule_versions', 'combined_purchase_scope')) {
                $table->string('combined_purchase_scope', 64)
                    ->default('approved_package_directs')
                    ->after('combined_purchase_enabled');
            }
            if (! Schema::hasColumn('level_income_rule_versions', 'combined_repeat_purchase_mode')) {
                $table->string('combined_repeat_purchase_mode', 32)
                    ->default('unique_directs')
                    ->after('combined_purchase_scope');
            }
            if (! Schema::hasColumn('level_income_rule_versions', 'combined_double_credit_policy')) {
                $table->string('combined_double_credit_policy', 32)
                    ->default('prevent_overlap')
                    ->after('combined_repeat_purchase_mode');
            }
            if (! Schema::hasColumn('level_income_rule_versions', 'combined_joining_unit_value')) {
                $table->unsignedSmallInteger('combined_joining_unit_value')->default(1)->after('combined_double_credit_policy');
            }
            if (! Schema::hasColumn('level_income_rule_versions', 'combined_purchase_unit_value')) {
                $table->unsignedSmallInteger('combined_purchase_unit_value')->default(1)->after('combined_joining_unit_value');
            }
        });

        // Document combined mode as available draft option — do NOT switch live basis or authorize.
        DB::table('level_income_rule_versions')
            ->where('version_code', 'proposed_activation_unlock_v1')
            ->where('status', 'draft')
            ->where('posting_authorized', false)
            ->update([
                'combined_joining_enabled' => true,
                'combined_purchase_enabled' => true,
                'combined_purchase_scope' => 'approved_package_directs',
                'combined_repeat_purchase_mode' => 'unique_directs',
                'combined_double_credit_policy' => 'prevent_overlap',
                'combined_joining_unit_value' => 1,
                'combined_purchase_unit_value' => 1,
                // Keep single-basis field until client explicitly selects combined basis + formula.
                'change_note' => 'Combined joining+purchase settings staged (disabled until basis=combined_joining_and_purchases and BD approves formula). Grandfathering remains available. Not posting-authorized.',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('level_income_rule_versions')) {
            return;
        }

        Schema::table('level_income_rule_versions', function (Blueprint $table): void {
            foreach ([
                'combined_purchase_unit_value',
                'combined_joining_unit_value',
                'combined_double_credit_policy',
                'combined_repeat_purchase_mode',
                'combined_purchase_scope',
                'combined_purchase_enabled',
                'combined_joining_enabled',
            ] as $column) {
                if (Schema::hasColumn('level_income_rule_versions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
