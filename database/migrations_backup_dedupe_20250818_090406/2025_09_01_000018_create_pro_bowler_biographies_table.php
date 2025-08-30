<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProBowlerBiographiesTable extends Migration
{
    public function up(): void
    {
        Schema::create('pro_bowler_biographies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pro_bowler_id')->index(); // 外部キー参照カラム
            $table->string('motto')->nullable();
            $table->text('message')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreignId('pro_bowler_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pro_bowler_biographies');
    }
}

