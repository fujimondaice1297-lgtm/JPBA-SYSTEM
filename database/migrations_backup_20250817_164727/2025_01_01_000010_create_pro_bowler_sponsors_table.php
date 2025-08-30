<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProBowlerSponsorsTable extends Migration
{
    public function up(): void
    {
        Schema::create('pro_bowler_sponsors', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pro_bowler_id')->index(); // 外部キー参照カラム
            $table->string('sponsor_name');
            $table->string('sponsor_note')->nullable();
            $table->integer('start_year')->nullable();
            $table->integer('end_year')->nullable();
            $table->timestamps();

            $table->foreign('pro_bowler_id')->references('id')->on('pro_bowlers')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pro_bowler_sponsors');
    }
}
