<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('withdrawal_requests') || Schema::hasColumn('withdrawal_requests', 'paid_at')) {
            return;
        }

        Schema::table('withdrawal_requests', function (Blueprint $table) {
            $table->timestamp('paid_at')->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('withdrawal_requests') || ! Schema::hasColumn('withdrawal_requests', 'paid_at')) {
            return;
        }

        Schema::table('withdrawal_requests', function (Blueprint $table) {
            $table->dropColumn('paid_at');
        });
    }
};
