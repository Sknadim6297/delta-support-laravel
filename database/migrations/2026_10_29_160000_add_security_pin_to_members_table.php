<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->string('security_pin_hash')->nullable()->after('password');
            $table->timestamp('security_pin_set_at')->nullable()->after('security_pin_hash');
            $table->unsignedTinyInteger('security_pin_failed_attempts')->default(0)->after('security_pin_set_at');
            $table->timestamp('security_pin_locked_until')->nullable()->after('security_pin_failed_attempts');
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn([
                'security_pin_hash',
                'security_pin_set_at',
                'security_pin_failed_attempts',
                'security_pin_locked_until',
            ]);
        });
    }
};
