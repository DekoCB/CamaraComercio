<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
        'avatar_path',
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

    /**
     * Up to two initials from the user's name — the fallback avatar shown
     * everywhere a profile photo hasn't been set (topbar, wherever else
     * <x-avatar> is used).
     */
    public function initials(): string
    {
        return (string) Str::of($this->name)->explode(' ')->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('');
    }

    public function avatarUrl(): ?string
    {
        return $this->avatar_path ? Storage::disk('public')->url($this->avatar_path) : null;
    }
}
