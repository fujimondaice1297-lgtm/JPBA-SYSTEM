<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('tournament_participants', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('tournament_id');
            $table->foreign('tournament_id')
                ->references('id')
                ->on('tournaments')
                ->onDelete('cascade');

            $table->string('pro_bowler_license_no');
            $table->foreign('pro_bowler_license_no')
                ->references('license_no')
                ->on('pro_bowlers')
                ->onDelete('cascade');

            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('tournament_participants');
    }
};
