<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_historical_recovery_runs', function (Blueprint $table) {
            $table->id();
            $table->string('database_name');
            $table->date('from_date');
            $table->date('to_date');
            $table->json('package_codes');
            $table->string('mode', 20);
            $table->string('status', 20);
            $table->unsignedInteger('days_processed')->default(0);
            $table->unsignedInteger('roi_records')->default(0);
            $table->unsignedInteger('commission_records')->default(0);
            $table->decimal('roi_amount', 16, 4)->default(0);
            $table->decimal('commission_amount', 16, 4)->default(0);
            $table->json('day_results')->nullable();
            $table->text('message')->nullable();
            $table->string('actor')->nullable();
            $table->timestamps();

            $table->index(['from_date', 'to_date']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_historical_recovery_runs');
    }
};
