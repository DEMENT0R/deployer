<?php

namespace App\Models;

use App\Enums\Platform;
use Database\Factories\InstanceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Instance extends Model
{
    /** @use HasFactory<InstanceFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'path',
        'url',
        'repository_url',
        'platform',
        'git_remote',
        'default_branch',
        'composer_command',
        'migrate_command',
        'frontend_command',
        'allowed_path_prefix',
        'screen_session',
        'serve_port',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'platform' => Platform::class,
            'serve_port' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** Стенд можно поднять из панели, только когда известно и имя сессии, и порт. */
    public function isServable(): bool
    {
        return filled($this->screen_session) && filled($this->serve_port);
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
