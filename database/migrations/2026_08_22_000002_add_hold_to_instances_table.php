<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instances', function (Blueprint $table) {
            // Занятость всегда с сроком: бессрочная бронь через неделю превращается
            // в мусор, который никто не снимает, и предупреждение перестают читать.
            $table->foreignId('held_by_user_id')->nullable()->after('is_active')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('held_until')->nullable()->after('held_by_user_id');
            $table->string('hold_note')->nullable()->after('held_until');
        });
    }

    public function down(): void
    {
        Schema::table('instances', function (Blueprint $table) {
            $table->dropConstrainedForeignId('held_by_user_id');
            $table->dropColumn(['held_until', 'hold_note']);
        });
    }
};
