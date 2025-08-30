<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sponsors', function (Blueprint $table) {
            $table->id();
            $table->string('name');                    // スポンサー名
            $table->string('logo_path')->nullable();   // ロゴ画像のパス
            $table->string('website')->nullable();     // 公式サイトURL
            $table->text('description')->nullable();   // 説明文
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sponsors');
    }
};
