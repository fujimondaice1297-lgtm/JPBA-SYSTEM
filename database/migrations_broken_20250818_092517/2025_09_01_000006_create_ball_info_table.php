<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ball_info', function (Blueprint $table) {
            $table->id(); // 主キー
            $table->string('brand')->nullable()->comment('ブランド名（例：Storm）');
            $table->string('model')->nullable()->comment('モデル名（例：Phaze II）');
            $table->timestamp('update_date')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ball_info');
    }
};

