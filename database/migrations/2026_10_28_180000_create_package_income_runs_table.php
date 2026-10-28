<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('package_income_runs', function (Blueprint $table) {
            $table->id();
            $table->date('business_date')->nullable();
            $table->string('status', 20);
            $table->unsignedInteger('credited_roi')->default(0);
            $table->unsignedInteger('credited_commissions')->default(0);
            $table->text('message');
            $table->timestamps();

            $table->index(['status', 'id']);
            $table->index('business_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_income_runs');
    }
};
