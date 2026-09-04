<?php

namespace App\Models;

use App\Enums\Platform;
use Database\Factories\InstanceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'cache_command',
        'backup_command',
        'migrate_command',
        'frontend_command',
        'test_command',
        'allowed_path_prefix',
        'screen_session',
        'serve_port',
        'is_active',
        'held_by_user_id',
        'held_until',
        'hold_note',
    ];

    protected function casts(): array
    {
        return [
            'platform' => Platform::class,
            'serve_port' => 'integer',
            'is_active' => 'boolean',
            'held_until' => 'datetime',
        ];
    }

    /** Стенд можно поднять из панели, только когда известно и имя сессии, и порт. */
    public function isServable(): bool
    {
        return filled($this->screen_session) && filled($this->serve_port);
    }

    /** Адрес стенда через проброшенный к нему порт; null — если порт не задан. */
    public function tunnelUrl(): ?string
    {
        $template = config('deployer.tunnel_url_template');

        if (blank($template) || blank($this->serve_port)) {
            return null;
        }

        return str_replace('{port}', (string) $this->serve_port, $template);
    }

    /** Занят ли стенд прямо сейчас: просроченная бронь — это свободный стенд. */
    public function isHeld(): bool
    {
        return $this->held_by_user_id !== null
            && $this->held_until !== null
            && $this->held_until->isFuture();
    }

    /**
     * Бронь для страницы или null, если стенд свободен.
     *
     * @return ?array{user_id: int, user: ?string, until: string, note: ?string}
     */
    public function holdSummary(): ?array
    {
        if (! $this->isHeld()) {
            return null;
        }

        return [
            'user_id' => $this->held_by_user_id,
            'user' => $this->holder?->name,
            'until' => $this->held_until->toIso8601String(),
            'note' => $this->hold_note,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function holder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'held_by_user_id');
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
