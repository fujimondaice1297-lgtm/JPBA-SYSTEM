<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTournamentAwardsTable extends Migration {
    public function up(): void {
        Schema::create('tournament_awards', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('tournament_id');
            $table$table->foreignId('tournament_id');

            $table->integer('rank');         
            $table->integer('prize_money');  

            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('tournament_awards');
    }
}