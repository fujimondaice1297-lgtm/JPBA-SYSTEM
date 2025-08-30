<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('registered_balls', function (Blueprint $table) {
            $table->id();

            $table->string('license_no');
            $table->string('license_no');

            $table->foreignId('pro_bowler_id');
            $table->foreignId('approved_ball_id');

            $table->string('serial_number');
            $table->date('registered_at');
            $table->date('expired_at');
            $table->string('certificate_number');

            $table->timestamps();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('registered_balls');
    }
};



