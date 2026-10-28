<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reward_evaluation_runs', function (Blueprint $table): void {
            $table->id();
            $table->date('business_date');
            $table->string('status', 20);
            $table->unsignedInteger('members_reviewed')->default(0);
            $table->unsignedInteger('selected_count')->default(0);
            $table->text('message');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index(['business_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reward_evaluation_runs');
    }
};
