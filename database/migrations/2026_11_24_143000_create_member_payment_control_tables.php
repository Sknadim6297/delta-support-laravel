<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_payment_controls', function (Blueprint $table) {
            $table->id();
            $table->string('member_id', 32)->unique();
            // Master overrides without destroying preferences.
            $table->boolean('master_enabled')->default(true);
            $table->boolean('roi_preference')->default(true);
            $table->boolean('level_preference')->default(true);
            $table->string('status', 32)->default('active'); // active|partial|stopped
            $table->text('last_reason')->nullable();
            $table->date('last_effective_from')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('updated_by', 'mpc_controls_updated_by_fk')->references('id')->on('users')->nullOnDelete();
            $table->index('status', 'mpc_controls_status_idx');
        });

        // Immutable dated blocks used by financial:daily / recovery when integration is authorized.
        Schema::create('member_payment_blocks', function (Blueprint $table) {
            $table->id();
            $table->string('member_id', 32);
            $table->string('income_type', 16); // roi|level
            $table->date('start_date'); // inclusive
            $table->date('end_date')->nullable(); // exclusive; null = open-ended
            $table->unsignedBigInteger('opened_by_event_id')->nullable();
            $table->unsignedBigInteger('closed_by_event_id')->nullable();
            $table->timestamps();

            $table->index(['member_id', 'income_type', 'start_date'], 'mpc_blocks_member_type_start_idx');
            $table->index(['member_id', 'income_type', 'end_date'], 'mpc_blocks_member_type_end_idx');
        });

        Schema::create('member_payment_control_events', function (Blueprint $table) {
            $table->id();
            $table->string('member_id', 32);
            $table->string('action', 64);
            $table->text('reason');
            $table->date('effective_from');
            $table->json('before_json')->nullable();
            $table->json('after_json')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->foreign('actor_id', 'mpc_events_actor_fk')->references('id')->on('users')->nullOnDelete();
            $table->index(['member_id', 'created_at'], 'mpc_events_member_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_payment_control_events');
        Schema::dropIfExists('member_payment_blocks');
        Schema::dropIfExists('member_payment_controls');
    }
};
