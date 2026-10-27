<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['packages', 'package_purchases', 'invoices'] as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'denomination')) {
                Schema::table($table, function (Blueprint $blueprint): void {
                    $blueprint->string('denomination', 8)->default('USD');
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['packages', 'package_purchases', 'invoices'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'denomination')) {
                Schema::table($table, function (Blueprint $blueprint): void {
                    $blueprint->dropColumn('denomination');
                });
            }
        }
    }
};
