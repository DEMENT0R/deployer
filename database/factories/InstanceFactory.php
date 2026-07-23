<?php

namespace Database\Factories;

use App\Enums\Platform;
use App\Models\Instance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Instance>
 */
class InstanceFactory extends Factory
{
    protected $model = Instance::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'path' => '/var/www/'.fake()->slug(),
            'platform' => Platform::Linux,
            'git_remote' => 'origin',
            'default_branch' => 'main',
            'composer_command' => 'composer install --no-dev --no-interaction',
            'migrate_command' => 'php artisan migrate --force',
            'frontend_command' => 'npm ci && npm run build',
            'allowed_path_prefix' => '/var/www',
            'is_active' => true,
        ];
    }
}
