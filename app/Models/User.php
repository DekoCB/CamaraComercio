<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role_id',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * @return string[] permission codes granted to this user's role
     *
     * Uses the (lazily cached) `permissions` relation collection rather
     * than a fresh query, since Gate::before() in AppServiceProvider
     * calls this once per ability check — often several times per
     * request (view rendering + route middleware).
     */
    public function permissionCodes(): array
    {
        return $this->role->permissions->pluck('code')->all();
    }

    /**
     * @return string[] module codes this user's role can navigate to
     */
    public function moduleCodes(): array
    {
        return $this->role->modules->where('is_active', true)->pluck('code')->all();
    }
}
