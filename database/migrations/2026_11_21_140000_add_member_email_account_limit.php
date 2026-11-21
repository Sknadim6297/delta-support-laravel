<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('members', 'normalized_email')) {
            Schema::table('members', function (Blueprint $table): void {
                $table->string('normalized_email', 255)->nullable()->after('email');
            });
        }

        DB::table('members')
            ->select(['id', 'email'])
            ->orderBy('id')
            ->chunkById(500, function ($members): void {
                foreach ($members as $member) {
                    DB::table('members')->where('id', $member->id)->update([
                        'normalized_email' => mb_strtolower(trim((string) $member->email), 'UTF-8'),
                    ]);
                }
            });

        if (Schema::hasIndex('members', 'members_email_unique')) {
            Schema::table('members', function (Blueprint $table): void {
                $table->dropUnique('members_email_unique');
            });
        }

        Schema::table('members', function (Blueprint $table): void {
            if (! Schema::hasIndex('members', 'members_normalized_email_index')) {
                $table->index('normalized_email', 'members_normalized_email_index');
            }
        });

        if (! Schema::hasTable('member_email_account_locks')) {
            Schema::create('member_email_account_locks', function (Blueprint $table): void {
                $table->string('normalized_email', 255)->primary();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('member_email_account_locks');

        if (Schema::hasIndex('members', 'members_normalized_email_index')) {
            Schema::table('members', function (Blueprint $table): void {
                $table->dropIndex('members_normalized_email_index');
            });
        }

        $duplicateCount = DB::table('members')
            ->selectRaw('normalized_email')
            ->whereNotNull('normalized_email')
            ->groupBy('normalized_email')
            ->havingRaw('COUNT(*) > 1')
            ->count();

        if ($duplicateCount === 0 && ! Schema::hasIndex('members', 'members_email_unique')) {
            Schema::table('members', function (Blueprint $table): void {
                $table->unique('email', 'members_email_unique');
            });
        }

        if (Schema::hasColumn('members', 'normalized_email')) {
            Schema::table('members', function (Blueprint $table): void {
                $table->dropColumn('normalized_email');
            });
        }
    }
};
