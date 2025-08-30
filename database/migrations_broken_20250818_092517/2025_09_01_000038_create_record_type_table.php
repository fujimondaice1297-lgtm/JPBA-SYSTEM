<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up()
    {
        Schema::create('record_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pro_bowler_id');
            $table->enum('record_type', ['perfect', 'seven_ten', 'eight_hundred']);
            $table->string('tournament_name');
            $table->string('game_numbers');
            $table->string('frame_number')->nullable();
            $table->date('awarded_on');
            $table->string('certification_number');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('record_types');
    }
};

