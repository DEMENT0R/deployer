<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instances', function (Blueprint $table) {
            // Без значения по умолчанию: чем снимать дамп, зависит от СУБД и хоста —
            // общей команды, которая заработала бы на всех стендах, нет.
            $table->string('backup_command', 1024)->nullable()->after('cache_command');
        });
    }

    public function down(): void
    {
        Schema::table('instances', function (Blueprint $table) {
            $table->dropColumn('backup_command');
        });
    }
};
