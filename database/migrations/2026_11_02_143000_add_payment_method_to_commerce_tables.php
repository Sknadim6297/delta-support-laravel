<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('package_purchases', function (Blueprint $table): void {
            if (! Schema::hasColumn('package_purchases', 'payment_method')) {
                $table->string('payment_method', 32)->nullable()->after('payment_reference');
            }
        });

        Schema::table('invoices', function (Blueprint $table): void {
            if (! Schema::hasColumn('invoices', 'payment_method')) {
                $table->string('payment_method', 32)->nullable()->after('payment_reference');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            if (Schema::hasColumn('invoices', 'payment_method')) {
                $table->dropColumn('payment_method');
            }
        });

        Schema::table('package_purchases', function (Blueprint $table): void {
            if (Schema::hasColumn('package_purchases', 'payment_method')) {
                $table->dropColumn('payment_method');
            }
        });
    }
};
