<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pro_test_score', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pro_test_id')->constrained('pro_test');
            $table->integer('game_no')->comment('第何ゲームか');
            $table->integer('score')->comment('スコア');

            $table->timestamp('update_date')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pro_test_score');
    }
};
