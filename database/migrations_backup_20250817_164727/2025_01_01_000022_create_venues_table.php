<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('venues', function (Blueprint $table) {
            $table->id();
            $table->string('name');                     // 会場名
            $table->string('address')->nullable();      // 住所（任意）
            $table->string('postal_code')->nullable();  // 郵便番号（任意）
            $table->string('city')->nullable();         // 市区町村（任意）
            $table->string('prefecture')->nullable();   // 都道府県（任意）
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venues');
    }
};
