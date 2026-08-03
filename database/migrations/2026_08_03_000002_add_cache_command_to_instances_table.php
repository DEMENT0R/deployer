<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Значение для уже заведённых инстансов: без него фикс не доедет до существующих стендов. */
    private const DEFAULT = 'php artisan config:clear && php artisan view:clear && php artisan route:clear';

    public function up(): void
    {
        Schema::table('instances', function (Blueprint $table) {
            $table->string('cache_command', 1024)->nullable()->after('composer_command');
        });

        DB::table('instances')->update(['cache_command' => self::DEFAULT]);
    }

    public function down(): void
    {
        Schema::table('instances', function (Blueprint $table) {
            $table->dropColumn('cache_command');
        });
    }
};
