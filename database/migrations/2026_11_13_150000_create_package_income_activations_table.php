<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('package_income_activations', function (Blueprint $table) {
            $table->id();
            $table->string('package_code')->unique();
            $table->date('activation_business_date');
            $table->timestamp('activated_at');
            $table->string('activated_by')->nullable();
            $table->string('confirm_token', 64)->nullable();
            $table->json('terms_snapshot')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_income_activations');
    }
};
