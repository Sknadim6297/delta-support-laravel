<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('support_tickets')) {
            Schema::create('support_tickets', function (Blueprint $table) {
                $table->id();
                $table->string('reference')->unique();
                $table->string('member_id');
                $table->string('subject', 150);
                $table->string('category', 40);
                $table->string('priority', 20);
                $table->string('status', 30);
                $table->uuid('idempotency_key')->unique();
                $table->timestamp('closed_at')->nullable();
                $table->string('closed_by')->nullable();
                $table->timestamps();

                $table->foreign('member_id')->references('member_id')->on('members')->restrictOnDelete();
                $table->index(['member_id', 'status'], 'stk_member_status_idx');
                $table->index(['status', 'created_at'], 'stk_status_created_idx');
                $table->index(['category', 'priority'], 'stk_category_priority_idx');
            });
        }

        if (! Schema::hasTable('support_messages')) {
            Schema::create('support_messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('support_ticket_id')->constrained('support_tickets')->restrictOnDelete();
                $table->string('sender_role', 20);
                $table->string('sender_member_id')->nullable();
                $table->foreignId('sender_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->text('body');
                $table->uuid('idempotency_key')->unique();
                $table->timestamp('created_at')->useCurrent();

                $table->foreign('sender_member_id')->references('member_id')->on('members')->nullOnDelete();
                $table->index(['support_ticket_id', 'id'], 'stm_ticket_id_idx');
            });
        }

        if (! Schema::hasTable('support_attachments')) {
            Schema::create('support_attachments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('support_message_id')->constrained('support_messages')->restrictOnDelete();
                $table->string('disk', 20)->default('local');
                $table->string('path');
                $table->string('original_name', 120);
                $table->string('mime', 80);
                $table->unsignedInteger('size');
                $table->timestamp('created_at')->useCurrent();

                $table->unique('path', 'sta_path_uidx');
            });
        }

        if (! Schema::hasTable('support_notifications')) {
            Schema::create('support_notifications', function (Blueprint $table) {
                $table->id();
                $table->string('audience', 20);
                $table->string('member_id')->nullable();
                $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
                $table->foreignId('support_ticket_id')->constrained('support_tickets')->cascadeOnDelete();
                $table->string('summary', 180);
                $table->string('email_status', 20);
                $table->timestamp('read_at')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->foreign('member_id')->references('member_id')->on('members')->cascadeOnDelete();
                $table->index(['audience', 'member_id', 'read_at'], 'stn_member_read_idx');
                $table->index(['audience', 'user_id', 'read_at'], 'stn_admin_read_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('support_notifications');
        Schema::dropIfExists('support_attachments');
        Schema::dropIfExists('support_messages');
        Schema::dropIfExists('support_tickets');
    }
};
