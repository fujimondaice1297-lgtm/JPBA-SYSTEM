<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddShiftGenderToGameScores extends Migration
{
    public function up()
    {
        Schema::table('game_scores', function (Blueprint $table) {
            // 既存に追加（存在確認は省略：このマイグレーションが単独で追加する前提）
            $table->string('shift')->nullable()->after('stage');   // 例：A / B など
            $table->string('gender', 1)->nullable()->after('shift'); // M / L
        });
    }

    public function down()
    {
        Schema::table('game_scores', function (Blueprint $table) {
            $table->dropColumn(['shift', 'gender']);
        });
    }
}
