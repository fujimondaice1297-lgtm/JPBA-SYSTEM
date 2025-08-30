<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTournamentResultsTable extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_results', function (Blueprint $table) {
            $table->id();

            $table->string('pro_bowler_license_no');
            $table->foreign('pro_bowler_license_no')
                ->references('license_no')
                ->on('pro_bowlers')
                ->onDelete('cascade');

            $table->unsignedBigInteger('tournament_id');
            $table->foreign('tournament_id')
                ->references('id')
                ->on('tournaments')
                ->onDelete('cascade');

            $table->integer('ranking')->nullable();
            $table->integer('points')->nullable();
            $table->integer('total_pin')->nullable();
            $table->integer('games')->nullable();
            $table->decimal('average', 5, 2)->nullable();
            $table->integer('prize_money')->nullable();

            $table->year('ranking_year');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_results');
    }
}
