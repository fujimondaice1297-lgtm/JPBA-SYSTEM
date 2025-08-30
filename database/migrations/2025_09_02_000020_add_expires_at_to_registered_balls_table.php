<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('registered_balls', function (Blueprint $table) {
            // 日付で管理するなら date、時刻まで管理したいなら dateTime に
            if (!Schema::hasColumn('registered_balls', 'expires_at')) {
                $table->date('expires_at')->nullable()->comment('有効期限');
                // 例: $table->dateTime('expires_at')->nullable()->comment('有効期限');
            }
        });
    }

    public function down(): void
    {
        Schema::table('registered_balls', function (Blueprint $table) {
            if (Schema::hasColumn('registered_balls', 'expires_at')) {
                $table->dropColumn('expires_at');
            }
        });
    }
};
