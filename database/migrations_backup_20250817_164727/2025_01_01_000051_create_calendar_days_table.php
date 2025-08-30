<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('calendar_days', function (Blueprint $table) {
            $table->date('date')->primary();
            $table->string('holiday_name')->nullable(); // 祝日名（例: 建国記念の日）
            $table->boolean('is_holiday')->default(false);
            $table->string('rokuyou')->nullable(); // 先勝, 友引, 先負, 仏滅, 大安, 赤口
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('calendar_days');
    }
};
