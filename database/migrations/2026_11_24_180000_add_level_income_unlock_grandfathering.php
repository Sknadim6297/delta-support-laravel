<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Versioned grandfathering for existing Level Unlock entitlements.
 * Does not authorize posting. Does not mutate wallets or accruals.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('level_income_rule_versions')) {
            Schema::table('level_income_rule_versions', function (Blueprint $table): void {
                if (! Schema::hasColumn('level_income_rule_versions', 'grandfather_existing_unlock')) {
                    $table->boolean('grandfather_existing_unlock')
                        ->default(false)
                        ->after('tour_only_qualifies_for_unlock');
                }
                if (! Schema::hasColumn('level_income_rule_versions', 'grandfather_transition_date')) {
                    $table->date('grandfather_transition_date')
                        ->nullable()
                        ->after('grandfather_existing_unlock');
                }
                if (! Schema::hasColumn('level_income_rule_versions', 'grandfather_captured_at')) {
                    $table->timestamp('grandfather_captured_at')
                        ->nullable()
                        ->after('grandfather_transition_date');
                }
                if (! Schema::hasColumn('level_income_rule_versions', 'grandfather_captured_by')) {
                    $table->unsignedBigInteger('grandfather_captured_by')->nullable()->after('grandfather_captured_at');
                    $table->foreign('grandfather_captured_by', 'li_gf_captured_by_fk')
                        ->references('id')
                        ->on('users')
                        ->nullOnDelete();
                }
            });

            DB::table('level_income_rule_versions')
                ->where('version_code', 'proposed_activation_unlock_v1')
                ->where('status', 'draft')
                ->where('posting_authorized', false)
                ->update([
                    'grandfather_existing_unlock' => true,
                    'change_note' => 'Draft recommends grandfather_existing_unlock — capture baseline at transition date before authorize.',
                    'updated_at' => now(),
                ]);
        }

        if (! Schema::hasTable('level_income_unlock_grandfather_snapshots')) {
            Schema::create('level_income_unlock_grandfather_snapshots', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('level_income_rule_version_id');
                $table->string('member_id', 32);
                $table->unsignedTinyInteger('legacy_unlocked_levels')->default(0);
                $table->unsignedInteger('income_eligible_direct_count')->default(0);
                $table->unsignedInteger('registered_direct_count')->default(0);
                $table->boolean('personally_activated_legacy')->default(false);
                $table->date('transition_date');
                $table->timestamp('captured_at');
                $table->unsignedBigInteger('captured_by')->nullable();
                $table->timestamps();

                $table->foreign('level_income_rule_version_id', 'li_gf_snap_version_fk')
                    ->references('id')
                    ->on('level_income_rule_versions')
                    ->cascadeOnDelete();
                $table->foreign('captured_by', 'li_gf_snap_captured_by_fk')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
                $table->unique(
                    ['level_income_rule_version_id', 'member_id'],
                    'li_gf_snap_version_member_uq'
                );
                $table->index('member_id', 'li_gf_snap_member_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('level_income_unlock_grandfather_snapshots');

        if (! Schema::hasTable('level_income_rule_versions')) {
            return;
        }

        Schema::table('level_income_rule_versions', function (Blueprint $table): void {
            if (Schema::hasColumn('level_income_rule_versions', 'grandfather_captured_by')) {
                $table->dropForeign('li_gf_captured_by_fk');
                $table->dropColumn('grandfather_captured_by');
            }
            foreach (['grandfather_captured_at', 'grandfather_transition_date', 'grandfather_existing_unlock'] as $column) {
                if (Schema::hasColumn('level_income_rule_versions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
