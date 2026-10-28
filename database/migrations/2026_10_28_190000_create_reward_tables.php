<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reward_campaigns', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('gift_name');
            $table->string('gift_image_path')->nullable();
            $table->text('gift_description')->nullable();
            $table->decimal('target_amount', 16, 4);
            $table->string('denomination', 8)->default('USDT');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('status', 20)->default('draft');
            $table->unsignedInteger('stock_quantity')->nullable();
            $table->text('terms')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'starts_on']);
            $table->index('ends_on');
        });

        Schema::create('reward_qualifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campaign_id')->constrained('reward_campaigns')->restrictOnDelete();
            $table->string('member_id');
            $table->string('member_name');
            $table->string('sponsor_id')->nullable();
            $table->decimal('achieved_amount', 16, 4);
            $table->decimal('target_amount', 16, 4);
            $table->string('status', 20);
            $table->timestamp('qualified_at')->nullable();
            $table->timestamps();
            $table->unique(['campaign_id', 'member_id']);
            $table->index(['status', 'qualified_at']);
            $table->index('member_id');
        });

        Schema::create('reward_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('qualification_id')->constrained('reward_qualifications')->restrictOnDelete();
            $table->foreignId('campaign_id')->constrained('reward_campaigns')->restrictOnDelete();
            $table->string('member_id');
            $table->string('gift_name');
            $table->string('status', 20);
            $table->timestamp('selected_at')->nullable();
            $table->timestamp('superseded_at')->nullable();
            $table->foreignId('superseded_by_campaign_id')->nullable()->constrained('reward_campaigns')->nullOnDelete();
            $table->foreignId('conflict_campaign_id')->nullable()->constrained('reward_campaigns')->nullOnDelete();
            $table->text('conflict_note')->nullable();
            $table->timestamps();
            $table->unique(['member_id', 'campaign_id']);
            $table->index(['member_id', 'status']);
        });

        Schema::create('reward_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assignment_id')->unique()->constrained('reward_assignments')->restrictOnDelete();
            $table->string('status', 20)->default('pending');
            $table->string('method')->nullable();
            $table->string('tracking_reference')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->string('recipient_confirmation')->nullable();
            $table->text('internal_notes')->nullable();
            $table->timestamps();
            $table->index('status');
        });

        Schema::create('reward_delivery_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('delivery_id')->constrained('reward_deliveries')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_status', 20);
            $table->string('to_status', 20);
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['delivery_id', 'created_at']);
        });

        Schema::table('package_purchases', function (Blueprint $table): void {
            $table->index(['status', 'denomination', 'reviewed_at'], 'package_purchases_reward_business_index');
        });
    }

    public function down(): void
    {
        Schema::table('package_purchases', function (Blueprint $table): void {
            $table->dropIndex('package_purchases_reward_business_index');
        });

        Schema::dropIfExists('reward_delivery_events');
        Schema::dropIfExists('reward_deliveries');
        Schema::dropIfExists('reward_assignments');
        Schema::dropIfExists('reward_qualifications');
        Schema::dropIfExists('reward_campaigns');
    }
};
