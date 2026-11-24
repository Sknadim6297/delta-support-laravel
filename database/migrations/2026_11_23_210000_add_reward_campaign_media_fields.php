<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive campaign media fields. Does not change qualification or wallets.
 * gift_image_path remains the campaign banner/gift image.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('reward_campaigns')) {
            return;
        }

        Schema::table('reward_campaigns', function (Blueprint $table): void {
            if (! Schema::hasColumn('reward_campaigns', 'video_path')) {
                $table->string('video_path', 255)->nullable()->after('gift_image_path');
            }
            if (! Schema::hasColumn('reward_campaigns', 'promote_on_login')) {
                $table->boolean('promote_on_login')->default(true)->after('status');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('reward_campaigns')) {
            return;
        }

        Schema::table('reward_campaigns', function (Blueprint $table): void {
            if (Schema::hasColumn('reward_campaigns', 'promote_on_login')) {
                $table->dropColumn('promote_on_login');
            }
            if (Schema::hasColumn('reward_campaigns', 'video_path')) {
                $table->dropColumn('video_path');
            }
        });
    }
};
