<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_plan_levels', function (Blueprint $table) {
            $table->id();
            $table->string('plan_code');
            $table->unsignedTinyInteger('level');
            $table->decimal('percentage', 8, 4);
            $table->boolean('is_active')->default(false);
            $table->timestamps();

            $table->unique(['plan_code', 'level']);
        });

        $now = now();
        $rows = [];
        foreach (range(1, 20) as $level) {
            $percentage = match (true) {
                $level === 1 => '25.0000',
                $level === 2 => '12.5000',
                $level === 3 => '7.5000',
                $level === 4 => '5.0000',
                $level <= 17 => '2.5000',
                $level <= 19 => '5.0000',
                default => '7.5000',
            };

            $rows[] = [
                'plan_code' => 'delta_support_v1',
                'level' => $level,
                'percentage' => $percentage,
                'is_active' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('commission_plan_levels')->insert($rows);

        Schema::create('package_income_terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_purchase_id')->unique()->constrained('package_purchases');
            $table->string('reference')->unique();
            $table->boolean('income_eligible')->nullable();
            $table->decimal('monthly_rate_percentage', 8, 4)->nullable();
            $table->decimal('monthly_amount', 16, 4)->nullable();
            $table->unsignedSmallInteger('duration_months')->nullable();
            $table->string('commission_base', 64)->nullable();
            $table->string('first_accrual_rule', 64)->nullable();
            $table->string('business_timezone', 64)->nullable();
            $table->dateTime('snapshotted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_income_terms');
        Schema::dropIfExists('commission_plan_levels');
    }
};
