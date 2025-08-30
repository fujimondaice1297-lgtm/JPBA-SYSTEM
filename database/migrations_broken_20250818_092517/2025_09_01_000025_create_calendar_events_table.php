<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('calendar_events', function (Blueprint $table) {
            $table->id();
            $table->string('title');               // 名称
            $table->date('start_date');
            $table->date('end_date');
            $table->string('venue')->nullable();   // 会場
            // ①プロテスト ②承認大会 ③その他
            $table->enum('kind', ['pro_test','approved','other'])->default('other');
            $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('calendar_events');
    }
};

