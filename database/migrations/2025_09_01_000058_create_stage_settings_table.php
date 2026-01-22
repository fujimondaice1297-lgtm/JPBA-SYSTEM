<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateStageSettingsTable extends Migration
{
    public function up()
    {
        Schema::create('stage_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained('tournaments')->onDelete('cascade');
            $table->string('stage');                 // 例：予選 / 準決勝
            $table->integer('total_games')->nullable(); // 想定ゲーム数（任意）
            $table->timestamps();

            $table->unique(['tournament_id', 'stage']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('stage_settings');
    }
}
