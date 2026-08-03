<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instances', function (Blueprint $table) {
            // Имя screen-сессии произвольное (стенды заводили руками), поэтому храним его,
            // а не выводим из id. Уникальность — иначе непонятно, чей стенд глушим.
            $table->string('screen_session', 64)->nullable()->unique()->after('allowed_path_prefix');
            $table->unsignedInteger('serve_port')->nullable()->unique()->after('screen_session');
        });
    }

    public function down(): void
    {
        Schema::table('instances', function (Blueprint $table) {
            $table->dropColumn(['screen_session', 'serve_port']);
        });
    }
};
