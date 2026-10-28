<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('packages')) {
            return;
        }

        DB::table('packages')
            ->where(function ($query): void {
                $query->where('code', 'DEMO-COAST')
                    ->orWhere('name', 'Coastal Weekend');
            })
            ->update([
                'package_type' => 'tour_roi',
                'roi_eligible' => true,
                'level_commission_eligible' => true,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('packages')) {
            return;
        }

        DB::table('packages')
            ->where('code', 'DEMO-COAST')
            ->update([
                'package_type' => 'tour',
                'roi_eligible' => false,
                'level_commission_eligible' => false,
                'updated_at' => now(),
            ]);
    }
};
