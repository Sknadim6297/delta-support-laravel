<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('package_purchases', function (Blueprint $table): void {
            $table->index('submitted_at', 'package_purchases_submitted_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('package_purchases', function (Blueprint $table): void {
            $table->dropIndex('package_purchases_submitted_at_index');
        });
    }
};
