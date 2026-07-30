<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deployments', function (Blueprint $table) {
            // SHA рабочего дерева до и после деплоя — по commit_before откатываемся назад.
            $table->string('commit_before', 40)->nullable()->after('branch');
            $table->string('commit_after', 40)->nullable()->after('commit_before');
        });
    }

    public function down(): void
    {
        Schema::table('deployments', function (Blueprint $table) {
            $table->dropColumn(['commit_before', 'commit_after']);
        });
    }
};
