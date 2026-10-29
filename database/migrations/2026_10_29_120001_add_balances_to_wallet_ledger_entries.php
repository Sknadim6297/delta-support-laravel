<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('wallet_ledger_entries') || Schema::hasColumn('wallet_ledger_entries', 'opening_balance')) {
            return;
        }

        Schema::table('wallet_ledger_entries', function (Blueprint $table) {
            $table->decimal('opening_balance', 20, 4)->nullable()->after('amount');
            $table->decimal('closing_balance', 20, 4)->nullable()->after('opening_balance');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('wallet_ledger_entries') || ! Schema::hasColumn('wallet_ledger_entries', 'opening_balance')) {
            return;
        }

        Schema::table('wallet_ledger_entries', function (Blueprint $table) {
            $table->dropColumn(['opening_balance', 'closing_balance']);
        });
    }
};
