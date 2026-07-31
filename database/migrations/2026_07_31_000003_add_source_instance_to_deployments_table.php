<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deployments', function (Blueprint $table) {
            // Инстанс-источник для action=copy. Храним связь, а не путь: путь источника
            // могут отредактировать между постановкой в очередь и запуском джобы.
            $table->foreignId('source_instance_id')
                ->nullable()
                ->after('instance_id')
                ->constrained('instances')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('deployments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_instance_id');
        });
    }
};
