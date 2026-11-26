<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Additive Sub-Admin management columns + permission assignments.
 * Existing is_admin accounts become Main Admins with full access retained.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table): void {
                if (! Schema::hasColumn('users', 'login_id')) {
                    $table->string('login_id', 64)->nullable()->unique()->after('email');
                }
                if (! Schema::hasColumn('users', 'admin_role')) {
                    $table->string('admin_role', 16)->nullable()->after('is_admin');
                }
                if (! Schema::hasColumn('users', 'is_active')) {
                    $table->boolean('is_active')->default(true)->after('admin_role');
                }
                if (! Schema::hasColumn('users', 'last_login_at')) {
                    $table->timestamp('last_login_at')->nullable()->after('must_change_password');
                }
                if (! Schema::hasColumn('users', 'created_by')) {
                    $table->foreignId('created_by')->nullable()->after('last_login_at')
                        ->constrained('users')->nullOnDelete();
                }
            });

            // Preserve existing administrators as Main Admins (full access).
            if (Schema::hasColumn('users', 'admin_role')) {
                DB::table('users')
                    ->where('is_admin', true)
                    ->where(function ($query): void {
                        $query->whereNull('admin_role')->orWhere('admin_role', '');
                    })
                    ->update([
                        'admin_role' => 'main',
                        'is_active' => true,
                        'updated_at' => now(),
                    ]);
            }
        }

        if (! Schema::hasTable('admin_user_permissions')) {
            Schema::create('admin_user_permissions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('permission_key', 96);
                $table->timestamps();
                $table->unique(['user_id', 'permission_key'], 'admin_user_permissions_user_perm_unique');
                $table->index('permission_key');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_user_permissions');

        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'created_by')) {
                $table->dropConstrainedForeignId('created_by');
            }
            foreach (['last_login_at', 'is_active', 'admin_role', 'login_id'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
