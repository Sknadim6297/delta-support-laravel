<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->string('logo_path')->nullable()->after('phone');
            $table->string('primary_color', 7)->nullable()->after('logo_path');
            $table->string('primary_hover_color', 7)->nullable()->after('primary_color');
            $table->string('navy_color', 7)->nullable()->after('primary_hover_color');
            $table->string('canvas_color', 7)->nullable()->after('navy_color');
            $table->string('border_color', 7)->nullable()->after('canvas_color');
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn([
                'logo_path',
                'primary_color',
                'primary_hover_color',
                'navy_color',
                'canvas_color',
                'border_color',
            ]);
        });
    }
};
