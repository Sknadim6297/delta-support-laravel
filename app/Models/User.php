<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    public const ADMIN_ROLE_MAIN = 'main';

    public const ADMIN_ROLE_SUB = 'sub';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'login_id',
        'password',
        'is_admin',
        'admin_role',
        'is_active',
        'must_change_password',
        'last_login_at',
        'created_by',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function adminPermissions(): HasMany
    {
        return $this->hasMany(AdminUserPermission::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(self::class, 'created_by');
    }

    public function isMainAdmin(): bool
    {
        return app(\App\Services\AdminAccess::class)->isMainAdmin($this);
    }

    public function isSubAdmin(): bool
    {
        return app(\App\Services\AdminAccess::class)->isSubAdmin($this);
    }

    public function hasAdminPermission(string $permission): bool
    {
        return app(\App\Services\AdminAccess::class)->can($this, $permission);
    }
}
