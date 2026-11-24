<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tour_interest_requests', function (Blueprint $table) {
            $table->id();
            $table->string('member_id', 32);
            $table->string('status', 32)->default('pending');
            // '1' while pending/contacted/confirmed; NULL when completed/cancelled.
            $table->char('active_marker', 1)->nullable();
            $table->timestamp('submitted_at');
            $table->timestamp('contacted_at')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->unsignedBigInteger('assigned_admin_id')->nullable();
            $table->text('admin_notes')->nullable();
            $table->string('source', 32)->default('member_dashboard');
            $table->timestamps();

            $table->unique(['member_id', 'active_marker'], 'tour_req_member_active_uq');
            $table->index(['status', 'submitted_at'], 'tour_req_status_submitted_idx');
            $table->index('member_id', 'tour_req_member_idx');
            $table->foreign('assigned_admin_id', 'tour_req_assigned_admin_fk')
                ->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('tour_interest_request_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tour_interest_request_id')->constrained('tour_interest_requests')->cascadeOnDelete();
            $table->string('action', 64);
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32)->nullable();
            $table->text('note')->nullable();
            $table->string('actor_type', 16);
            $table->string('actor_member_id', 32)->nullable();
            $table->unsignedBigInteger('actor_admin_id')->nullable();
            $table->timestamps();

            $table->index(['tour_interest_request_id', 'id'], 'tour_req_events_request_idx');
            $table->foreign('actor_admin_id', 'tour_req_events_admin_fk')->references('id')->on('users')->nullOnDelete();
        });

        if (! Schema::hasTable('support_notifications')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'sqlite') {
            $this->sqliteRebuildNotifications();

            return;
        }

        if (! Schema::hasColumn('support_notifications', 'tour_interest_request_id')) {
            Schema::table('support_notifications', function (Blueprint $table) {
                $table->unsignedBigInteger('tour_interest_request_id')->nullable()->after('support_ticket_id');
            });
        }

        if ($driver === 'mysql') {
            try {
                DB::statement('ALTER TABLE support_notifications MODIFY support_ticket_id BIGINT UNSIGNED NULL');
            } catch (\Throwable) {
            }
        }

        Schema::table('support_notifications', function (Blueprint $table) {
            $table->foreign('tour_interest_request_id', 'stn_tour_request_fk')
                ->references('id')->on('tour_interest_requests')->nullOnDelete();
            $table->index('tour_interest_request_id', 'stn_tour_request_idx');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('support_notifications') && Schema::hasColumn('support_notifications', 'tour_interest_request_id')) {
            Schema::table('support_notifications', function (Blueprint $table) {
                try {
                    $table->dropForeign('stn_tour_request_fk');
                } catch (\Throwable) {
                }
                try {
                    $table->dropIndex('stn_tour_request_idx');
                } catch (\Throwable) {
                }
                $table->dropColumn('tour_interest_request_id');
            });
        }

        Schema::dropIfExists('tour_interest_request_events');
        Schema::dropIfExists('tour_interest_requests');
    }

    private function sqliteRebuildNotifications(): void
    {
        Schema::rename('support_notifications', 'support_notifications_old_tr');
        // SQLite index names are DB-scoped; free names held by the renamed table.
        foreach (['stn_member_read_idx', 'stn_admin_read_idx', 'stn_tour_request_idx'] as $index) {
            try {
                DB::statement('DROP INDEX IF EXISTS "'.$index.'"');
            } catch (\Throwable) {
            }
        }

        Schema::create('support_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('audience', 20);
            $table->string('member_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('support_ticket_id')->nullable();
            $table->unsignedBigInteger('tour_interest_request_id')->nullable();
            $table->string('summary', 180);
            $table->string('email_status', 20);
            $table->timestamp('read_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['audience', 'member_id', 'read_at'], 'stn_member_read_idx');
            $table->index(['audience', 'user_id', 'read_at'], 'stn_admin_read_idx');
            $table->index('tour_interest_request_id', 'stn_tour_request_idx');
        });

        foreach (DB::table('support_notifications_old_tr')->get() as $row) {
            DB::table('support_notifications')->insert([
                'id' => $row->id,
                'audience' => $row->audience,
                'member_id' => $row->member_id,
                'user_id' => $row->user_id,
                'support_ticket_id' => $row->support_ticket_id,
                'tour_interest_request_id' => null,
                'summary' => $row->summary,
                'email_status' => $row->email_status,
                'read_at' => $row->read_at,
                'created_at' => $row->created_at,
            ]);
        }

        Schema::drop('support_notifications_old_tr');
    }
};
