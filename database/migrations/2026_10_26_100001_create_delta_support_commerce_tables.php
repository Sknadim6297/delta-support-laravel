<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('packages')) {
        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price_usd', 16, 4);
            $table->string('tour_title')->nullable();
            $table->text('tour_details')->nullable();
            $table->string('duration')->nullable();
            $table->text('benefits')->nullable();
            $table->string('image_path')->nullable();
            $table->boolean('roi_eligible')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
        }

        if (! Schema::hasTable('company_settings')) {
        Schema::create('company_settings', function (Blueprint $table) {
            $table->id();
            $table->string('company_name')->nullable();
            $table->text('address')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->timestamps();
        });
        }

        if (! Schema::hasTable('payment_settings')) {
        Schema::create('payment_settings', function (Blueprint $table) {
            $table->id();
            $table->string('currency_label')->nullable();
            $table->string('network')->nullable();
            $table->string('receiving_address')->nullable();
            $table->string('qr_image_path')->nullable();
            $table->text('instructions')->nullable();
            $table->string('reference_pattern')->nullable();
            $table->boolean('is_enabled')->default(false);
            $table->timestamps();
        });
        }

        if (! Schema::hasTable('package_purchases')) {
        Schema::create('package_purchases', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->uuid('idempotency_key')->unique();
            $table->string('member_id');
            $table->foreignId('package_id')->constrained('packages');
            $table->string('package_code');
            $table->string('package_name');
            $table->decimal('price_usd', 16, 4);
            $table->string('tour_title')->nullable();
            $table->text('tour_details')->nullable();
            $table->string('duration')->nullable();
            $table->text('benefits')->nullable();
            $table->string('currency_label')->nullable();
            $table->string('network')->nullable();
            $table->string('payment_reference');
            $table->string('status')->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->dateTime('submitted_at');
            $table->dateTime('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->foreign('member_id')->references('member_id')->on('members');
            $table->unique(['network', 'payment_reference']);
            $table->index(['member_id', 'status']);
            $table->index('status');
        });
        }

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE package_purchases MODIFY submitted_at DATETIME NOT NULL, MODIFY reviewed_at DATETIME NULL');
        }

        if (! Schema::hasTable('invoices')) {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->foreignId('package_purchase_id')->unique()->constrained('package_purchases');
            $table->string('member_id');
            $table->string('member_name');
            $table->string('package_name');
            $table->decimal('price_usd', 16, 4);
            $table->string('currency_label')->nullable();
            $table->string('network')->nullable();
            $table->string('payment_reference');
            $table->string('company_name')->nullable();
            $table->text('company_address')->nullable();
            $table->string('company_email')->nullable();
            $table->string('company_phone')->nullable();
            $table->dateTime('purchased_at');
            $table->dateTime('approved_at');
            $table->string('status')->default('approved');
            $table->timestamps();

            $table->foreign('member_id')->references('member_id')->on('members');
            $table->index('member_id');
        });
        }

        if (! Schema::hasTable('payment_decisions')) {
        Schema::create('payment_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_purchase_id')->constrained('package_purchases');
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->string('action');
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index(['package_purchase_id', 'action']);
        });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_decisions');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('package_purchases');
        Schema::dropIfExists('payment_settings');
        Schema::dropIfExists('company_settings');
        Schema::dropIfExists('packages');
    }
};
