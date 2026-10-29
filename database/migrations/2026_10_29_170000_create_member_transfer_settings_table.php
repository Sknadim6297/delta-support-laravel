<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('member_transfer_settings')) {
            return;
        }

        Schema::create('member_transfer_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('member_transfers_enabled')->default(false);
            $table->decimal('minimum_amount', 20, 4)->default('1.0000');
            $table->decimal('maximum_amount', 20, 4)->default('1000.0000');
            $table->decimal('daily_limit', 20, 4)->default('5000.0000');
            $table->decimal('fee_amount', 20, 4)->default('0.0000');
            $table->string('denomination', 8)->default('USDT');
            $table->timestamps();
        });

        DB::table('member_transfer_settings')->insert([
            'member_transfers_enabled' => false,
            'minimum_amount' => '1.0000',
            'maximum_amount' => '1000.0000',
            'daily_limit' => '5000.0000',
            'fee_amount' => '0.0000',
            'denomination' => 'USDT',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('member_transfer_settings');
    }
};
