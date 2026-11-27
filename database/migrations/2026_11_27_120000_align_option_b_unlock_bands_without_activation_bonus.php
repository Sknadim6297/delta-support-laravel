<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Option B unlock ladder:
 * Unlocked = min(20, 4 × (direct_joining + approved_personal_purchases))
 * for personally activated members. No activation-only bonus at metric 0.
 *
 * Seeded proposed bands previously used min_qualifying_directs=0 → levels 1–4
 * (activation bonus). Align to metric thresholds 1/2/3/4/5 → 4/8/12/16/20.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('level_income_rule_versions') || ! Schema::hasTable('level_income_unlock_bands')) {
            return;
        }

        $versionId = DB::table('level_income_rule_versions')
            ->where('version_code', 'proposed_activation_unlock_v1')
            ->value('id');

        if ($versionId === null) {
            return;
        }

        $bands = [
            ['sort' => 1, 'start' => 1, 'end' => 4, 'directs' => 1],
            ['sort' => 2, 'start' => 5, 'end' => 8, 'directs' => 2],
            ['sort' => 3, 'start' => 9, 'end' => 12, 'directs' => 3],
            ['sort' => 4, 'start' => 13, 'end' => 16, 'directs' => 4],
            ['sort' => 5, 'start' => 17, 'end' => 20, 'directs' => 5],
        ];

        $now = now();
        foreach ($bands as $band) {
            $updated = DB::table('level_income_unlock_bands')
                ->where('level_income_rule_version_id', $versionId)
                ->where('sort_order', $band['sort'])
                ->update([
                    'start_level' => $band['start'],
                    'end_level' => $band['end'],
                    'min_qualifying_directs' => $band['directs'],
                    'requires_personal_activation' => true,
                    'updated_at' => $now,
                ]);

            if ($updated === 0) {
                DB::table('level_income_unlock_bands')->insert([
                    'level_income_rule_version_id' => $versionId,
                    'sort_order' => $band['sort'],
                    'start_level' => $band['start'],
                    'end_level' => $band['end'],
                    'min_qualifying_directs' => $band['directs'],
                    'requires_personal_activation' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        // Drop any extra proposed bands outside the Option B five-band ladder.
        DB::table('level_income_unlock_bands')
            ->where('level_income_rule_version_id', $versionId)
            ->where('sort_order', '>', 5)
            ->delete();

        DB::table('level_income_rule_versions')
            ->where('id', $versionId)
            ->update([
                'label' => 'Option B: combined joining + personal purchases (4 levels each, max 20)',
                'direct_qualification_basis' => 'combined_joining_and_purchase',
                'tour_only_qualifies_for_unlock' => true,
                'combined_joining_enabled' => true,
                'combined_purchase_enabled' => true,
                'combined_purchase_scope' => 'personal_approved_purchases',
                'combined_repeat_purchase_mode' => 'each_approved_purchase',
                'combined_double_credit_policy' => 'allow_sum',
                'combined_joining_unit_value' => 1,
                'combined_purchase_unit_value' => 1,
                'notes' => 'Option B: Unlocked = min(20, 4 × (registered directs + approved personal purchases)) for personally activated members. No activation-only bonus. Pending/rejected purchases excluded. existing_prospective + grandfathering required for go-live.',
                'change_note' => 'Aligned unlock bands to Option B thresholds (metric 1/2/3/4/5 → levels 4/8/12/16/20). Removed metric-0 activation bonus. Combined personal-purchase scope enabled.',
                'updated_at' => $now,
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('level_income_rule_versions') || ! Schema::hasTable('level_income_unlock_bands')) {
            return;
        }

        $versionId = DB::table('level_income_rule_versions')
            ->where('version_code', 'proposed_activation_unlock_v1')
            ->value('id');

        if ($versionId === null) {
            return;
        }

        $bands = [
            ['sort' => 1, 'start' => 1, 'end' => 4, 'directs' => 0],
            ['sort' => 2, 'start' => 5, 'end' => 8, 'directs' => 1],
            ['sort' => 3, 'start' => 9, 'end' => 12, 'directs' => 2],
            ['sort' => 4, 'start' => 13, 'end' => 16, 'directs' => 3],
            ['sort' => 5, 'start' => 17, 'end' => 20, 'directs' => 4],
        ];

        $now = now();
        foreach ($bands as $band) {
            DB::table('level_income_unlock_bands')
                ->where('level_income_rule_version_id', $versionId)
                ->where('sort_order', $band['sort'])
                ->update([
                    'start_level' => $band['start'],
                    'end_level' => $band['end'],
                    'min_qualifying_directs' => $band['directs'],
                    'requires_personal_activation' => true,
                    'updated_at' => $now,
                ]);
        }
    }
};
