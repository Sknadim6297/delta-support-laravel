<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_security_pin_resets', function (Blueprint $table) {
            $table->id();
            $table->string('member_id')->index();
            $table->string('otp_hash');
            $table->timestamp('expires_at');
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('invalidated_at')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamps();

            $table->index(['member_id', 'consumed_at', 'invalidated_at'], 'msr_member_open_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_security_pin_resets');
    }
};
