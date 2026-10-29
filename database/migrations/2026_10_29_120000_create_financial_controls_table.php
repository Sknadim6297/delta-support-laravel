<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('financial_controls')) {
            return;
        }

        Schema::create('financial_controls', function (Blueprint $table) {
            $table->id();
            $table->boolean('roi_calculation_enabled')->default(false);
            $table->boolean('roi_wallet_crediting_enabled')->default(false);
            $table->boolean('level_calculation_enabled')->default(false);
            $table->boolean('level_wallet_crediting_enabled')->default(false);
            $table->boolean('rewards_evaluation_enabled')->default(false);
            $table->boolean('withdrawal_requests_enabled')->default(false);
            $table->boolean('withdrawal_approval_enabled')->default(false);
            $table->boolean('external_payouts_enabled')->default(false);
            $table->boolean('daily_financial_cycle_enabled')->default(false);
            $table->timestamps();
        });

        DB::table('financial_controls')->insert([
            'roi_calculation_enabled' => false,
            'roi_wallet_crediting_enabled' => false,
            'level_calculation_enabled' => false,
            'level_wallet_crediting_enabled' => false,
            'rewards_evaluation_enabled' => false,
            'withdrawal_requests_enabled' => false,
            'withdrawal_approval_enabled' => false,
            'external_payouts_enabled' => false,
            'daily_financial_cycle_enabled' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_controls');
    }
};
