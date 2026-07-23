<?php

namespace App\Models;

use App\Enums\Platform;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Instance extends Model
{
    /** @use HasFactory<\Database\Factories\InstanceFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'path',
        'platform',
        'git_remote',
        'default_branch',
        'composer_command',
        'migrate_command',
        'frontend_command',
        'allowed_path_prefix',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'platform' => Platform::class,
            'is_active' => 'boolean',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function deployments(): HasMany
    {
        return $this->hasMany(Deployment::class);
    }

    public function latestDeployment(): HasMany
    {
        return $this->hasMany(Deployment::class)->latest();
    }
}
