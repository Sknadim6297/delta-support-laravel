<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Canonicalize qualification basis key to singular combined_joining_and_purchase.
 * No structural change; preserves unauthorized posting posture.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('level_income_rule_versions')) {
            return;
        }

        if (! Schema::hasColumn('level_income_rule_versions', 'direct_qualification_basis')) {
            return;
        }

        DB::table('level_income_rule_versions')
            ->where('direct_qualification_basis', 'combined_joining_and_purchases')
            ->update([
                'direct_qualification_basis' => 'combined_joining_and_purchase',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('level_income_rule_versions')) {
            return;
        }

        if (! Schema::hasColumn('level_income_rule_versions', 'direct_qualification_basis')) {
            return;
        }

        DB::table('level_income_rule_versions')
            ->where('direct_qualification_basis', 'combined_joining_and_purchase')
            ->update([
                'direct_qualification_basis' => 'combined_joining_and_purchases',
                'updated_at' => now(),
            ]);
    }
};
