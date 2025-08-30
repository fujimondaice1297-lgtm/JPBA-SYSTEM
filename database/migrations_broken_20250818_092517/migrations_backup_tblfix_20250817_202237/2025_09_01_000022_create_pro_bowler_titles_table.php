<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('pro_bowler_titles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pro_bowler_id');
            $table->unsignedBigInteger('tournament_id')->nullable()->index(); // 結果から同期できるとき用
            $table->string('title_name');          // 大会名（手入力時もここに保存）
            $table->unsignedSmallInteger('year');  // 取得年（表示に使う）
            $table->date('won_date')->nullable();  // 決勝日など（任意）
            $table->string('source')->default('manual'); // 'result' or 'manual'
            $table->timestamps();

            // 同一大会で同一選手に2重付与しないため
            $table->unique(['pro_bowler_id','tournament_id']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('pro_bowler_titles');
    }
};
