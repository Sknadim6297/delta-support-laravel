<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('production_initializations')) {
            return;
        }

        Schema::create('production_initializations', function (Blueprint $table): void {
            $table->id();
            $table->string('marker')->unique();
            $table->string('driver');
            $table->string('host')->default('');
            $table->string('port')->default('');
            $table->string('database_name');
            $table->timestamp('initialized_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_initializations');
    }
};
