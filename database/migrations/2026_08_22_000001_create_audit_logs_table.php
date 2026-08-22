<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            // Автора и инстанс держим ссылками, но переживаем их удаление: запись «кто удалил
            // инстанс» не должна исчезать вместе с инстансом.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('instance_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 64);
            $table->string('subject')->nullable();
            $table->string('summary', 1024)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['created_at', 'id']);
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
