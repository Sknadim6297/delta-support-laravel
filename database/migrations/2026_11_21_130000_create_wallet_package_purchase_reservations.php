<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('package_purchases', function (Blueprint $table): void {
            if (! Schema::hasColumn('package_purchases', 'funding_source')) {
                $table->string('funding_source', 32)->nullable()->after('payment_method');
            }
            if (! Schema::hasColumn('package_purchases', 'funding_wallet')) {
                $table->string('funding_wallet', 20)->nullable()->after('funding_source');
            }
        });

        if (! Schema::hasTable('package_purchase_reservations')) {
            Schema::create('package_purchase_reservations', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('package_purchase_id')->unique()->constrained('package_purchases')->restrictOnDelete();
                $table->string('member_id');
                $table->foreign('member_id')->references('member_id')->on('members')->restrictOnDelete();
                $table->enum('wallet', ['working']);
                $table->decimal('reserved_amount', 20, 4);
                $table->enum('status', ['active', 'settled', 'released'])->default('active');
                $table->string('reservation_reference')->unique();
                $table->uuid('idempotency_key')->unique();
                $table->timestamp('reserved_at');
                $table->timestamp('settled_at')->nullable();
                $table->timestamp('released_at')->nullable();
                $table->timestamps();

                $table->index(['member_id', 'wallet', 'status'], 'ppr_member_wallet_status_idx');
                $table->index(['status', 'reserved_at'], 'ppr_status_reserved_idx');
            });
        }

        if (! Schema::hasIndex('package_purchases', 'package_purchases_funding_source_status_index')) {
            Schema::table('package_purchases', function (Blueprint $table): void {
                $table->index(['funding_source', 'status'], 'package_purchases_funding_source_status_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('package_purchase_reservations');
        Schema::table('package_purchases', function (Blueprint $table): void {
            if (Schema::hasIndex('package_purchases', 'package_purchases_funding_source_status_index')) {
                $table->dropIndex('package_purchases_funding_source_status_index');
            }
            $columns = array_values(array_filter([
                Schema::hasColumn('package_purchases', 'funding_wallet') ? 'funding_wallet' : null,
                Schema::hasColumn('package_purchases', 'funding_source') ? 'funding_source' : null,
            ]));
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
