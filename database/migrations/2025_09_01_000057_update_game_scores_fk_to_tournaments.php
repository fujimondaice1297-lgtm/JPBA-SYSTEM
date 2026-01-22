<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateGameScoresFkToTournaments extends Migration
{
    public function up()
    {
        // 既に外部キーがある前提で一旦外して付け直す（存在確認しつつ実施）
        Schema::table('game_scores', function (Blueprint $table) {
            // 外部キー名が不明でも dropForeign(['tournament_id']) ならOK
            try {
                $table->dropForeign(['tournament_id']);
            } catch (\Throwable $e) {
                // 無視：外部キー未設定ならスルー
            }
        });

        Schema::table('game_scores', function (Blueprint $table) {
            // tournaments へ貼り直し
            $table->foreign('tournament_id')->references('id')->on('tournaments')->onDelete('cascade');
        });
    }

    public function down()
    {
        // 可能な限り元に戻す（tournamentscore へ戻す）
        Schema::table('game_scores', function (Blueprint $table) {
            try {
                $table->dropForeign(['tournament_id']);
            } catch (\Throwable $e) {
            }
        });

        Schema::table('game_scores', function (Blueprint $table) {
            $table->foreign('tournament_id')->references('id')->on('tournamentscore')->onDelete('cascade');
        });
    }
}
