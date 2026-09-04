<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instances', function (Blueprint $table) {
            // Без значения по умолчанию: прогон тестов на стенде может снести его базу,
            // если phpunit.xml проекта не переопределяет DB_*. Включать это должен человек,
            // который в конфиг проекта заглянул.
            $table->string('test_command', 1024)->nullable()->after('frontend_command');
        });
    }

    public function down(): void
    {
        Schema::table('instances', function (Blueprint $table) {
            $table->dropColumn('test_command');
        });
    }
};
