<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->unsignedSmallInteger('ball_registration_limit')
                ->default(12)
                ->after('inspection_required')
                ->comment('1選手が大会へ登録できる使用ボールの上限個数');
        });
    }

    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn('ball_registration_limit');
        });
    }
};
