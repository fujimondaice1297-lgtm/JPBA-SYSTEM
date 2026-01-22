<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGameScoresTable extends Migration
{
    public function up()
    {
        Schema::create('game_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained('tournamentscore');
            $table->string('stage');
            $table->string('license_number')->nullable();
            $table->string('name')->nullable();
            $table->string('entry_number')->nullable();
            $table->integer('game_number');
            $table->integer('score');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('game_scores');
    }
}
