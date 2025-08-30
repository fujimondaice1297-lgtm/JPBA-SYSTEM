<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approved_ball_pro_bowler', function (Blueprint $table) {
            $table->id();
            $table->string('pro_bowler_license_no'); // プロボウラーのライセンス番号
            $table->foreignId('approved_ball_id')->constrained(); // 承認ボールとの紐づけ
            $table->year('year')->nullable(); // 登録年
            $table->timestamps();

            // 同じプロボウラーが同じ年に同じボールを重複登録しないようにする
            $table->unique(['pro_bowler_license_no', 'approved_ball_id', 'year'], 'pro_ball_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approved_ball_pro_bowler');
    }
};
