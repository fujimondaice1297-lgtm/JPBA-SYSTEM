<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('tournament_participants', function (Blueprint $table) {
            $table->id();

            $table->foreignId('pro_bowler_id');
            $table->foreignId('tournament_id');

            $table->string('pro_bowler_license_no');
            $table->string('pro_bowler_license_no');

            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('tournament_participants');
    }
};



