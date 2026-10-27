<?php

use App\Support\WalletLedgerDirection;
use App\Support\WithdrawalStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('withdrawal_settings')) {
            Schema::create('withdrawal_settings', function (Blueprint $table) {
                $table->id();
                $table->decimal('minimum_amount', 20, 4)->default('20.0000');
                $table->decimal('maximum_amount', 20, 4)->nullable();
                $table->string('charge_type', 20)->nullable();
                $table->decimal('charge_value', 20, 4)->nullable();
                $table->decimal('daily_limit', 20, 4)->nullable();
                $table->decimal('monthly_limit', 20, 4)->nullable();
                $table->json('eligible_wallets')->nullable();
                $table->json('supported_networks')->nullable();
                $table->text('address_validation_policy')->nullable();
                $table->text('member_cancellation_policy')->nullable();
                $table->boolean('submissions_enabled')->default(false);
                $table->string('denomination', 8)->default('USDT');
                $table->timestamps();
            });

            DB::table('withdrawal_settings')->insert([
                'minimum_amount' => '20.0000',
                'submissions_enabled' => false,
                'denomination' => 'USDT',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (! Schema::hasTable('withdrawal_requests')) {
            Schema::create('withdrawal_requests', function (Blueprint $table) {
                $table->id();
                $table->string('reference')->unique();
                $table->uuid('idempotency_key')->unique();
                $table->string('member_id');
                $table->string('source_wallet', 20);
                $table->decimal('amount', 20, 4);
                $table->decimal('charge_amount', 20, 4)->nullable();
                $table->decimal('net_amount', 20, 4)->nullable();
                $table->string('denomination', 8)->default('USDT');
                $table->string('network')->nullable();
                $table->string('receiving_address')->nullable();
                $table->enum('status', WithdrawalStatus::VALUES)->default(WithdrawalStatus::DRAFT);
                $table->string('payout_network')->nullable();
                $table->string('payout_txid')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->timestamps();

                $table->foreign('member_id')->references('member_id')->on('members')->restrictOnDelete();
                $table->unique(['payout_network', 'payout_txid'], 'withdrawal_payout_network_txid_unique');
                $table->index(['member_id', 'status']);
                $table->index('status');
            });
        }

        if (! Schema::hasTable('wallet_ledger_entries')) {
            Schema::create('wallet_ledger_entries', function (Blueprint $table) {
                $table->id();
                $table->string('wallet', 20);
                $table->enum('direction', WalletLedgerDirection::VALUES);
                $table->decimal('amount', 20, 4);
                $table->string('denomination', 8)->default('USDT');
                $table->string('member_id');
                $table->string('reference')->unique();
                $table->uuid('idempotency_key')->unique();
                $table->foreignId('package_purchase_id')->nullable()->constrained('package_purchases')->restrictOnDelete();
                $table->foreignId('withdrawal_request_id')->nullable()->constrained('withdrawal_requests')->restrictOnDelete();
                $table->unsignedBigInteger('counter_entry_id')->nullable();
                $table->string('actor')->nullable();
                $table->string('reason')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->foreign('member_id')->references('member_id')->on('members')->restrictOnDelete();
                $table->index(['member_id', 'wallet', 'created_at']);
            });

            Schema::table('wallet_ledger_entries', function (Blueprint $table) {
                $table->foreign('counter_entry_id')->references('id')->on('wallet_ledger_entries')->restrictOnDelete();
            });
        }

        if (! Schema::hasTable('withdrawal_status_events')) {
            Schema::create('withdrawal_status_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('withdrawal_request_id')->constrained('withdrawal_requests')->restrictOnDelete();
                $table->string('from_status', 40)->nullable();
                $table->string('to_status', 40);
                $table->string('actor')->nullable();
                $table->text('reason')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['withdrawal_request_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('withdrawal_status_events');
        Schema::dropIfExists('wallet_ledger_entries');
        Schema::dropIfExists('withdrawal_requests');
        Schema::dropIfExists('withdrawal_settings');
    }
};
