<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member_transfer_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('member_transfer_settings', 'fee_type')) {
                $table->string('fee_type', 20)->nullable()->after('fee_amount');
            }
            if (! Schema::hasColumn('member_transfer_settings', 'fee_value')) {
                $table->decimal('fee_value', 20, 4)->nullable()->after('fee_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('member_transfer_settings', function (Blueprint $table): void {
            if (Schema::hasColumn('member_transfer_settings', 'fee_value')) {
                $table->dropColumn('fee_value');
            }
            if (Schema::hasColumn('member_transfer_settings', 'fee_type')) {
                $table->dropColumn('fee_type');
            }
        });
    }
};
