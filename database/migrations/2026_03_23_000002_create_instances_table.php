<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instances', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('path');
            $table->string('platform')->default('linux');
            $table->string('git_remote')->default('origin');
            $table->string('default_branch')->default('main');
            $table->string('composer_command')->nullable();
            $table->string('migrate_command')->default('php artisan migrate --force');
            $table->string('frontend_command')->default('npm ci && npm run build');
            $table->string('allowed_path_prefix')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instances');
    }
};
