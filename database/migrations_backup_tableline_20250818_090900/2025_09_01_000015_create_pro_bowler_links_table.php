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
            $table // 外部キー参照カラム
            $table->string('homepage_url')->nullable();
            $table->string('twitter_url')->nullable();
            $table->string('instagram_url')->nullable();
            $table->string('youtube_url')->nullable();
            $table->string('facebook_url')->nullable();
            $table->timestamps();

            $table->foreignId('pro_bowler_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pro_bowler_links');
    }
}


