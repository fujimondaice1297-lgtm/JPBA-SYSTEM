<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approved_balls', function (Blueprint $table) {
            $table->id();
            $table->string('name');               // ボール名
            $table->string('name_kana')->nullable();
            $table->string('manufacturer');       // メーカー名
            $table->year('release_year')->nullable(); // 発売年
            $table->boolean('approved')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approved_balls');
    }
};

