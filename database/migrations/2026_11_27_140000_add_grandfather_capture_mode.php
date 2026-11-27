<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Capture mode metadata for Level Unlock grandfather baselines.
 * Does not authorize posting or mutate financial history.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('level_income_rule_versions')) {
            return;
        }

        Schema::table('level_income_rule_versions', function (Blueprint $table): void {
            if (! Schema::hasColumn('level_income_rule_versions', 'grandfather_capture_mode')) {
                $table->string('grandfather_capture_mode', 64)
                    ->nullable()
                    ->after('grandfather_captured_by');
            }
            if (! Schema::hasColumn('level_income_rule_versions', 'grandfather_capture_note')) {
                $table->string('grandfather_capture_note', 500)
                    ->nullable()
                    ->after('grandfather_capture_mode');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('level_income_rule_versions')) {
            return;
        }

        Schema::table('level_income_rule_versions', function (Blueprint $table): void {
            foreach (['grandfather_capture_note', 'grandfather_capture_mode'] as $column) {
                if (Schema::hasColumn('level_income_rule_versions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
