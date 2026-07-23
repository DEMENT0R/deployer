<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Instance;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@local',
            'password' => 'password',
            'role' => UserRole::Admin,
            'email_verified_at' => now(),
        ]);

        $tester = User::factory()->create([
            'name' => 'Tester',
            'email' => 'tester@local',
            'password' => 'password',
            'role' => UserRole::Tester,
            'email_verified_at' => now(),
        ]);

        $instance = Instance::create([
            'name' => 'Demo Staging',
            'path' => '/var/www/demo-staging',
            'git_remote' => 'origin',
            'default_branch' => 'main',
            'composer_command' => 'composer install --no-dev --no-interaction',
            'migrate_command' => 'php artisan migrate --force',
            'frontend_command' => 'npm ci && npm run build',
            'allowed_path_prefix' => '/var/www',
        ]);

        $instance->users()->attach($tester);
    }
}
