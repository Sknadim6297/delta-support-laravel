<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('level_income_rule_versions', function (Blueprint $table) {
            $table->id();
            $table->string('version_code', 64)->unique();
            $table->string('label', 160);
            $table->string('status', 32)->default('draft'); // draft|published|superseded
            $table->boolean('posting_authorized')->default(false);
            $table->string('applicability', 64)->default('unspecified'); // unspecified|future_purchases_only|existing_prospective
            $table->date('effective_from')->nullable();
            $table->text('notes')->nullable();
            $table->text('change_note')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('published_by')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'posting_authorized'], 'li_rule_versions_status_auth_idx');
            $table->foreign('created_by', 'li_rule_versions_created_by_fk')->references('id')->on('users')->nullOnDelete();
            $table->foreign('published_by', 'li_rule_versions_published_by_fk')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('level_income_unlock_bands', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('level_income_rule_version_id');
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->unsignedTinyInteger('start_level');
            $table->unsignedTinyInteger('end_level');
            $table->unsignedSmallInteger('min_qualifying_directs')->default(0);
            $table->boolean('requires_personal_activation')->default(true);
            $table->timestamps();

            $table->foreign('level_income_rule_version_id', 'li_unlock_bands_version_fk')
                ->references('id')->on('level_income_rule_versions')->cascadeOnDelete();
            $table->index(['level_income_rule_version_id', 'sort_order'], 'li_unlock_bands_version_sort_idx');
        });

        Schema::create('level_income_commission_rates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('level_income_rule_version_id');
            $table->unsignedTinyInteger('level');
            $table->decimal('percentage', 10, 4);
            $table->timestamps();

            $table->foreign('level_income_rule_version_id', 'li_commission_rates_version_fk')
                ->references('id')->on('level_income_rule_versions')->cascadeOnDelete();
            $table->unique(['level_income_rule_version_id', 'level'], 'li_commission_rates_version_level_uq');
        });

        Schema::create('level_income_rule_audits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('level_income_rule_version_id')->nullable();
            $table->string('action', 64);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->json('before_json')->nullable();
            $table->json('after_json')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->foreign('level_income_rule_version_id', 'li_rule_audits_version_fk')
                ->references('id')->on('level_income_rule_versions')->nullOnDelete();
            $table->foreign('actor_id', 'li_rule_audits_actor_fk')->references('id')->on('users')->nullOnDelete();
            $table->index(['action', 'created_at'], 'li_rule_audits_action_created_idx');
        });

        $now = now();
        $legacyId = DB::table('level_income_rule_versions')->insertGetId([
            'version_code' => 'legacy_live_v1',
            'label' => 'Legacy live unlock & rates (baseline)',
            'status' => 'published',
            'posting_authorized' => false,
            'applicability' => 'unspecified',
            'effective_from' => null,
            'notes' => 'Mirrors hard-coded ReferralLevelEligibility + CommissionPlan::RATE_BY_LEVEL. posting_authorized=false keeps financial:daily on code defaults until explicit authorization.',
            'change_note' => 'Seeded baseline from audit.',
            'created_by' => null,
            'published_by' => null,
            'published_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Legacy live mapping: 0 directs → 0 levels; 1→4 … ≥5→20 (activation required on every band).
        $legacyBands = [
            ['sort' => 1, 'start' => 1, 'end' => 4, 'directs' => 1, 'activation' => true],
            ['sort' => 2, 'start' => 5, 'end' => 8, 'directs' => 2, 'activation' => true],
            ['sort' => 3, 'start' => 9, 'end' => 12, 'directs' => 3, 'activation' => true],
            ['sort' => 4, 'start' => 13, 'end' => 16, 'directs' => 4, 'activation' => true],
            ['sort' => 5, 'start' => 17, 'end' => 20, 'directs' => 5, 'activation' => true],
        ];
        foreach ($legacyBands as $band) {
            DB::table('level_income_unlock_bands')->insert([
                'level_income_rule_version_id' => $legacyId,
                'sort_order' => $band['sort'],
                'start_level' => $band['start'],
                'end_level' => $band['end'],
                'min_qualifying_directs' => $band['directs'],
                'requires_personal_activation' => $band['activation'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $rates = [
            1 => '25.0000', 2 => '12.5000', 3 => '7.5000', 4 => '5.0000',
            5 => '2.5000', 6 => '2.5000', 7 => '2.5000', 8 => '2.5000',
            9 => '2.5000', 10 => '2.5000', 11 => '2.5000', 12 => '2.5000',
            13 => '2.5000', 14 => '2.5000', 15 => '2.5000', 16 => '2.5000',
            17 => '2.5000', 18 => '5.0000', 19 => '5.0000', 20 => '7.5000',
        ];
        foreach ($rates as $level => $percentage) {
            DB::table('level_income_commission_rates')->insert([
                'level_income_rule_version_id' => $legacyId,
                'level' => $level,
                'percentage' => $percentage,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $proposedId = DB::table('level_income_rule_versions')->insertGetId([
            'version_code' => 'proposed_activation_unlock_v1',
            'label' => 'Proposed: activation unlocks levels 1–4',
            'status' => 'draft',
            'posting_authorized' => false,
            'applicability' => 'future_purchases_only',
            'effective_from' => null,
            'notes' => 'PROPOSED defaults from business request. Recommended applicability: future_purchases_only. Not authorized for financial:daily. Active member with 0 directs → levels 1–4 under new rules only after publish+authorize and only for purchases approved on/after effective_from.',
            'change_note' => 'Draft only — default applicability set to future_purchases_only; wait for written approval before publish/authorize.',
            'created_by' => null,
            'published_by' => null,
            'published_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $proposedBands = [
            ['sort' => 1, 'start' => 1, 'end' => 4, 'directs' => 0, 'activation' => true],
            ['sort' => 2, 'start' => 5, 'end' => 8, 'directs' => 1, 'activation' => true],
            ['sort' => 3, 'start' => 9, 'end' => 12, 'directs' => 2, 'activation' => true],
            ['sort' => 4, 'start' => 13, 'end' => 16, 'directs' => 3, 'activation' => true],
            ['sort' => 5, 'start' => 17, 'end' => 20, 'directs' => 4, 'activation' => true],
        ];
        foreach ($proposedBands as $band) {
            DB::table('level_income_unlock_bands')->insert([
                'level_income_rule_version_id' => $proposedId,
                'sort_order' => $band['sort'],
                'start_level' => $band['start'],
                'end_level' => $band['end'],
                'min_qualifying_directs' => $band['directs'],
                'requires_personal_activation' => $band['activation'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        foreach ($rates as $level => $percentage) {
            DB::table('level_income_commission_rates')->insert([
                'level_income_rule_version_id' => $proposedId,
                'level' => $level,
                'percentage' => $percentage,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::table('level_income_rule_audits')->insert([
            'level_income_rule_version_id' => $legacyId,
            'action' => 'seeded_legacy_baseline',
            'actor_id' => null,
            'before_json' => null,
            'after_json' => json_encode(['version_code' => 'legacy_live_v1', 'posting_authorized' => false]),
            'ip_address' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('level_income_rule_audits');
        Schema::dropIfExists('level_income_commission_rates');
        Schema::dropIfExists('level_income_unlock_bands');
        Schema::dropIfExists('level_income_rule_versions');
    }
};
