<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProBowlerLinksTable extends Migration
{
    public function up(): void
    {
        Schema::create('pro_bowler_links', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pro_bowler_id')->index(); // 外部キー参照カラム
            $table->string('homepage_url')->nullable();
            $table->string('twitter_url')->nullable();
            $table->string('instagram_url')->nullable();
            $table->string('youtube_url')->nullable();
            $table->string('facebook_url')->nullable();
            $table->timestamps();

            $table->foreign('pro_bowler_id')->references('id')->on('pro_bowlers')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pro_bowler_links');
    }
}
