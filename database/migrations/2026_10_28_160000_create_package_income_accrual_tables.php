<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('package_roi_accruals', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->string('idempotency_key')->unique();
            $table->foreignId('package_purchase_id')->constrained('package_purchases');
            $table->string('member_id');
            $table->string('member_name');
            $table->string('package_code');
            $table->string('package_name');
            $table->date('business_date');
            $table->decimal('amount', 16, 4);
            $table->string('status', 20)->default('accrued');
            $table->timestamps();

            $table->unique(['package_purchase_id', 'business_date'], 'package_roi_purchase_date_unique');
            $table->index(['member_id', 'business_date']);
        });

        Schema::create('package_level_accruals', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->string('idempotency_key')->unique();
            $table->foreignId('package_purchase_id')->constrained('package_purchases');
            $table->string('member_id');
            $table->string('member_name');
            $table->string('from_member_id');
            $table->string('from_member_name');
            $table->unsignedTinyInteger('level');
            $table->string('package_code');
            $table->string('package_name');
            $table->date('business_date');
            $table->decimal('pool_amount', 16, 4);
            $table->decimal('rate_percentage', 8, 4);
            $table->decimal('amount', 16, 4);
            $table->string('status', 20)->default('accrued');
            $table->timestamps();

            $table->unique(['package_purchase_id', 'member_id', 'business_date'], 'package_level_purchase_member_date_unique');
            $table->index(['member_id', 'business_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_level_accruals');
        Schema::dropIfExists('package_roi_accruals');
    }
};
