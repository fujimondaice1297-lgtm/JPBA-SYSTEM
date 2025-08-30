<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('used_balls', function (Blueprint $table) {
    $table->id();
    $table->foreign('pro_bowler_id')->references('id')->on('pro_bowlers');
    $table->foreignId('approved_ball_id')->constrained();  // 承認ボールとの紐づけ
    $table->string('serial_number')->unique();  // シリアル番号
    $table->string('inspection_number')->unique(); // 検量証番号
    $table->date('registered_at'); // 登録日
    $table->date('expires_at');    // 有効期限
    $table->timestamps();
});

    }

    public function down(): void
    {
        Schema::dropIfExists('used_balls');
    }
};
