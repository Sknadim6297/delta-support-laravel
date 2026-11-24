<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Recommend future_purchases_only as the default applicability on the proposed draft.
 * Does not publish or authorize posting. Does not change financial history.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('level_income_rule_versions')) {
            return;
        }

        DB::table('level_income_rule_versions')
            ->where('version_code', 'proposed_activation_unlock_v1')
            ->where('status', 'draft')
            ->where('posting_authorized', false)
            ->update([
                'applicability' => 'future_purchases_only',
                'notes' => 'PROPOSED defaults from business request. Recommended applicability: future_purchases_only. Not authorized for financial:daily. Active member with 0 directs → levels 1–4 under new rules only after publish+authorize and only for purchases approved on/after effective_from.',
                'change_note' => 'Draft only — default applicability set to future_purchases_only; wait for written approval before publish/authorize.',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('level_income_rule_versions')) {
            return;
        }

        DB::table('level_income_rule_versions')
            ->where('version_code', 'proposed_activation_unlock_v1')
            ->where('status', 'draft')
            ->where('posting_authorized', false)
            ->update([
                'applicability' => 'unspecified',
                'updated_at' => now(),
            ]);
    }
};
