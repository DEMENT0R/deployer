<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        'role',
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
            'role' => UserRole::class,
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isTester(): bool
    {
        return $this->role === UserRole::Tester;
    }

    public function instances(): BelongsToMany
    {
        return $this->belongsToMany(Instance::class)->withTimestamps();
    }

    public function deployments(): HasMany
    {
        return $this->hasMany(Deployment::class);
    }

    public function accessibleInstances(): Builder
    {
        if ($this->isAdmin()) {
            return Instance::query()->where('is_active', true);
        }

        return Instance::query()
            ->where('is_active', true)
            ->whereHas('users', fn (Builder $query) => $query->where('users.id', $this->id));
    }

    public function hasAccessTo(Instance $instance): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        return $this->instances()->where('instances.id', $instance->id)->exists();
    }
}
