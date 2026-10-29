<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('member_transfers')) {
            return;
        }

        Schema::create('member_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('transfer_reference')->unique();
            $table->string('sender_member_id');
            $table->string('receiver_member_id');
            $table->string('source_wallet', 20);
            $table->string('destination_wallet', 20);
            $table->decimal('amount', 20, 4);
            $table->decimal('fee_amount', 20, 4);
            $table->string('currency', 8)->default('USDT');
            $table->string('status', 20);
            $table->uuid('idempotency_key')->unique();
            $table->date('business_date');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->string('reversal_actor')->nullable();
            $table->text('reversal_reason')->nullable();
            $table->timestamps();

            $table->foreign('sender_member_id')->references('member_id')->on('members')->restrictOnDelete();
            $table->foreign('receiver_member_id')->references('member_id')->on('members')->restrictOnDelete();
            $table->index(['sender_member_id', 'business_date'], 'mtr_sender_day_idx');
            $table->index('receiver_member_id', 'mtr_receiver_idx');
            $table->index(['status', 'business_date'], 'mtr_status_day_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_transfers');
    }
};
