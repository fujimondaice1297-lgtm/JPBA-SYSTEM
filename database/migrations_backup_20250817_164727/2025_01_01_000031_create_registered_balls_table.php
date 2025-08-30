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
            $table->foreign('license_no')->references('license_no')->on('pro_bowlers')->onDelete('cascade');

            $table->unsignedBigInteger('approved_ball_id');
            $table->foreign('approved_ball_id')->references('id')->on('approved_balls')->onDelete('cascade');

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
