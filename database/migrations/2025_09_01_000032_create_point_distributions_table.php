<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('point_distributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id');
            $table->integer('rank'); // 順位
            $table->integer('points'); // 付与されるポイント
            $table->foreignId('pattern_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('point_distributions');
    }
};
