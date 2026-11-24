<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Versioned Level Unlock qualification settings.
 * Does not authorize posting. Does not change wallet/commission history.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('level_income_rule_versions')) {
            return;
        }

        Schema::table('level_income_rule_versions', function (Blueprint $table): void {
            if (! Schema::hasColumn('level_income_rule_versions', 'direct_qualification_basis')) {
                $table->string('direct_qualification_basis', 64)
                    ->default('income_eligible_directs')
                    ->after('applicability');
            }
            if (! Schema::hasColumn('level_income_rule_versions', 'tour_only_qualifies_for_unlock')) {
                $table->boolean('tour_only_qualifies_for_unlock')
                    ->default(false)
                    ->after('direct_qualification_basis');
            }
        });

        // Legacy published baseline stays income-eligible unique directs, Tour Only off.
        DB::table('level_income_rule_versions')
            ->where('version_code', 'legacy_live_v1')
            ->update([
                'direct_qualification_basis' => 'income_eligible_directs',
                'tour_only_qualifies_for_unlock' => false,
                'updated_at' => now(),
            ]);

        // Proposed draft: document preferred bands; qualification still editable before authorize.
        DB::table('level_income_rule_versions')
            ->where('version_code', 'proposed_activation_unlock_v1')
            ->where('status', 'draft')
            ->where('posting_authorized', false)
            ->update([
                'direct_qualification_basis' => 'approved_package_directs',
                'tour_only_qualifies_for_unlock' => true,
                'notes' => 'PROPOSED: activation unlocks 1–4 at 0 directs. Direct qualification draft default = unique directs with any approved package (incl. Tour Only for unlock only). Not posting-authorized. ROI/commission generation remain separate from unlock qualification.',
                'change_note' => 'Draft qualification settings — publish + authorize still required for live posting.',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('level_income_rule_versions')) {
            return;
        }

        Schema::table('level_income_rule_versions', function (Blueprint $table): void {
            if (Schema::hasColumn('level_income_rule_versions', 'tour_only_qualifies_for_unlock')) {
                $table->dropColumn('tour_only_qualifies_for_unlock');
            }
            if (Schema::hasColumn('level_income_rule_versions', 'direct_qualification_basis')) {
                $table->dropColumn('direct_qualification_basis');
            }
        });
    }
};
