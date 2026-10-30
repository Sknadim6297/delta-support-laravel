<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('financial_controls')) {
            return;
        }

        Schema::table('financial_controls', function (Blueprint $table): void {
            if (! Schema::hasColumn('financial_controls', 'financial_automation_armed')) {
                $table->boolean('financial_automation_armed')->default(false);
            }
            if (! Schema::hasColumn('financial_controls', 'financial_emergency_stop')) {
                $table->boolean('financial_emergency_stop')->default(false);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('financial_controls')) {
            return;
        }

        Schema::table('financial_controls', function (Blueprint $table): void {
            if (Schema::hasColumn('financial_controls', 'financial_emergency_stop')) {
                $table->dropColumn('financial_emergency_stop');
            }
            if (Schema::hasColumn('financial_controls', 'financial_automation_armed')) {
                $table->dropColumn('financial_automation_armed');
            }
        });
    }
};
