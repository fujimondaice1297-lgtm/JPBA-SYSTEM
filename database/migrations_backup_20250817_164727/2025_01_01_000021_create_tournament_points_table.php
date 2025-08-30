<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('tournament_points', function (Blueprint $table) {
            $table->foreignId('tournament_id')->constrained()->onDelete('cascade');
            $table->integer('rank')->unique();      // 順位
            $table->integer('point');               // ポイント
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('tournament_points');
    }
};
